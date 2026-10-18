<?php

namespace App\Http\Controllers\SunA\GeneralAffairs\Nencho;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\SunA\GeneralAffairs\Nencho\InsuranceItem;
use App\Models\SunA\GeneralAffairs\Nencho\Submission;
use App\Models\SunA\GeneralAffairs\Nencho\SubmissionEvent;
use App\Services\SunA\GeneralAffairs\Nencho\EditLockService;
use App\Services\SunA\GeneralAffairs\Nencho\ProfileSyncService;
use App\Services\SunA\GeneralAffairs\Nencho\SubmissionService;
use App\Support\SunA\GeneralAffairs\Nencho\NenchoRules;
use App\Support\SunA\GeneralAffairs\Nencho\ProfileDiff;
use App\Support\SunA\GeneralAffairs\StaffAccess;
use App\Support\SunA\GeneralAffairs\Nencho\StepRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * 総務: 提出された年末調整の内容確認と差戻し。両社の提出物を扱う（権限 suna_staff:manage_nencho）。
 */
class SubmissionController extends Controller
{
    public function show(Submission $submission, EditLockService $locks)
    {
        $submission->load(['campaign', 'spouse', 'dependents', 'insuranceItems', 'events']);
        $spouse = $submission->spouse;
        $items = [];
        foreach (array_keys(InsuranceItem::KINDS) as $kind) {
            $items[$kind] = $submission->insuranceItems->where('kind', $kind)->values()->map->toArray()->all();
        }
        $c = $submission->campaign;
        $o = fn (array $m) => NenchoRules::options($m);

        return Inertia::render('SunA/GeneralAffairs/Nencho/Submissions/Show', [
            'campaign' => [
                'id' => $c->id, 'title' => $c->title, 'fiscal_year' => $c->fiscal_year, 'company_id' => $c->company_id,
                'company_name' => Company::whereKey($c->company_id)->value('name'),
            ],
            'submission' => $submission->makeHidden(['spouse', 'dependents', 'insuranceItems', 'campaign', 'events']),
            'spouse' => $spouse ? $spouse->toArray() + ['masked_my_number' => $spouse->maskedMyNumber()] : null,
            'dependents' => $submission->dependents->map(fn ($d) => $d->toArray() + ['masked_my_number' => $d->maskedMyNumber()])->values(),
            'insuranceItems' => $items,
            'events' => $submission->events->map(fn ($e) => [
                'id' => $e->id, 'action' => $e->action, 'actor_name' => $e->actor_name, 'comment' => $e->comment,
                'steps' => $e->meta['steps'] ?? [], 'confirmation' => $e->meta['confirmation'] ?? null, 'created_at' => $e->created_at?->timezone('Asia/Tokyo')->format('Y-m-d H:i'),
            ]),
            'eventActions' => SubmissionEvent::ACTIONS,
            'statuses' => Submission::STATUSES,
            'steps' => collect(StepRules::STEPS)->filter(fn ($v, $k) => $k <= 6)->map(fn ($v, $k) => ['no' => $k] + $v)->values(),
            'canReturn' => $submission->status === 'submitted',
            // 社員情報との照合（社員情報の値を見せるので「社員情報を見る」権限が必要。反映には「社員情報を編集する」権限）
            'profileDiff' => $submission->status === 'submitted' && StaffAccess::for(Auth::user())->viewEmployees ? ProfileDiff::compute($submission) : null,
            'profileDiffStatus' => $submission->status === 'submitted' ? ProfileDiff::compute($submission)['status'] : null,
            'canApplySync' => $submission->status === 'submitted' && StaffAccess::for(Auth::user())->editEmployees,
            'canProxyEdit' => in_array($submission->status, ['draft', 'returned'], true) && $submission->user_id !== null,
            // 総務だけが見られる情報（従業員の画面には渡さない）
            'editLock' => $locks->holder($submission),   // いま誰かが編集中ならその人（なければ null）
            'adminMemo' => $submission->admin_memo,
            'proxyConfirmation' => $submission->proxy_confirmation,
            'isSelf' => (int) $submission->user_id === (int) Auth::id(),
            'options' => [
                'householdRelations' => $o(NenchoRules::HOUSEHOLD_RELATIONS), 'spouseStatus' => $o(NenchoRules::SPOUSE_STATUS),
                'spouseKinds' => $o(NenchoRules::SPOUSE_KINDS), 'dependentKinds' => $o(NenchoRules::DEPENDENT_KINDS),
                'dependentRelations' => $o(NenchoRules::DEPENDENT_RELATIONS), 'disabilityClasses' => $o(NenchoRules::DISABILITY_CLASSES),
                'singleParentClasses' => $o(NenchoRules::SINGLE_PARENT_CLASSES), 'insuranceKinds' => InsuranceItem::KINDS,
            ],
        ]);
    }

