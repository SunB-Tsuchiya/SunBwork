<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Team;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TeamController extends Controller
{
    public function index()
    {
        // チーム一覧をcompany, departmentリレーション付きで取得
        $teams = Team::with(['company', 'department'])->get();
        return Inertia::render('Admin/Teams/Index', [
            'teams' => $teams,
        ]);
    }

    public function edit($id)
    {
        $team = Team::with(['company', 'department'])->findOrFail($id);
        $companies = \App\Models\Company::active()->get(['id', 'name']);
        $departments = \App\Models\Department::active()->get(['id', 'name', 'company_id']);
        $props = [
            'team' => $team,
            'companies' => $companies,
            'departments' => $departments,
        ];
        // Provide company users (so the front-end can build leader candidates filtered by role/department).
        // This ensures the Edit page always has access to company users, not only unit teams.
        $companyId = $team->company_id;
        if ($companyId) {
            $users = \App\Models\User::select(['id', 'name', 'user_role', 'department_id', 'company_id'])
                ->where('company_id', $companyId)
                ->get();

            // Include superadmin, admin and leader roles as potential leaders (frontend still filters by Admin/Leader)
            $leaders = \App\Models\User::select(['id', 'name', 'user_role'])
                ->whereIn('user_role', ['superadmin', 'admin', 'leader'])
                ->get();

            $props['users'] = $users;
            $props['leaders'] = $leaders;
        }

        // If this is a unit team, include the Unit model for the edit form
        if ($team->team_type === 'unit') {
            $unit = \App\Models\Unit::with(['members:id,name'])
                ->where('company_id', $team->company_id)
                ->where('department_id', $team->department_id)
                ->where('name', $team->name)
                ->first();

            $props['unit'] = $unit;
        }

        return Inertia::render('Admin/Teams/Edit', $props);
    }

    public function update(Request $request, $id)
    {
        $team = Team::findOrFail($id);
        // remember previous leader to allow demotion when changed
        $oldLeaderId = $team->leader_id;
        // If the team is a company or department team, only allow updating leader_id and description.
        if (in_array($team->team_type, ['company', 'department'])) {
            $validated = $request->validate([
                'leader_id' => 'nullable|exists:users,id',
                'description' => 'nullable|string|max:2000',
            ]);
        } else {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'company_id' => 'nullable|exists:companies,id',
                'department_id' => 'nullable|exists:departments,id',
                'leader_id' => 'nullable|exists:users,id',
                'description' => 'nullable|string|max:2000',
                'member_ids' => 'array',
                'member_ids.*' => 'exists:users,id',
            ]);
        }

        // 権限制御: superadmin でない場合は会社の変更等に制限をかける
        $user = Auth::user();
        if ($user && $user->user_role !== 'superadmin') {
            $inputCompanyId = $validated['company_id'] ?? null;
            if ($inputCompanyId && $inputCompanyId != ($user->company_id ?? null)) {
                abort(403, '指定された会社を選択する権限がありません');
            }
        }

        // leader_id が指定されている場合は、'superadmin' を許容しつつ、会社/部署が一致するか確認
        if (!empty($validated['leader_id'])) {
            // allow the 'superadmin' sentinel (frontend sends this string)
            if ($validated['leader_id'] === 'superadmin') {
                // nothing more to validate
            } else {
                // numeric id expected; validate existence
                $leader = \App\Models\User::find($validated['leader_id']);
                if (!$leader) {
                    abort(422, '指定されたリーダーが見つかりません');
                }

                if (strtolower($leader->user_role ?? '') !== 'superadmin') {
                    $targetCompanyId = $validated['company_id'] ?? $team->company_id;
                    if ($targetCompanyId && ($leader->company_id ?? null) != $targetCompanyId) {
                        abort(422, '指定されたリーダーは選択された会社に所属していません');
                    }
                    $targetDepartmentId = $validated['department_id'] ?? $team->department_id;
                    if ($targetDepartmentId && ($leader->department_id ?? null) != $targetDepartmentId) {
                        abort(422, '指定されたリーダーは選択された部署に所属していません');
                    }
                }
            }
        }

        // For company/department teams only update allowed fields (leader_id, description)
        if (in_array($team->team_type, ['company', 'department'])) {
            $team->leader_id = $validated['leader_id'] ?? null;
            $team->description = $validated['description'] ?? $team->description;
            $team->save();
        } else {
            $team->update($validated);
        }

        // Handle pivot role adjustments when leader changes.
        $leaderId = $validated['leader_id'] ?? null;
        try {
            // If new leader is 'superadmin' sentinel, we do not attach a pivot for them; instead
            // demote any existing owners to 'user'. If leader is numeric, attach/update pivot
            // to set role = 'owner', and demote any other owners.
            // Build preserve list. Honor TEAMS_PRESERVE_ADMINS env flag so behavior matches CreateTeam.
            $preserveOwnerIds = [];
            if (env('TEAMS_PRESERVE_ADMINS', true)) {
                $superAdmins = DB::table('users')->where('user_role', 'superadmin')->pluck('id')->toArray();
                $preserveOwnerIds = array_merge($preserveOwnerIds, $superAdmins);

                if (!empty($team->company_id)) {
                    $companyAdmins = DB::table('users')->where('user_role', 'admin')->where('company_id', $team->company_id)->pluck('id')->toArray();
                    $preserveOwnerIds = array_merge($preserveOwnerIds, $companyAdmins);
                }
            }

            // include current authenticated user (creator/admin performing update)
            if ($user) {
                $preserveOwnerIds[] = $user->id;
            }

            if ($leaderId === 'superadmin' || empty($leaderId)) {
                // demote owners except preserved ones
                DB::table('team_user')
                    ->where('team_id', $team->id)
                    ->where('role', 'owner')
                    ->whereNotIn('user_id', array_values(array_unique(array_filter($preserveOwnerIds))))
                    ->update(['role' => 'user', 'updated_at' => now()]);
            } else {
                $newLeaderInt = intval($leaderId);

                if (method_exists($team, 'users')) {
                    $team->users()->syncWithoutDetaching([
                        $newLeaderInt => ['role' => 'owner'],
                    ]);
                } else {
                    DB::table('team_user')->updateOrInsert(
                        ['team_id' => $team->id, 'user_id' => $newLeaderInt],
                        ['role' => 'owner', 'updated_at' => now(), 'created_at' => now()]
                    );
                }

                // demote any other owners to 'user', excluding the preserved ones and newly assigned owner
                $preserveOwnerIds[] = $newLeaderInt;
                DB::table('team_user')
                    ->where('team_id', $team->id)
                    ->where('role', 'owner')
                    ->whereNotIn('user_id', array_values(array_unique(array_filter($preserveOwnerIds))))
                    ->update(['role' => 'user', 'updated_at' => now()]);
            }

            // If there was a previous leader (numeric and different from new leader), also ensure
            // they are set to 'user' (covers cases where previous leader was owner but not caught above)
            if (!empty($oldLeaderId) && $oldLeaderId !== 'superadmin') {
                $oldInt = intval($oldLeaderId);
                if (empty($leaderId) || intval($leaderId) !== $oldInt) {
                    DB::table('team_user')
                        ->where('team_id', $team->id)
                        ->where('user_id', $oldInt)
                        ->update(['role' => 'user', 'updated_at' => now()]);
                }
            }

            // If this is a unit team, sync unit_members and ensure team_user membership reflects member_ids
            if ($team->team_type === 'unit') {
                $memberIds = $validated['member_ids'] ?? [];

                // find the corresponding Unit record (matches logic from edit/store)
                $unit = \App\Models\Unit::where('company_id', $team->company_id)
                    ->where('department_id', $team->department_id)
                    ->where('name', $team->name)
                    ->first();

                if ($unit) {
                    try {
                        if (method_exists($unit, 'members')) {
                            // sync will insert/delete unit_members rows accordingly
                            $unit->members()->sync($memberIds);
                        } else {
                            // fallback: manual sync
                            DB::table('unit_members')->where('unit_id', $unit->id)->delete();
                            foreach ($memberIds as $mid) {
                                DB::table('unit_members')->insertOrIgnore([
                                    'unit_id' => $unit->id,
                                    'user_id' => $mid,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            }
                        }

                        // ensure team_user has entries for all current members (do not change owner roles)
                        foreach ($memberIds as $mid) {
                            DB::table('team_user')->insertOrIgnore([
                                'team_id' => $team->id,
                                'user_id' => $mid,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        // remove non-owner team_user rows for users no longer in memberIds, but preserve preserved owners
                        DB::table('team_user')
                            ->where('team_id', $team->id)
                            ->where('role', '!=', 'owner')
                            ->whereNotIn('user_id', array_values(array_unique(array_filter($memberIds))))
                            ->whereNotIn('user_id', array_values(array_unique(array_filter($preserveOwnerIds))))
                            ->delete();
                    } catch (\Throwable $_exSync) {
                        logger()->warning('Failed to sync unit members/team_user after team update', ['team_id' => $team->id, 'error' => $_exSync->getMessage()]);
                    }
                }
            }
        } catch (\Throwable $_exAttach) {
            logger()->warning('Failed to adjust team_user pivot roles after leader change', ['team_id' => $team->id, 'leader_id' => $leaderId, 'error' => $_exAttach->getMessage()]);
        }

        return redirect()->route('admin.teams.index')->with('success', 'チーム情報を更新しました');
    }

    // Show a single team (resource route expects this)
    public function show($id)
    {
        // eager load company, department and related users with minimal necessary columns
        $team = Team::with(['company', 'department', 'users' => function ($q) {
            $q->select(['users.id', 'users.name', 'users.user_role', 'users.department_id', 'users.assignment_id', 'users.company_id']);
        }])->findOrFail($id);

        $assignments = \App\Models\Assignment::all();
        $departments = \App\Models\Department::all();
        $user = Auth::user();

        return Inertia::render('Admin/Teams/Show', [
            'team' => $team,
            'assignments' => $assignments,
            'departments' => $departments,
            'user' => $user,
        ]);
    }

    // Destroy a team
    public function destroy($id)
    {
        $team = Team::findOrFail($id);
        // Ensure pivot rows are removed even if model events are disabled
        try {
            if (method_exists($team, 'users')) {
                $team->users()->detach();
            } else {
                \Illuminate\Support\Facades\DB::table('team_user')->where('team_id', $team->id)->delete();
            }
        } catch (\Throwable $_exDetach) {
            logger()->warning('TeamController::destroy failed to detach users before delete', ['team_id' => $team->id, 'error' => $_exDetach->getMessage()]);
        }

        // Null out current_team_id for any users who had this team selected to avoid view errors
        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'current_team_id')) {
                \Illuminate\Support\Facades\DB::table('users')
                    ->where('current_team_id', $team->id)
                    ->update(['current_team_id' => null, 'updated_at' => now()]);
            }
        } catch (\Throwable $_exNull) {
            logger()->warning('TeamController::destroy failed to null current_team_id', ['team_id' => $team->id, 'error' => $_exNull->getMessage()]);
        }

        $team->delete();
        // If request is AJAX/XHR, return 204 No Content for client-side handling
        if (request()->ajax() || request()->wantsJson() || request()->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json(['message' => 'deleted'], 204);
        }

        return redirect()->route('admin.teams.index')->with('success', 'チームを削除しました');
    }
}
