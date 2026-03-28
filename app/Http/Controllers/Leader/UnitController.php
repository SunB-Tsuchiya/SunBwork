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
        $user = Auth::user();

        $companies   = \App\Models\Company::where('id', $user->company_id ?? 0)->get(['id', 'name']);
        $companyIds  = $companies->pluck('id')->all();
        $departments = \App\Models\Department::active()->whereIn('company_id', $companyIds)->get(['id', 'name', 'company_id']);
        $users       = \App\Models\User::select(['id', 'name', 'user_role', 'department_id', 'company_id'])
            ->whereIn('company_id', $companyIds)->get();
        $leaders     = \App\Models\User::select(['id', 'name', 'user_role'])
            ->whereIn('user_role', ['superadmin', 'admin', 'leader'])
            ->whereIn('company_id', $companyIds)->get();

        return Inertia::render('Leader/Teams/Create', [
            'companies'   => $companies,
            'departments' => $departments,
            'users'       => $users,
            'leaders'     => $leaders,
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
            'sub_leader_ids'   => 'array',
            'sub_leader_ids.*' => 'exists:users,id',
            'member_ids'       => 'array',
            'member_ids.*'     => 'exists:users,id',
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
                'user_id'       => $validated['leader_id'] ?? ($user->id ?? null),
                'company_id'    => $unit->company_id,
                'department_id' => $unit->department_id,
                'name'          => $unit->name,
                'description'   => $unit->description,
                'personal_team' => false,
                'team_type'     => 'unit',
                'leader_id'     => $validated['leader_id'] ?? null,
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

            foreach ($validated['sub_leader_ids'] ?? [] as $subId) {
                DB::table('team_sub_leaders')->insertOrIgnore([
                    'team_id'    => $team->id,
                    'user_id'    => $subId,
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