    /**
     * 申告と社員情報の差異への対応: グループごとに「申告に合わせて社員情報を更新」か「社員情報のまま」を選ぶ。
     * 社員情報を変更するので「社員情報を編集する」権限が必要。
     */
    public function profileSync(Request $request, Submission $submission, ProfileSyncService $sync, SubmissionService $service)
    {
        abort_unless(StaffAccess::for(Auth::user())->editEmployees, 403, '社員情報を編集する権限がありません。');
        abort_unless($submission->status === 'submitted' && $submission->user_id !== null, 422, '提出済みの申告だけ反映できます。');
        $diff = ProfileDiff::compute($submission);
        $differing = collect($diff['groups'])->filter(fn ($g) => $g['differs'])->keys()->all();
        $data = $request->validate([
            'decisions' => ['required', 'array'],
            'decisions.*' => ['in:update,keep'],
        ], ['decisions.required' => 'どちらが正しいか、選んでください。'], ['decisions' => '選択']);
        // 差異のあるグループすべてについて選ぶ（差異のないグループは対象外）
        $decisions = collect($data['decisions'])->only($differing)->all();
        if (count($decisions) !== count($differing)) {
            return back()->withErrors(['decisions' => '差異のある項目すべてについて、どちらが正しいか選んでください。']);
        }
        $sync->apply($submission, Auth::user(), $decisions, $service);

        return back()->with('success', in_array('update', $decisions, true) ? '選んだ内容で社員情報を更新しました。' : '社員情報はそのままにして、確認済みにしました。');
    }

    /** 管理者メモ（総務だけが見られる。従業員には表示しない） */
    public function memo(Request $request, Submission $submission)
    {
        $data = $request->validate(['admin_memo' => ['nullable', 'string', 'max:2000']], [], ['admin_memo' => '管理者メモ']);
        $submission->update(['admin_memo' => $data['admin_memo'] ?: null]);

        return back()->with('success', '管理者メモを保存しました。');
    }

    /** 差戻し: コメントと、直してほしい工程を付けて従業員に返す（締切後でも、その従業員だけ修正できる） */
    public function return(Request $request, Submission $submission, SubmissionService $service)
    {
        abort_unless($submission->status === 'submitted', 422, '提出済みの申告だけ差し戻せます。');
        $data = $request->validate([
            'comment' => ['required', 'string', 'max:1000'],
            'steps' => ['nullable', 'array'],
            'steps.*' => ['integer', 'between:1,6'],
        ], ['comment.required' => '差戻しのコメントを入力してください。'], ['comment' => 'コメント']);

        $service->returnToEmployee($submission, Auth::user(), $data['comment'], $data['steps'] ?? []);

        // 自分の提出物を自分で差し戻した場合（総務と従業員を兼ねる人）は、そのまま修正画面へ
        if ((int) $submission->user_id === (int) Auth::id()) {
            return to_route('user.general_affairs.nencho.show', ['submission' => $submission->id, 'step' => $submission->fresh()->current_step])
                ->with('success', '差し戻しました。内容を修正して、もう一度提出してください。');
        }

        return to_route('clerk.general_affairs.nencho.submissions.show', ['submission' => $submission->id])->with('success', '差し戻しました。');
    }
}
