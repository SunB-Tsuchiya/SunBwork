<?php

namespace App\Http\Controllers\SunA\GeneralAffairs\Nencho;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\SunA\GeneralAffairs\Nencho\Campaign;
use App\Models\SunA\GeneralAffairs\Nencho\EmployeeProfile;
use App\Models\SunA\GeneralAffairs\Nencho\InsuranceItem;
use App\Models\SunA\GeneralAffairs\Nencho\Submission;
use App\Models\SunA\GeneralAffairs\Nencho\Target;
use App\Models\User;
use App\Services\SunA\GeneralAffairs\Nencho\EditLockService;
use App\Services\SunA\GeneralAffairs\Nencho\SubmissionService;
use App\Support\SunA\GeneralAffairs\Nencho\Completeness;
use App\Support\SunA\GeneralAffairs\Nencho\NenchoRules;
use App\Support\SunA\GeneralAffairs\Nencho\StepRules;
use App\Support\SunA\GeneralAffairs\StaffAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

/**
 * 従業員: 自分の年末調整の入力（ステップ形式・下書き保存・前年読み込み・提出・取り下げ）。
 * サンエー印刷・サン・ブレーンのどちらの従業員も、自分が受付の対象者であれば使える（会社タイプでは制限しない）。
 * 自分の提出物だけ操作できる（他人の ID は 404）。
 */
