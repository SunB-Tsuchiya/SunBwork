<?php

namespace App\Http\Controllers\SunA\GeneralAffairs\Nencho;

use App\Http\Controllers\Controller;
use App\Http\Requests\SunA\GeneralAffairs\Nencho\CampaignRequest;
use App\Models\SunA\GeneralAffairs\ApplicationType;
use App\Models\SunA\GeneralAffairs\Nencho\Campaign;
use App\Models\Company;
use App\Models\SunA\GeneralAffairs\Nencho\Submission;
use App\Models\SunA\GeneralAffairs\Nencho\Target;
use App\Models\User;
use App\Models\AnnouncementRecipient;
use App\Models\ClerkCalendarReminder;
use App\Models\SunA\GeneralAffairs\Nencho\Notice;
use App\Services\SunA\GeneralAffairs\Nencho\NoticeService;
use App\Services\SunA\GeneralAffairs\Nencho\TargetSyncService;
use App\Support\SunA\GeneralAffairs\CompanyTabs;
use App\Models\SunA\GeneralAffairs\Nencho\EmployeeProfile;
use App\Support\SunA\GeneralAffairs\Nencho\NenchoRules;
use App\Support\SunA\GeneralAffairs\Nencho\ProfileDiff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * サンエー印刷 総務: 年末調整の受付（会社×年度）と対象者の管理。
 * 総務は両社（サンエー印刷・サン・ブレーン）の受付を扱う。会社では絞り込まず、権限（suna_staff:manage_nencho）で制御し、画面で会社ごとに切り分ける。
 */
class CampaignController extends Controller
{
    public function index(Request $request)
    {
        $companies = CompanyTabs::list();
        $selected = $companies->firstWhere('id', (int) $request->query('company')) ?? $companies->firstWhere('id', Auth::user()->company_id) ?? $companies->first();
        abort_unless($selected, 404);

        return Inertia::render('SunA/GeneralAffairs/Nencho/Campaigns/Index', [
            'companies' => $companies->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
            'selectedCompanyId' => $selected->id,
            'campaigns' => Campaign::forCompany($selected->id)
                ->withCount(['targets', 'targets as excluded_count' => fn ($q) => $q->where('is_excluded', true)])
                ->orderByDesc('fiscal_year')
                ->get(),
            'statuses' => Campaign::STATUSES,
        ]);
    }

    public function create(Request $request)
    {
        $companies = CompanyTabs::list();
        $selected = $companies->firstWhere('id', (int) $request->query('company')) ?? $companies->firstWhere('id', Auth::user()->company_id) ?? $companies->first();
        abort_unless($selected, 404);
        $year = (int) now('Asia/Tokyo')->format('Y');

        // 会社ごとに、前回の受付の会社側情報（税務署・支払者）を初期値として引き継ぐ
        $lastInfo = [];
        foreach ($companies as $c) {
            $last = Campaign::forCompany($c->id)->orderByDesc('fiscal_year')->first();
            $lastInfo[$c->id] = [
                'tax_office_name' => $last?->tax_office_name ?? '',
                'municipality_name' => $last?->municipality_name ?? '',
                'payer_name' => $last?->payer_name ?? '',
                'payer_corporate_number' => $last?->payer_corporate_number ?? '',
                'payer_postal_code' => $last?->payer_postal_code ?? '',
                'payer_prefecture' => $last?->payer_prefecture ?? '',
                'payer_city' => $last?->payer_city ?? '',
                'payer_street' => $last?->payer_street ?? '',
            ];
        }

        return Inertia::render('SunA/GeneralAffairs/Nencho/Campaigns/Form', [
            'campaign' => null,
            'companies' => $companies->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
            'defaults' => [
                'company_id' => $selected->id,
                'fiscal_year' => $year,
                'title' => '令和' . ($year - 2018) . '年分 年末調整',
                'opens_on' => now('Asia/Tokyo')->toDateString(),
                'due_on' => now('Asia/Tokyo')->addDays(14)->toDateString(),
                'message' => '',
                'status' => 'open',
            ] + $lastInfo[$selected->id],
            'lastInfo' => $lastInfo,
            'statuses' => Campaign::STATUSES,
            'prefectures' => NenchoRules::PREFECTURES,
        ]);
    }

    public function store(CampaignRequest $request, TargetSyncService $sync)
    {
        $data = $request->validated();
        $campaign = Campaign::create($data + [
            'created_by' => Auth::id(),
            'application_type_id' => ApplicationType::forCompany((int) Auth::user()->company_id)->where('feature_key', 'nencho')->value('id'),
        ]);
        $count = $sync->sync($campaign);

        return to_route('clerk.general_affairs.nencho.campaigns.show', ['campaign' => $campaign->id])
            ->with('success', "受付を作成し、対象者{$count}名を登録しました。");
    }

