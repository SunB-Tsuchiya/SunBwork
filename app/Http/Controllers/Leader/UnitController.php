<?php

namespace App\Http\Controllers\Leader;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\Unit;
use App\Models\UnitMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class UnitController extends Controller
{
    public function create()
    {
        $user      = Auth::user();
        $companyId = $user->company_id;
        $deptId    = $user->department_id;

        // 全社ユーザー（メンバー選択用・部署横断）
        $users = \App\Models\User::select(['id', 'name', 'user_role', 'department_id', 'company_id', 'assignment_id'])
            ->where('company_id', $companyId)
            ->ordered()
            ->get();

        // リーダー候補: 同部署の leader/coordinator/clerk + 全社 admin/superadmin
        $leaders = \App\Models\User::select(['id', 'name', 'user_role'])
            ->where('company_id', $companyId)
            ->where(function ($q) use ($deptId) {
                $q->whereIn('user_role', ['admin', 'superadmin'])
                  ->orWhere(function ($q2) use ($deptId) {
                      $q2->whereIn('user_role', ['leader', 'coordinator', 'clerk'])
                         ->where('department_id', $deptId);
                  });
            })
            ->ordered()
            ->get();

        $departments = \App\Models\Department::where('company_id', $companyId)
            ->get(['id', 'name', 'company_id']);

        $assignments = \App\Models\Assignment::where('department_id', $deptId)
            ->orWhereNull('department_id')
            ->get(['id', 'name', 'department_id']);

        return Inertia::render('Leader/Teams/Create', [
            'users'              => $users,
            'leaders'            => $leaders,
            'departments'        => $departments,
            'assignments'        => $assignments,
            'auth_company_id'    => $companyId,
            'auth_department_id' => $deptId,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'company_id'       => 'nullable|exists:companies,id',
            'department_id'    => 'nullable|exists:departments,id',
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string',
            'leader_id'        => 'nullable|exists:users,id',
            'member_ids'       => 'array',
            'member_ids.*'     => 'exists:users,id',
            'can_read_diary'   => 'boolean',
        ]);

        // Enforce company scope
        $inputCompanyId = $validated['company_id'] ?? null;
        if ($inputCompanyId && $inputCompanyId != $user->company_id) {
            abort(403, '指定された会社を選択する権限がありません');
        }

        DB::transaction(function () use ($validated, $user) {
            $unit = Unit::create([
                'company_id'    => $validated['company_id'] ?? null,
                'department_id' => $validated['department_id'] ?? null,
                'name'          => $validated['name'],
                'description'   => $validated['description'] ?? null,
                'leader_id'     => $validated['leader_id'] ?? null,
            ]);

            $team = Team::create([
                'user_id'        => $validated['leader_id'] ?? ($user->id ?? null),
                'company_id'     => $unit->company_id,
                'department_id'  => $unit->department_id,
                'name'           => $unit->name,
                'description'    => $unit->description,
                'personal_team'  => false,
                'team_type'      => 'unit',
                'leader_id'      => $validated['leader_id'] ?? null,
                'can_read_diary' => $validated['can_read_diary'] ?? false,
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
                UnitMember::firstOrCreate(['unit_id' => $unit->id, 'user_id' => $uid]);
                DB::table('team_user')->insertOrIgnore([
                    'team_id'    => $team->id,
                    'user_id'    => $uid,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return redirect()->route('leader.teams.index')->with('success', 'ユニットチームを作成しました');
    }
}
