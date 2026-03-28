<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ChecksAdminPermission;
use Illuminate\Http\Request;
use App\Models\Unit;
use App\Models\UnitMember;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class UnitController extends Controller
{
    use ChecksAdminPermission;

    public function create()
    {
        $this->requireAdminPermission('team_management');
        $user = Auth::user();

        // SuperAdmin can choose any company; regular admin only their own company
        if ($user && $user->user_role === 'superadmin') {
            $companies = \App\Models\Company::active()->get(['id', 'name']);
        } else {
            // if admin doesn't have company_id, return empty collection
            $companies = \App\Models\Company::where('id', $user->company_id ?? 0)->get(['id', 'name']);
        }

        // departments filtered by available companies
        $companyIds = $companies->pluck('id')->all();
        $departments = \App\Models\Department::active()->whereIn('company_id', $companyIds)->get(['id', 'name', 'company_id']);
        // all users within the allowed companies (provide department info and user_role)
        $users = \App\Models\User::select(['id', 'name', 'user_role', 'department_id', 'company_id'])
            ->whereIn('company_id', $companyIds)
            ->get();

        // leaders (for leader/sub-leader select) - allow superadmin, admin, leader
        $leaders = \App\Models\User::select(['id', 'name', 'user_role'])
            ->whereIn('user_role', ['superadmin', 'admin', 'leader'])
            ->whereIn('company_id', $companyIds)
            ->get();

        return Inertia::render('Admin/Teams/Create', [
            'companies' => $companies,
            'departments' => $departments,
            'users' => $users,
            'leaders' => $leaders,
        ]);
    }

    public function store(Request $request)
    {
        $this->requireAdminPermission('team_management');
        $validated = $request->validate([
            'company_id'      => 'nullable|exists:companies,id',
            'department_id'   => 'nullable|exists:departments,id',
            'name'            => 'required|string|max:255',
            'description'     => 'nullable|string',
            'leader_id'       => 'nullable|exists:users,id',
            'sub_leader_ids'  => 'array',
            'sub_leader_ids.*'=> 'exists:users,id',
            'member_ids'      => 'array',
            'member_ids.*'    => 'exists:users,id',
        ]);
        // server-side ownership and consistency checks
        $user = Auth::user();

        // If not superadmin, enforce company is user's company (or null)
        if ($user && $user->user_role !== 'superadmin') {
            $inputCompanyId = $validated['company_id'] ?? null;
            if ($inputCompanyId && $inputCompanyId != $user->company_id) {
                abort(403, '指定された会社を選択する権限がありません');
            }
        }

        // If department_id provided, ensure it belongs to the provided company (or user's company)
        if (!empty($validated['department_id'])) {
            $dept = \App\Models\Department::find($validated['department_id']);
            if (!$dept) {
                abort(422, '指定された部署が見つかりません');
            }
            $expectedCompanyId = $validated['company_id'] ?? ($user->company_id ?? null);
            if ($dept->company_id != $expectedCompanyId) {
                abort(422, '部署は選択された会社に属していません');
            }
        }

        DB::transaction(function () use ($validated, $user) {
            // create unit
            $unit = Unit::create([
                'company_id' => $validated['company_id'] ?? null,
                'department_id' => $validated['department_id'] ?? null,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'leader_id' => $validated['leader_id'] ?? null,
            ]);

            // create team record
            $team = Team::create([
                'user_id' => $validated['leader_id'] ?? ($user->id ?? null),
                'company_id' => $unit->company_id,
                'department_id' => $unit->department_id,
                'name' => $unit->name,
                // copy description from unit to team so it's not null
                'description' => $unit->description,
                // units are not personal teams
                'personal_team' => false,
                'team_type' => 'unit',
                // persist leader on team for later reference
                'leader_id' => $validated['leader_id'] ?? null,
            ]);

            // ensure leader is attached as owner in pivot (if provided)
            if (!empty($validated['leader_id'])) {
                DB::table('team_user')->insertOrIgnore([
                    'team_id' => $team->id,
                    'user_id' => $validated['leader_id'],
                    'role' => 'owner',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // サブリーダーを登録
            foreach ($validated['sub_leader_ids'] ?? [] as $subId) {
                DB::table('team_sub_leaders')->insertOrIgnore([
                    'team_id'    => $team->id,
                    'user_id'    => $subId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // add team members
            $memberIds = $validated['member_ids'] ?? [];
            foreach ($memberIds as $uid) {
                // create unit_member (allow duplicates at DB level? unique constraint exists, so ignore duplicates)
                UnitMember::firstOrCreate([
                    'unit_id' => $unit->id,
                    'user_id' => $uid,
                ]);

                // add to team members table (assumes TeamMember model exists)
                DB::table('team_user')->insertOrIgnore([
                    'team_id' => $team->id,
                    'user_id' => $uid,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return redirect()->route('admin.teams.index')->with('success', 'ユニットチームを作成しました');
    }
}
