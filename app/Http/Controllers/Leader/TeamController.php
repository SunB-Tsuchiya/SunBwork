<?php

namespace App\Http\Controllers\Leader;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesContextCompany;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TeamController extends Controller
{
    use ResolvesContextCompany;

    /** ユニットチーム一覧 */
    public function index()
    {
        $user  = Auth::user();
        $query = Team::with(['company', 'department'])->where('team_type', 'unit');

        if ($user->isSuperAdmin()) {
            $companyId = $this->contextCompanyId();
            if ($companyId === null) {
                return Inertia::render('Leader/Teams/Index', [
                    'noCompanySelected' => true,
                    'teams'             => [],
                ]);
            }
            $query->where('company_id', $companyId);
        } else {
            $query->where('company_id', $user->company_id);

            // 部署リーダー判定（department チームの leader_id が自分）
            $deptTeam = Team::where('team_type', 'department')
                ->where('company_id', $user->company_id)
                ->where('leader_id', $user->id)
                ->first();

            if ($deptTeam) {
                // 部署リーダー: 自部署のユニットチームをすべて表示
                $query->where('department_id', $deptTeam->department_id);
            } else {
                // 通常リーダー: 自分が leader_id のチームのみ
                $query->where('leader_id', $user->id);
            }
        }

        return Inertia::render('Leader/Teams/Index', [
            'teams' => $query->get(),
        ]);
    }

    /** 編集フォーム */
    public function edit($id)
    {
        $team = Team::with(['company', 'department'])->findOrFail($id);
        $this->authorizeTeam($team);

        $companyId   = $team->company_id;
        $companies   = \App\Models\Company::active()->ordered()->get(['id', 'name']);
        $departments = \App\Models\Department::active()->get(['id', 'name', 'company_id']);

        $users = $companyId
            ? \App\Models\User::select(['id', 'name', 'user_role', 'department_id', 'company_id', 'assignment_id', 'is_ghost'])
                ->where('company_id', $companyId)->get()
            : collect();

        $leaders = $companyId
            ? \App\Models\User::select(['id', 'name', 'user_role'])
                ->whereIn('user_role', ['superadmin', 'admin', 'leader', 'coordinator', 'clerk'])
                ->where('company_id', $companyId)->get()
            : collect();

        $unit = \App\Models\Unit::with(['members:id,name'])
            ->where('company_id', $team->company_id)
            ->where('department_id', $team->department_id)
            ->where('name', $team->name)
            ->first();

        $assignments = \App\Models\Assignment::all(['id', 'name', 'department_id']);

        $currentMemberIds = DB::table('team_user')
            ->where('team_id', $team->id)
            ->where(function ($q) { $q->whereNull('role')->orWhere('role', '<>', 'owner'); })
            ->pluck('user_id')
            ->map(fn ($v) => (string) $v)
            ->values()
            ->toArray();

        return Inertia::render('Leader/Teams/Edit', [
            'team'               => $team,
            'companies'          => $companies,
            'departments'        => $departments,
            'users'              => $users,
            'leaders'            => $leaders,
            'unit'               => $unit,
            'assignments'        => $assignments,
            'current_member_ids' => $currentMemberIds,
        ]);
    }

    /** 更新 */
    public function update(Request $request, $id)
    {
        $team = Team::findOrFail($id);
        $this->authorizeTeam($team);

        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'company_id'       => 'nullable|exists:companies,id',
            'department_id'    => 'nullable|exists:departments,id',
            'leader_id'        => 'nullable|exists:users,id',
            'description'      => 'nullable|string|max:2000',
            'member_ids'       => 'array',
            'member_ids.*'     => 'exists:users,id',
            'can_read_diary'   => 'boolean',
        ]);

        $oldLeaderId = $team->leader_id;
        $team->update($validated);

        // リーダー pivot 更新
        $leaderId = $validated['leader_id'] ?? null;
        if ($leaderId && $leaderId !== 'superadmin') {
            DB::table('team_user')->updateOrInsert(
                ['team_id' => $team->id, 'user_id' => intval($leaderId)],
                ['role' => 'owner', 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // team_user sync
        $memberIds = array_map('strval', $validated['member_ids'] ?? []);
        DB::table('team_user')
            ->where('team_id', $team->id)
            ->where(function ($q) { $q->whereNull('role')->orWhere('role', '<>', 'owner'); })
            ->delete();
        foreach ($memberIds as $mid) {
            DB::table('team_user')->insertOrIgnore([
                'team_id' => $team->id, 'user_id' => $mid,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // unit_members sync（unit が存在する場合のみ）
        $unit = \App\Models\Unit::where('company_id', $team->company_id)
            ->where('department_id', $team->department_id)
            ->where('name', $team->name)
            ->first();
        if ($unit) {
            $unit->members()->sync($memberIds);
        }

        return redirect()->route('leader.teams.index')->with('success', 'チーム情報を更新しました');
    }

    /** 詳細表示 */
    public function show($id)
    {
        $team = Team::with(['company', 'department', 'users' => function ($q) {
            $q->select(['users.id', 'users.name', 'users.user_role', 'users.department_id', 'users.assignment_id', 'users.company_id']);
        }])->findOrFail($id);

        $this->authorizeTeam($team);

        return Inertia::render('Leader/Teams/Show', [
            'team'        => $team,
            'assignments' => \App\Models\Assignment::all(),
            'departments' => \App\Models\Department::all(),
        ]);
    }

    /** 削除 */
    public function destroy($id)
    {
        $team = Team::findOrFail($id);
        $this->authorizeTeam($team);

        try { $team->users()->detach(); } catch (\Throwable $_) {}

        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'current_team_id')) {
                DB::table('users')->where('current_team_id', $team->id)->update(['current_team_id' => null]);
            }
        } catch (\Throwable $_) {}

        $team->delete();

        if (request()->wantsJson()) {
            return response()->json(['message' => 'deleted'], 204);
        }
        return redirect()->route('leader.teams.index')->with('success', 'チームを削除しました');
    }

    private function authorizeTeam(Team $team): void
    {
        if ($team->team_type !== 'unit') abort(404);
        $user = Auth::user();
        if (! $user->isSuperAdmin() && $team->company_id !== $user->company_id) {
            abort(403);
        }
    }
}
