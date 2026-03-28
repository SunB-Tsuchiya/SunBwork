<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaderPermission;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LeaderPermissionController extends Controller
{
    /** Leader ユーザー一覧と権限を表示 */
    public function index()
    {
        $currentUser = Auth::user();

        if ($currentUser->isAdmin() || $currentUser->isSuperAdmin()) {
            $query = User::where('user_role', 'leader')
                ->with('leaderPermission')
                ->orderBy('name');

            if (! $currentUser->isSuperAdmin()) {
                $query->where('company_id', $currentUser->company_id);
            }

            $leaders = $this->formatLeaders($query->get());
            $prefix  = $this->routePrefix($currentUser);

            return Inertia::render('Admin/LeaderPermissions/Index', [
                'leaders'    => $leaders,
                'indexRoute' => "{$prefix}.leader_permissions.index",
                'editRoute'  => "{$prefix}.leader_permissions.edit",
            ]);
        }

        if ($currentUser->isLeader()) {
            $ids     = $this->getManageableLeaderIds($currentUser);
            $leaders = $this->formatLeaders(
                User::whereIn('id', $ids)->with('leaderPermission')->orderBy('name')->get()
            );

            return Inertia::render('Admin/LeaderPermissions/Index', [
                'leaders'    => $leaders,
                'indexRoute' => 'leader.leader_permissions.index',
                'editRoute'  => 'leader.leader_permissions.edit',
            ]);
        }

        abort(403);
    }

    /** 特定 Leader の権限編集フォーム */
    public function edit(User $leaderuser)
    {
        $currentUser = Auth::user();
        $this->authorizeAccess($currentUser, $leaderuser);

        $perm        = LeaderPermission::firstOrNew(['user_id' => $leaderuser->id]);
        $prefix      = $this->routePrefix($currentUser);
        $updateRoute = "{$prefix}.leader_permissions.update";
        $indexRoute  = "{$prefix}.leader_permissions.index";

        return Inertia::render('Admin/LeaderPermissions/Edit', [
            'leaderUser' => [
                'id'    => $leaderuser->id,
                'name'  => $leaderuser->name,
                'email' => $leaderuser->email,
            ],
            'permissions' => [
                'client_management'      => $perm->client_management      ?? true,
                'diary_management'       => $perm->diary_management       ?? true,
                'workload_analysis'      => $perm->workload_analysis       ?? true,
                'workload_setting'       => $perm->workload_setting        ?? true,
                'work_record_management' => $perm->work_record_management  ?? true,
            ],
            'updateRoute' => $updateRoute,
            'indexRoute'  => $indexRoute,
        ]);
    }

    /** 権限を保存 */
    public function update(Request $request, User $leaderuser)
    {
        $currentUser = Auth::user();
        $this->authorizeAccess($currentUser, $leaderuser);

        $data = $request->validate([
            'client_management'      => 'boolean',
            'diary_management'       => 'boolean',
            'workload_analysis'      => 'boolean',
            'workload_setting'       => 'boolean',
            'work_record_management' => 'boolean',
        ]);

        LeaderPermission::updateOrCreate(['user_id' => $leaderuser->id], $data);

        $prefix = $this->routePrefix($currentUser);

        return redirect()->route("{$prefix}.leader_permissions.index")
            ->with('success', '権限設定を保存しました');
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    /** ルートプレフィックスを返す */
    private function routePrefix(User $user): string
    {
        if ($user->isSuperAdmin() || $user->isAdmin()) return 'admin';
        return 'leader';
    }

    /** 権限フォーマット */
    private function formatLeaders($users): \Illuminate\Support\Collection
    {
        return $users->map(function ($user) {
            $perm = $user->leaderPermission;
            return [
                'id'                     => $user->id,
                'name'                   => $user->name,
                'email'                  => $user->email,
                'client_management'      => $perm?->client_management      ?? true,
                'diary_management'       => $perm?->diary_management       ?? true,
                'workload_analysis'      => $perm?->workload_analysis       ?? true,
                'workload_setting'       => $perm?->workload_setting        ?? true,
                'work_record_management' => $perm?->work_record_management  ?? true,
            ];
        });
    }

    /**
     * リーダーユーザーが管理できる対象の user_id 配列を返す
     *
     * - 部署リーダー（department チームの leader_id）:
     *     自部署の全ユニットチームの leader_id + sub-leaders
     * - ユニットチームリーダー（unit チームの leader_id）:
     *     自チームの sub-leaders
     * 自分自身は除外する
     */
    private function getManageableLeaderIds(User $user): array
    {
        $ids = collect();

        // 部署リーダーとして管理するスコープ
        $deptTeams = Team::where('team_type', 'department')
            ->where('leader_id', $user->id)
            ->get(['id', 'department_id']);

        foreach ($deptTeams as $deptTeam) {
            $unitTeams = Team::where('team_type', 'unit')
                ->where('department_id', $deptTeam->department_id)
                ->get(['id', 'leader_id']);

            foreach ($unitTeams as $unitTeam) {
                if ($unitTeam->leader_id) {
                    $ids->push($unitTeam->leader_id);
                }
                DB::table('team_sub_leaders')
                    ->where('team_id', $unitTeam->id)
                    ->pluck('user_id')
                    ->each(fn ($id) => $ids->push($id));
            }
        }

        // ユニットチームリーダーとして管理するスコープ（自分が leader_id のチームのサブリーダー）
        $ownUnitTeams = Team::where('team_type', 'unit')
            ->where('leader_id', $user->id)
            ->get(['id']);

        foreach ($ownUnitTeams as $unitTeam) {
            DB::table('team_sub_leaders')
                ->where('team_id', $unitTeam->id)
                ->pluck('user_id')
                ->each(fn ($id) => $ids->push($id));
        }

        return $ids->unique()
            ->reject(fn ($id) => $id == $user->id)
            ->filter()
            ->values()
            ->toArray();
    }

    /** アクセス可否チェック */
    private function authorizeAccess(User $currentUser, User $targetUser): void
    {
        if ($targetUser->user_role !== 'leader') {
            abort(404);
        }

        if ($currentUser->isSuperAdmin()) return;

        if ($currentUser->isAdmin()) {
            if ($targetUser->company_id !== $currentUser->company_id) abort(403);
            return;
        }

        if ($currentUser->isLeader()) {
            $manageableIds = $this->getManageableLeaderIds($currentUser);
            if (! in_array($targetUser->id, $manageableIds)) abort(403);
            return;
        }

        abort(403);
    }
}