    public function show(Campaign $campaign, NoticeService $notices)
    {
        $submissions = Submission::where('campaign_id', $campaign->id)->get()->keyBy('user_id');
        $profiles = EmployeeProfile::whereIn('user_id', $submissions->keys())->get()->keyBy('user_id');   // 社員情報との照合用（提出済みの申告だけ照合する）
        $targets = Target::where('campaign_id', $campaign->id)
            ->with(['user:id,name,employment_type,department_id', 'user.department:id,name'])
            ->get()
            ->map(fn (Target $t) => [
                'submission_id' => $submissions->get($t->user_id)?->id,
                'status' => $submissions->get($t->user_id)?->status,
                'submitted_at' => $submissions->get($t->user_id)?->submitted_at?->timezone('Asia/Tokyo')->format('Y-m-d H:i'),
                'current_step' => $submissions->get($t->user_id)?->current_step,
                'entered_by_type' => $submissions->get($t->user_id)?->entered_by_type,
                'profile_status' => ($sub = $submissions->get($t->user_id)) && $sub->status === 'submitted' ? ProfileDiff::compute($sub, $profiles->get($t->user_id))['status'] : null,
                'id' => $t->id,
                'user_id' => $t->user_id,
                'name' => $t->user?->name ?? $t->user_name ?? '（ユーザー削除済み）',
                'department' => $t->user?->department?->name,
                'employment_type' => $t->user?->employment_type,
                'is_excluded' => $t->is_excluded,
                'excluded_reason' => $t->excluded_reason,
                'added_manually' => $t->added_manually,
            ])
            ->sortBy([['is_excluded', 'asc'], ['name', 'asc']])
            ->values();

        $candidates = User::where('company_id', $campaign->company_id)
            ->whereNotIn('id', $targets->pluck('user_id')->filter())
            ->orderBy('name')
            ->get(['id', 'name', 'employment_type'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'employment_type' => $u->employment_type]);

        return Inertia::render('SunA/GeneralAffairs/Nencho/Campaigns/Show', [
            'campaign' => $campaign,
            'companyName' => Company::whereKey($campaign->company_id)->value('name'),
            'statuses' => Campaign::STATUSES,
            'targets' => $targets,
            // 回収状況のサマリー（除外した人は数えない）
            'summary' => [
                'total' => $targets->where('is_excluded', false)->count(),
                'not_started' => $targets->where('is_excluded', false)->whereNull('status')->count(),
                'draft' => $targets->where('is_excluded', false)->where('status', 'draft')->count(),
                'submitted' => $targets->where('is_excluded', false)->where('status', 'submitted')->count(),
                'returned' => $targets->where('is_excluded', false)->where('status', 'returned')->count(),
                'profile_diff' => $targets->where('is_excluded', false)->whereIn('profile_status', ['unregistered', 'diff'])->count(),
            ],
            'statusLabels' => Submission::STATUSES,
            // 案内（お知らせ・カレンダーのリマインダー）
            'noticeDrafts' => $notices->drafts($campaign),
            'noticeKinds' => Notice::KINDS,
            'reminderDraft' => $notices->reminderDraft($campaign),
            'notices' => Notice::where('campaign_id', $campaign->id)->orderByDesc('created_at')->orderByDesc('id')->get()->map(fn ($n) => [
                'id' => $n->id, 'kind' => $n->kind, 'title' => $n->title, 'recipient_count' => $n->recipient_count, 'sent_by_name' => $n->sent_by_name,
                'created_at' => $n->created_at?->timezone('Asia/Tokyo')->format('Y-m-d H:i'),
                'read_count' => $n->announcement_id ? AnnouncementRecipient::where('announcement_id', $n->announcement_id)->whereNotNull('read_at')->count() : 0,
            ]),
            'reminder' => ($r = $campaign->calendar_reminder_id ? ClerkCalendarReminder::find($campaign->calendar_reminder_id) : null)
                ? ['is_active' => (bool) $r->is_active, 'starts_on' => $r->starts_on?->toDateString(), 'ends_on' => $r->ends_on?->toDateString(), 'content' => $r->content]
                : null,
            'candidates' => $candidates,
            'employmentLabels' => NenchoRules::EMPLOYMENT_LABELS,
        ]);
    }

    public function edit(Campaign $campaign)
    {

        return Inertia::render('SunA/GeneralAffairs/Nencho/Campaigns/Form', [
            'campaign' => $campaign,
            'companies' => CompanyTabs::list()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
            'defaults' => null,
            'lastInfo' => [],
            'statuses' => Campaign::STATUSES,
            'prefectures' => NenchoRules::PREFECTURES,
        ]);
    }

    public function update(CampaignRequest $request, Campaign $campaign, NoticeService $notices)
    {
        $campaign->update($request->validated());
        $notices->syncReminder($campaign->fresh(), Auth::user());   // 日付・タイトルを変えたら、カレンダーのリマインダーも合わせる

        return to_route('clerk.general_affairs.nencho.campaigns.show', ['campaign' => $campaign->id])->with('success', '受付を更新しました。');
    }

    /** 対象者の取り込み直し（新しく対象に当てはまる人だけを追加） */
    public function sync(Campaign $campaign, TargetSyncService $sync)
    {
        $count = $sync->sync($campaign);

        return back()->with('success', $count > 0 ? "対象者を{$count}名追加しました。" : '追加する対象者はいませんでした。');
    }

    /** 対象者の除外／戻す */
    public function toggleTarget(Request $request, Campaign $campaign, Target $target)
    {
        abort_unless($target->campaign_id === $campaign->id, 404);
        $data = $request->validate([
            'is_excluded' => ['required', 'boolean'],
            'excluded_reason' => ['nullable', 'string', 'max:200'],
        ]);
        $target->update([
            'is_excluded' => $data['is_excluded'],
            'excluded_reason' => $data['is_excluded'] ? ($data['excluded_reason'] ?? null) : null,
        ]);

        return back()->with('success', $data['is_excluded'] ? '対象から除外しました。' : '対象に戻しました。');
    }

    /** 対象者の手動追加（派遣・業務委託など、自動対象外の人を個別に追加） */
    public function storeTarget(Request $request, Campaign $campaign)
    {
        $data = $request->validate(['user_id' => ['required', 'integer']]);
        $user = User::where('company_id', $campaign->company_id)->findOrFail($data['user_id']);
        abort_if(Target::where('campaign_id', $campaign->id)->where('user_id', $user->id)->exists(), 422, 'すでに対象者です。');
        Target::create(['campaign_id' => $campaign->id, 'user_id' => $user->id, 'user_name' => $user->name, 'added_manually' => true]);

        return back()->with('success', "{$user->name}さんを対象者に追加しました。");
    }

}
