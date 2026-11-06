<?php

namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Portfolio\PortfolioPermission;
use App\Models\User;
use App\Services\Portfolio\MemberList;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * ポートフォリオの利用メンバー設定（SuperAdmin のみ）。
 * 画面と保存の形は、申請者管理（Admin\ApplicantController / Pages/Admin/Applicants/Show.vue）と同じ:
 * ユーザーごとのスイッチを動かして、まとめて「保存する」。並び順も同じ（MemberList）。
 * 対象はサン・ブレーン（company_type = 'sunbrain'）所属のユーザー。SuperAdmin・Admin は常時利用できるため一覧に出さない。
 */
class PortfolioPermissionController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeManage($request);

        $users = MemberList::sort(
            $this->targetUsers()->with(['department:id,name', 'positionTitle:id,name,sort_order', 'assignment:id,name'])->get()
        );
        $enabledMap = PortfolioPermission::whereIn('user_id', $users->pluck('id'))->pluck('enabled', 'user_id');

        return Inertia::render('Portfolio/Permissions', [
            'routePrefix' => Str::before($request->route()->getName(), '.portfolio.'),
            'users' => $users->map(fn (User $u) => MemberList::row($u) + [
                'assignment_name' => $u->assignment?->name,
                'enabled' => (bool) ($enabledMap[$u->id] ?? false),
            ])->values(),
            // 担当（制作・営業など）。部署ごとに同名の担当があるため名前で一意にして絞り込みに使う
            'assignmentNames' => $users->map(fn (User $u) => $u->assignment?->name)->filter()->unique()->sort()->values(),
            'departments' => Department::whereIn('id', $users->pluck('department_id')->filter()->unique())
                ->orderBy('sort_order')->orderBy('id')->get(['id', 'name']),
        ]);
    }

    /**
     * スイッチを、まとめて保存する。リクエスト: items = [{ user_id, enabled }, ...]（対象外のユーザーは受け付けない）。
     * 変更のない行は触らない（許可した人・日時を残すため）。
     */
    public function update(Request $request)
    {
        $this->authorizeManage($request);

        $items = $request->validate([
            'items' => ['required', 'array'],
            'items.*.user_id' => ['required', 'integer'],
            'items.*.enabled' => ['required', 'boolean'],
        ])['items'];

        $allowed = array_flip($this->targetUsers()->pluck('id')->map(fn ($v) => (int) $v)->all());
        $current = PortfolioPermission::pluck('enabled', 'user_id');
        $changed = 0;

        foreach ($items as $item) {
            $userId = (int) $item['user_id'];
            if (! isset($allowed[$userId])) {
                continue; // 対象外（サンエー印刷・Admin・SuperAdmin など）は受け付けない
            }
            $enabled = (bool) $item['enabled'];
            if (isset($current[$userId]) && (bool) $current[$userId] === $enabled) {
                continue; // 変更のない行
            }
            if (! isset($current[$userId]) && ! $enabled) {
                continue; // 行が無く OFF のまま = 変更なし（無駄な行を作らない）
            }
            PortfolioPermission::updateOrCreate(
                ['user_id' => $userId],
                ['enabled' => $enabled, 'granted_by' => $request->user()->id, 'granted_at' => $enabled ? now() : null],
            );
            $changed++;
        }

        return response()->json(['ok' => true, 'changed' => $changed]);
    }

    /** SB 所属の SuperAdmin・Admin 以外（この2つは利用許可なしで常時使える。ゴースト除外は User のグローバルスコープ） */
    private function targetUsers()
    {
        return User::whereNotIn('user_role', ['superadmin', 'admin'])
            ->whereHas('company', fn ($q) => $q->where('company_type', 'sunbrain'));
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
    }
}
