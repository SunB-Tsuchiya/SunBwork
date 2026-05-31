<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ChecksAdminPermission;
use App\Http\Controllers\Concerns\ResolvesContextCompany;
use App\Models\Department;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    use ChecksAdminPermission, ResolvesContextCompany;

    public function index()
    {
        $this->requireAdminPermission('team_management');
        $user      = Auth::user();
        $companyId = $this->contextCompanyId() ?? $user->company_id;

        $departments = Department::where('company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'sort_order', 'active']);

        $teamMap = Team::where('company_id', $companyId)
            ->where('team_type', 'department')
            ->pluck('id', 'department_id');

        $departments = $departments->map(function ($dept) use ($teamMap) {
            $dept->team_id = $teamMap[$dept->id] ?? null;
            return $dept;
        });

        return Inertia::render('Admin/Departments/Index', [
            'departments' => $departments,
        ]);
    }

    public function store(Request $request)
    {
        $this->requireAdminPermission('team_management');
        $user      = Auth::user();
        $companyId = $this->contextCompanyId() ?? $user->company_id;

        if (!$companyId) {
            abort(422, '会社が特定できません');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($validated, $companyId, $user) {
            $department = Department::create([
                'company_id' => $companyId,
                'name'       => $validated['name'],
                'code'       => 'DEPT_' . substr(uniqid(), -6),
                'active'     => true,
            ]);

            Team::firstOrCreate(
                [
                    'company_id'    => $companyId,
                    'department_id' => $department->id,
                    'team_type'     => 'department',
                ],
                [
                    'name'    => $validated['name'],
                    'user_id' => $user->id,
                ]
            );
        });

        return redirect()->route('admin.departments.index')->with('success', '部署を作成しました');
    }

    public function createTeam(Department $department)
    {
        $this->requireAdminPermission('team_management');
        $user      = Auth::user();
        $companyId = $this->contextCompanyId() ?? $user->company_id;

        if ($department->company_id !== $companyId) {
            abort(403, 'この部署のチームを作成する権限がありません');
        }

        Team::firstOrCreate(
            [
                'company_id'    => $department->company_id,
                'department_id' => $department->id,
                'team_type'     => 'department',
            ],
            [
                'name'    => $department->name,
                'user_id' => $user->id,
            ]
        );

        return redirect()->route('admin.departments.index')->with('success', "「{$department->name}」のチームを作成しました");
    }

    public function destroy(Department $department)
    {
        $this->requireAdminPermission('team_management');
        $user      = Auth::user();
        $companyId = $this->contextCompanyId() ?? $user->company_id;

        if ($department->company_id !== $companyId) {
            abort(403, 'この部署を削除する権限がありません');
        }

        DB::transaction(function () use ($department, $companyId) {
            $team = Team::where('company_id', $companyId)
                ->where('department_id', $department->id)
                ->where('team_type', 'department')
                ->first();

            if ($team) {
                try {
                    $team->users()->detach();
                } catch (\Throwable $e) {
                    DB::table('team_user')->where('team_id', $team->id)->delete();
                }

                DB::table('users')
                    ->where('current_team_id', $team->id)
                    ->update(['current_team_id' => null, 'updated_at' => now()]);

                $team->delete();
            }

            $department->delete();
        });

        return redirect()->route('admin.departments.index')->with('success', '部署を削除しました');
    }
}