class MyNenchoController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        $targets = Target::where('user_id', $userId)->where('is_excluded', false)->with('campaign')->get();
        $submissions = Submission::where('user_id', $userId)->get()->keyBy('campaign_id');
        $companies = Company::whereIn('id', $targets->pluck('campaign.company_id')->filter())->pluck('name', 'id');

        $items = $targets->filter(fn ($t) => $t->campaign)->map(function (Target $t) use ($submissions, $companies) {
            $c = $t->campaign;
            $sub = $submissions->get($c->id);

            return [
                'campaign_id' => $c->id,
                'title' => $c->title,
                'fiscal_year' => $c->fiscal_year,
                'company_name' => $companies->get($c->company_id),
                'opens_on' => $c->opens_on?->toDateString(),
                'due_on' => $c->due_on?->toDateString(),
                'message' => $c->message,
                'is_open' => $this->campaignOpen($c),
                'submission_id' => $sub?->id,
                'status' => $sub?->status,
                'return_comment' => $sub?->status === 'returned' ? $sub->return_comment : null,
                'submitted_at' => $sub?->submitted_at?->timezone('Asia/Tokyo')->format('Y-m-d H:i'),
            ];
        })->sortByDesc('fiscal_year')->values();

        return Inertia::render('SunA/GeneralAffairs/Nencho/Employee/Index', [
            'items' => $items,
            'statuses' => Submission::STATUSES,
        ]);
    }

    public function start(Campaign $campaign, SubmissionService $service)
    {
        $user = Auth::user();
        abort_unless(Target::where('campaign_id', $campaign->id)->where('user_id', $user->id)->where('is_excluded', false)->exists(), 404);

        $existing = Submission::where('campaign_id', $campaign->id)->where('user_id', $user->id)->first();
        if (! $existing) {
            abort_unless($this->campaignOpen($campaign), 403, 'この受付は現在、入力できません。');
            $existing = $service->start($campaign, $user);
        }

        return to_route('user.general_affairs.nencho.show', ['submission' => $existing->id]);
    }

    /**
     * 代理入力の開始（総務）: 端末のない従業員の分を、総務が同じフォームで入力する。
     * 未入力・入力中・差戻しの申告だけ（提出済みは、先に差戻しする）。権限 manage_nencho はルートで確認済み。
     */
    public function proxyStart(Campaign $campaign, User $user, SubmissionService $service)
    {
        abort_unless(Target::where('campaign_id', $campaign->id)->where('user_id', $user->id)->where('is_excluded', false)->exists(), 404);
        $sub = Submission::where('campaign_id', $campaign->id)->where('user_id', $user->id)->first();
        if ($sub && $sub->status === 'submitted') {
            return to_route('clerk.general_affairs.nencho.submissions.show', ['submission' => $sub->id])->with('error', '提出済みの申告は、先に「差し戻す」と、代理で修正できます。');
        }
        $sub ??= $service->start($campaign, $user, Auth::user());
        $service->markProxy($sub, Auth::user());

        return to_route('clerk.general_affairs.nencho.proxy.show', ['submission' => $sub->id]);
    }

    public function show(Request $request, Submission $submission, SubmissionService $service, EditLockService $locks)
    {
        $this->authorizeSubmission($submission);
        $submission->load(['campaign', 'spouse', 'dependents', 'insuranceItems']);
        $step = max(1, min(7, (int) $request->query('step', $submission->current_step)));
        $editable = $this->editable($submission);
        // 同時編集の防止: 編集できる状態の申告は、開いた人が編集ロックを取る。ほかの人（または別モード）が編集中なら読み取り専用
        $lockInfo = null;
        if ($editable) {
            $res = $locks->acquire($submission, Auth::user(), $this->mode());
            if ($res['ok']) {
                $lockInfo = ['token' => $res['token'], 'blocked' => false, 'holder' => null, 'canTakeOver' => false];
            } else {
                $editable = false;
                $lockInfo = ['token' => null, 'blocked' => true, 'holder' => $res['holder'], 'canTakeOver' => $this->canTakeOver($res['holder'])];
            }
        }
        $reviewed = $submission->current_step >= 7;      // 確認画面を一度でも開いたか（以降の「次へ」は不備のある工程だけをたどる）
        if ($step === 7 && $editable && ! $reviewed) {
            $submission->update(['current_step' => 7]);
            $reviewed = true;
        }
        $allMissing = Completeness::missing($submission);
        $stepStatus = [];
        foreach (range(1, 6) as $no) {
            $stepStatus[$no] = ['visited' => $submission->current_step >= $no, 'missing' => collect($allMissing)->where('step', $no)->count()];
        }

        $missing = $step === 7 ? $allMissing : [];
        $spouse = $submission->spouse;
        $items = [];
        foreach (array_keys(InsuranceItem::KINDS) as $kind) {
            $items[$kind] = $submission->insuranceItems->where('kind', $kind)->values()->map->toArray()->all();
        }

        return Inertia::render('SunA/GeneralAffairs/Nencho/Employee/Form', [
            'campaign' => [
                'id' => $submission->campaign->id,
                'title' => $submission->campaign->title,
                'fiscal_year' => $submission->campaign->fiscal_year,
                'due_on' => $submission->campaign->due_on?->toDateString(),
                'message' => $submission->campaign->message,
                'company_name' => Company::whereKey($submission->campaign->company_id)->value('name'),
            ],
            'submission' => $submission->makeHidden(['spouse', 'dependents', 'insuranceItems', 'campaign']),
            'spouse' => $spouse ? $spouse->toArray() + ['masked_my_number' => $spouse->maskedMyNumber()] : null,
            'dependents' => $submission->dependents->map(fn ($d) => $d->toArray() + ['masked_my_number' => $d->maskedMyNumber()])->values(),
            'insuranceItems' => $items,
            'step' => $step,
            'steps' => collect(StepRules::STEPS)->map(fn ($v, $k) => ['no' => $k] + $v)->values(),
            'editable' => $editable,
            'lock' => $lockInfo,
            'canWithdraw' => ! $this->proxy() && $submission->status === 'submitted' && $this->campaignOpen($submission->campaign),
            'canCarryOver' => $service->previous($submission) !== null,
            'canReturn' => ! $this->proxy() && $submission->status === 'submitted' && StaffAccess::for(Auth::user())->manageNencho,
            // 代理入力（総務）: 同じフォームを、ルート名の接頭辞を切り替えて使う
            'proxy' => $this->proxy() ? ['user_name' => $submission->user_name ?? $submission->name, 'campaign_id' => $submission->campaign_id] : null,
            'routePrefix' => $this->proxy() ? 'clerk.general_affairs.nencho.proxy' : 'user.general_affairs.nencho',
            'reviewed' => $reviewed,
            'stepStatus' => $stepStatus,
            'problemSteps' => collect($allMissing)->pluck('step')->unique()->values(),
            'missing' => $missing,
            'notices' => $step === 7 ? Completeness::notices($submission) : [],
            'myNumberRegistered' => $submission->user_id ? (bool) EmployeeProfile::where('user_id', $submission->user_id)->value('my_number') : false,
            'statuses' => Submission::STATUSES,
            'options' => $this->options(),
            'prefectures' => NenchoRules::PREFECTURES,
        ]);
    }

    public function saveStep(Request $request, Submission $submission, int $step, SubmissionService $service)
    {
        $this->authorizeSubmission($submission);
        abort_unless($this->editable($submission), 403, '現在は入力できません。');
        if ($blocked = $this->requireLock($request, $submission)) {
            return $blocked;
        }
        $key = StepRules::stepKey($step);
        abort_if(! $key || $key === 'review', 404);

        $input = StepRules::prepare($key, $request->except(['go']));
        [$rules, $messages, $attributes] = StepRules::rules($key);
        $data = Validator::make($input, $rules, $messages, $attributes)->validate();
        $service->saveStep($submission, $key, $data);
        if ($this->proxy()) {
            $service->markProxy($submission, Auth::user(), false);   // 入力者を「総務の代理」に
        }

        $go = $request->input('go', 'next');
        $reviewed = $submission->current_step >= 7;
        $flash = null;
        if ($go === 'review') {
            $next = 7;
        } elseif ($go === 'prev') {
            $next = max(1, $step - 1);
        } elseif ($go === 'stay') {
            $next = $step;
        } elseif ($reviewed) {
            // 確認画面を開いたあとの修正: 問題のない工程は飛ばし、次の不備のある工程へ。なければ確認画面へ戻る
            $submission->refresh()->load(['spouse', 'dependents', 'insuranceItems']);
            $problems = collect(Completeness::missing($submission))->pluck('step')->unique()->sort()->values();
            $after = $problems->first(fn ($n) => $n > $step);
            $next = $after ?? $problems->first(fn ($n) => $n < $step) ?? 7;
            $flash = $next === 7 ? '不備はありません。確認画面に戻りました。' : '不備のある次の工程へ移動しました。';
        } else {
            $next = min(7, $step + 1);
        }
        $submission->update(['current_step' => max($submission->current_step, min($next, 7))]);

        return to_route($this->routeName('show'), ['submission' => $submission->id, 'step' => $next])
            ->with('success', $flash ?? ($go === 'stay' ? '下書きを保存しました。' : null));
    }

    public function carryOver(Request $request, Submission $submission, SubmissionService $service)
    {
        $this->authorizeSubmission($submission);
        abort_unless($this->editable($submission), 403, '現在は入力できません。');
        if ($blocked = $this->requireLock($request, $submission)) {
            return $blocked;
        }
        $data = $request->validate(['scope' => ['required', 'in:category,spouse,dependents,insurance']]);
        $ok = $service->carryOver($submission, $data['scope']);

        return back()->with($ok ? 'success' : 'error', $ok ? '前年の内容を読み込みました。内容を確認して、変わった点を直してください。' : '読み込める前年の申告がありません。');
    }

    public function submit(Request $request, Submission $submission, SubmissionService $service)
    {
        $this->authorizeSubmission($submission);
        abort_unless($this->editable($submission), 403, '現在は提出できません。');
        if ($blocked = $this->requireLock($request, $submission)) {
            return $blocked;
        }
        $submission->load(['campaign', 'spouse', 'dependents', 'insuranceItems']);
        if (Completeness::missing($submission)) {
            return to_route($this->routeName('show'), ['submission' => $submission->id, 'step' => 7])->with('error', '未入力の項目があります。内容を確認してください。');
        }
        // 代理入力の提出: 本人確認（紙を受け取った・本人に確認した・メモ）を任意で記録する
        $confirmation = $this->proxy() ? $request->validate([
            'confirmation.paper' => ['nullable', 'boolean'],
            'confirmation.person' => ['nullable', 'boolean'],
            'confirmation.note' => ['nullable', 'string', 'max:500'],
        ])['confirmation'] ?? [] : null;
        $service->submit($submission, Auth::user(), $confirmation);
        app(EditLockService::class)->clear($submission);   // 提出したら編集ロックを解除

        if ($this->proxy()) {
            return to_route('clerk.general_affairs.nencho.submissions.show', ['submission' => $submission->id])->with('success', '代理で提出しました。');
        }

        return to_route('user.general_affairs.nencho.index')->with('success', '年末調整を提出しました。締切前であれば、取り下げて修正できます。');
    }

    /** ハートビート: 画面を開いている間、編集ロックを延長する。失効・奪われた場合は ok=false */
    public function heartbeat(Request $request, Submission $submission, EditLockService $locks)
    {
        $this->authorizeSubmission($submission);
        $ok = $locks->touch($submission, $request->input('lock_token'));

        return response()->json(['ok' => $ok, 'holder' => $ok ? null : $locks->holder($submission)]);
    }

    /** 画面を閉じたとき: 自分のロックを解除する（ほかの人のロックは外さない） */
    public function release(Request $request, Submission $submission, EditLockService $locks)
    {
        $this->authorizeSubmission($submission);
        $locks->release($submission, $request->input('lock_token'));

        return response()->json(['ok' => true]);
    }

    /** 編集の引き継ぎ: 総務は本人の編集を、同じ人は別の画面の編集を引き継げる（本人は総務の代理入力を引き継げない） */
    public function takeover(Submission $submission, EditLockService $locks)
    {
        $this->authorizeSubmission($submission);
        abort_unless($this->editable($submission), 403, '現在は編集できません。');
        $holder = $locks->holder($submission);
        abort_if($holder && ! $this->canTakeOver($holder), 403, 'この編集は引き継げません。');
        $locks->acquire($submission, Auth::user(), $this->mode(), true);

        return to_route($this->routeName('show'), ['submission' => $submission->id])->with('success', '編集を引き継ぎました。');
    }

    public function withdraw(Submission $submission, SubmissionService $service)
    {
        $this->authorizeSubmission($submission);
        abort_unless($submission->status === 'submitted' && $this->campaignOpen($submission->campaign), 403, '現在は取り下げできません。');
        $service->withdraw($submission, Auth::user());

        return to_route('user.general_affairs.nencho.show', ['submission' => $submission->id, 'step' => 7])->with('success', '提出を取り下げました。修正して、もう一度提出してください。');
    }

    /** 代理入力（総務）のルートか。権限（manage_nencho）はルートのミドルウェアで確認済み */
    private function proxy(): bool
    {
        return request()->routeIs('clerk.general_affairs.nencho.proxy.*');
    }

    private function mode(): string
    {
        return $this->proxy() ? 'proxy' : 'self';
    }

    /** 編集の引き継ぎができるか: 総務の代理入力は常に、本人は同じ人の別の画面だけ */
    private function canTakeOver(?array $holder): bool
    {
        return $this->proxy() || ($holder && (int) $holder['user_id'] === (int) Auth::id());
    }

    /**
     * 保存の前に、この画面が編集ロックを持っているか確認する（持っていなければ保存せず、読み取り専用で開き直す）。
     * 確認できたら、ロックを延長する。
     */
    private function requireLock(Request $request, Submission $submission)
    {
        $locks = app(EditLockService::class);
        if ($locks->touch($submission, $request->input('lock_token'))) {
            return null;
        }

        return to_route($this->routeName('show'), ['submission' => $submission->id])
            ->with('error', '他の画面（または他の方）が編集を開始したため、保存できませんでした。最新の内容を開き直してください。');
    }

    /** 現在のモード（本人／代理）に合わせたルート名 */
    private function routeName(string $name): string
    {
        return ($this->proxy() ? 'clerk.general_affairs.nencho.proxy.' : 'user.general_affairs.nencho.') . $name;
    }

    private function authorizeSubmission(Submission $submission): void
    {
        if ($this->proxy()) {
            abort_unless($submission->user_id !== null, 404);   // ユーザー削除済みの申告は代理入力しない

            return;
        }
        abort_unless($submission->user_id !== null && (int) $submission->user_id === (int) Auth::id(), 404);
    }

    /** 受付が入力できる期間か（状態が受付中で、開始日〜締切日の間） */
    private function campaignOpen(Campaign $c): bool
    {
        $today = now('Asia/Tokyo')->toDateString();

        return $c->status === 'open' && $today >= $c->opens_on?->toDateString() && $today <= $c->due_on?->toDateString();
    }

    private function editable(Submission $s): bool
    {
        $s->loadMissing('campaign');

        // 差戻しされた提出物は、受付期間外でもその従業員だけ修正できる（総務が差戻しの判断をしたため）
        // 代理入力（総務）は、受付期間外でも入力中の申告を入力できる（総務の判断）
        return $s->status === 'returned' || ($s->status === 'draft' && ($this->proxy() || $this->campaignOpen($s->campaign)));
    }

    /** フォームの選択肢（国のコード値） */
    private function options(): array
    {
        $o = fn (array $m) => NenchoRules::options($m);

        return [
            'householdRelations' => $o(NenchoRules::HOUSEHOLD_RELATIONS),
            'spouseStatus' => $o(NenchoRules::SPOUSE_STATUS),
            'spouseKinds' => $o(NenchoRules::SPOUSE_KINDS),
            'dependentKinds' => $o(NenchoRules::DEPENDENT_KINDS),
            'dependentRelations' => $o(NenchoRules::DEPENDENT_RELATIONS),
            'otherEarnerRelations' => $o(NenchoRules::OTHER_EARNER_RELATIONS),
            'elderlyClasses' => $o(NenchoRules::ELDERLY_CLASSES),
            'specificClasses' => $o(NenchoRules::SPECIFIC_CLASSES),
            'disabilityClasses' => $o(NenchoRules::DISABILITY_CLASSES),
            'singleParentClasses' => $o(NenchoRules::SINGLE_PARENT_CLASSES),
            'nonresidentReasons' => $o(NenchoRules::NONRESIDENT_REASONS),
            'remittanceUnits' => $o(NenchoRules::REMITTANCE_UNITS),
            'changeReasons' => array_map($o, NenchoRules::CHANGE_REASONS),
            'newOld' => $o(NenchoRules::NEW_OLD),
            'quakeClasses' => $o(NenchoRules::QUAKE_CLASSES),
            'insuranceKinds' => InsuranceItem::KINDS,
        ];
    }
}
