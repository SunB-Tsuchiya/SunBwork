<?php

namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Portfolio\PortfolioPermission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * ポートフォリオの利用メンバー設定（試用期間中は SuperAdmin のみ）。
 * 対象はサン・ブレーン（company_type = 'sunbrain'）所属のユーザー全ロール。SuperAdmin・Admin は常時可のため一覧に出さない。
 */
class PortfolioPermissionController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeManage($request);

        $users = $this->targetUsers()
            ->with(['department:id,name', 'assignment:id,name'])
            ->orderBy('name')
            ->get();

        $enabledMap = PortfolioPermission::whereIn('user_id', $users->pluck('id'))
            ->pluck('enabled', 'user_id');

        $candidates = $users->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'assignment_name' => $u->assignment?->name,
            'employment_type' => $u->employment_type ?? 'regular',
            'department_id' => $u->department_id,
            'department_name' => $u->department?->name,
            'enabled' => (bool) ($enabledMap[$u->id] ?? false),
        ])->values();

        return Inertia::render('Portfolio/Permissions', [
            'routePrefix' => Str::before($request->route()->getName(), '.portfolio.'),
            // 担当（制作・営業など）。部署ごとに同名の担当があるため名前で一意にして絞り込みに使う
            'assignmentNames' => $users->map(fn (User $u) => $u->assignment?->name)->filter()->unique()->sort()->values(),
            'candidates' => $candidates,
            'departments' => Department::whereIn('id', $users->pluck('department_id')->filter()->unique())
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeManage($request);
        abort_unless($this->targetUsers()->whereKey($user->id)->exists(), 422, '対象ユーザーが不正です。');

        $data = $request->validate(['enabled' => 'required|boolean']);
        $this->setEnabled([$user->id], $data['enabled']);

        return back()->with('success', '利用設定を保存しました');
    }

    /** 絞り込み結果に対する一括オン/オフ */
    public function bulk(Request $request)
    {
        $this->authorizeManage($request);

        $data = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer',
            'enabled' => 'required|boolean',
        ]);

        $ids = $this->targetUsers()->whereIn('id', $data['user_ids'])->pluck('id')->all();
        $this->setEnabled($ids, $data['enabled']);

        return back()->with('success', count($ids) . '人の利用設定を保存しました');
    }

    /** SB 所属の SuperAdmin・Admin 以外（この2つは利用許可なしで常時使える。ゴースト除外は User のグローバルスコープ） */
    private function targetUsers()
    {
        return User::whereNotIn('user_role', ['superadmin', 'admin'])
            ->whereHas('company', fn ($q) => $q->where('company_type', 'sunbrain'));
    }

    private function setEnabled(array $userIds, bool $enabled): void
    {
        $grantor = Auth::id();
        foreach ($userIds as $id) {
            PortfolioPermission::updateOrCreate(
                ['user_id' => $id],
                [
                    'enabled' => $enabled,
                    'granted_by' => $grantor,
                    'granted_at' => $enabled ? now() : null,
                ]
            );
        }
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
    }
}
