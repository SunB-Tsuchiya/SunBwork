<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ChecksAdminPermission;
use App\Models\Company;
use App\Models\Department;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SpecialTeamController extends Controller
{
    use ChecksAdminPermission;

    public function index()
    {
        $this->requireAdminPermission('team_management');
        $user = Auth::user();

        $teams = Team::with(['company', 'users'])
            ->where('team_type', 'special')
            ->where('company_id', $user->company_id)
            ->orderBy('name')
            ->get()
            ->map(function ($t) {
                return [
                    'id'          => $t->id,
                    'name'        => $t->name,
                    'description' => $t->description,
                    'leader'      => $t->leader_id
                        ? User::find($t->leader_id, ['id', 'name'])
                        : null,
                    'member_count' => $t->users->count(),
                    'can_read_diary' => (bool) $t->can_read_diary,
                ];
            });

        return Inertia::render('Admin/SpecialTeams/Index', [
            'teams' => $teams,
        ]);
    }

    public function create()
    {
        $this->requireAdminPermission('team_management');
        $user      = Auth::user();
        $companyId = $user->company_id;

        // リーダー候補: SuperAdmin は全会社、Admin は自社
        $leaderCompanyIds = $user->isSuperAdmin()
            ? Company::active()->pluck('id')
            : [$companyId];

        $leaders = User::select(['id', 'name', 'user_role', 'company_id', 'department_id'])
            ->whereIn('company_id', $leaderCompanyIds)
            ->orderBy('name')
            ->get();

        // 全会社（メンバー選択用）
        $companies = Company::active()->ordered()->get(['id', 'name']);

        // 全部署
        $departments = Department::whereIn('company_id', $companies->pluck('id'))
            ->get(['id', 'name', 'company_id']);

        // 全ユーザー（会社・部署フィルター用）
        $users = User::select(['id', 'name', 'user_role', 'department_id', 'company_id'])
            ->whereIn('company_id', $companies->pluck('id'))
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/SpecialTeams/Create', [
            'leaders'     => $leaders,
            'companies'   => $companies,
            'departments' => $departments,
            'users'       => $users,
            'auth_company_id' => $companyId,
        ]);
    }

    public function store(Request $request)
    {
        $this->requireAdminPermission('team_management');
        $user = Auth::user();

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string|max:2000',
            'leader_id'    => 'nullable|exists:users,id',
            'member_ids'   => 'array',
            'member_ids.*' => 'exists:users,id',
        ]);

        DB::transaction(function () use ($validated, $user) {
            $team = Team::create([
                'user_id'        => $validated['leader_id'] ?? $user->id,
                'company_id'     => $user->company_id,
                'department_id'  => null,
                'name'           => $validated['name'],
                'description'    => $validated['description'] ?? null,
                'personal_team'  => false,
                'team_type'      => 'special',
                'leader_id'      => $validated['leader_id'] ?? null,
                'can_read_diary' => false,
            ]);

            if (!empty($validated['leader_id'])) {
                DB::table('team_user')->insertOrIgnore([
                    'team_id'    => $team->id,
                    'user_id'    => $validated['leader_id'],
                    'role'       => 'owner',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($validated['member_ids'] ?? [] as $uid) {
                DB::table('team_user')->insertOrIgnore([
                    'team_id'    => $team->id,
                    'user_id'    => $uid,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return redirect()->route('admin.special_teams.index')
            ->with('success', '特別チームを作成しました');
    }

    public function edit($id)
    {
        $this->requireAdminPermission('team_management');
        $user = Auth::user();

        $team = Team::where('team_type', 'special')
            ->where('company_id', $user->company_id)
            ->findOrFail($id);

        $currentMemberIds = DB::table('team_user')
            ->where('team_id', $team->id)
            ->where(function ($q) { $q->whereNull('role')->orWhere('role', '<>', 'owner'); })
            ->pluck('user_id')
            ->map(fn ($v) => (string) $v)
            ->values()
            ->toArray();

        // リーダー候補: SuperAdmin は全会社、Admin は自社
        $leaderCompanyIds = $user->isSuperAdmin()
            ? Company::active()->pluck('id')
            : [$user->company_id];

        $leaders = User::select(['id', 'name', 'user_role', 'company_id', 'department_id'])
            ->whereIn('company_id', $leaderCompanyIds)
            ->orderBy('name')
            ->get();

        $companies   = Company::active()->ordered()->get(['id', 'name']);
        $departments = Department::whereIn('company_id', $companies->pluck('id'))
            ->get(['id', 'name', 'company_id']);
        $users = User::select(['id', 'name', 'user_role', 'department_id', 'company_id'])
            ->whereIn('company_id', $companies->pluck('id'))
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/SpecialTeams/Edit', [
            'team'               => $team,
            'leaders'            => $leaders,
            'companies'          => $companies,
            'departments'        => $departments,
            'users'              => $users,
            'current_member_ids' => $currentMemberIds,
            'auth_company_id'    => $user->company_id,
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->requireAdminPermission('team_management');
        $user = Auth::user();

        $team = Team::where('team_type', 'special')
            ->where('company_id', $user->company_id)
            ->findOrFail($id);

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string|max:2000',
            'leader_id'    => 'nullable|exists:users,id',
            'member_ids'   => 'array',
            'member_ids.*' => 'exists:users,id',
        ]);

        $team->update([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'leader_id'   => $validated['leader_id'] ?? null,
        ]);

        // リーダー pivot 更新
        if (!empty($validated['leader_id'])) {
            DB::table('team_user')->updateOrInsert(
                ['team_id' => $team->id, 'user_id' => intval($validated['leader_id'])],
                ['role' => 'owner', 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // メンバー sync
        $memberIds = array_values(array_unique(array_map('intval', $validated['member_ids'] ?? [])));
        DB::table('team_user')
            ->where('team_id', $team->id)
            ->where(function ($q) { $q->whereNull('role')->orWhere('role', '<>', 'owner'); })
            ->delete();
        foreach ($memberIds as $mid) {
            DB::table('team_user')->insertOrIgnore([
                'team_id'    => $team->id,
                'user_id'    => $mid,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect()->route('admin.special_teams.index')
            ->with('success', '特別チームを更新しました');
    }

    public function destroy($id)
    {
        $this->requireAdminPermission('team_management');
        $user = Auth::user();

        $team = Team::where('team_type', 'special')
            ->where('company_id', $user->company_id)
            ->findOrFail($id);

        DB::table('team_user')->where('team_id', $team->id)->delete();
        DB::table('users')->where('current_team_id', $team->id)->update(['current_team_id' => null]);
        $team->delete();

        return redirect()->route('admin.special_teams.index')
            ->with('success', '特別チームを削除しました');
    }
}
