<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ChecksAdminPermission;
use App\Http\Controllers\Concerns\ResolvesContextCompany;
use App\Models\Department;
use App\Models\DepartmentFieldConfig;
use App\Models\Team;
use App\Models\WorkItemType;
use App\Models\Stage;
use App\Models\Size;
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
                    'name'          => $validated['name'],
                    'user_id'       => $user->id,
                    'personal_team' => false,
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
                'name'          => $department->name,
                'user_id'       => $user->id,
                'personal_team' => false,
            ]
        );

        return redirect()->route('admin.departments.index')->with('success', "「{$department->name}」のチームを作成しました");
    }

    public function fieldConfig(Department $department)
    {
        $this->requireAdminPermission('team_management');
        $user      = Auth::user();
        $companyId = $this->contextCompanyId() ?? $user->company_id;

        if ($department->company_id !== $companyId) {
            abort(403);
        }

        $configs = DepartmentFieldConfig::where('department_id', $department->id)
            ->get()
            ->keyBy('slot');

        return Inertia::render('Admin/Departments/FieldConfig', [
            'department' => $department->only('id', 'name'),
            'configs'    => $configs,
            'masters'    => $this->buildMasters($companyId),
        ]);
    }

    public function updateFieldConfig(Request $request, Department $department)
    {
        $this->requireAdminPermission('team_management');
        $user      = Auth::user();
        $companyId = $this->contextCompanyId() ?? $user->company_id;

        if ($department->company_id !== $companyId) {
            abort(403);
        }

        $request->validate([
            'slots'                    => 'required|array',
            'slots.*.slot'             => 'required|in:type,stage,size,amounts',
            'slots.*.label'            => 'nullable|string|max:100',
            'slots.*.enabled'          => 'boolean',
            'slots.*.allowed_item_ids'   => 'nullable|array',
            'slots.*.allowed_item_ids.*' => 'integer',
            'slots.*.source'             => 'nullable|string|max:50',
            'slots.*.source_group'       => 'nullable|string|max:100',
        ]);

        foreach ($request->input('slots') as $slotData) {
            DepartmentFieldConfig::updateOrCreate(
                ['department_id' => $department->id, 'slot' => $slotData['slot']],
                [
                    'label'            => $slotData['label'] ?? '',
                    'enabled'          => $slotData['enabled'] ?? true,
                    'source'           => $slotData['source'] ?: null,
                    'source_group'     => $slotData['source_group'] ?: null,
                    'allowed_item_ids' => empty($slotData['allowed_item_ids']) ? null : $slotData['allowed_item_ids'],
                ]
            );
        }

        return redirect()->route('admin.departments.index')
            ->with('success', "「{$department->name}」のフィールド設定を保存しました");
    }

    private function buildMasters(int $companyId): array
    {
        $types  = WorkItemType::where('company_id', $companyId)->orderBy('sort_order')->get(['id', 'name', 'group']);
        $stages = Stage::where(function ($q) use ($companyId) {
            $q->where('company_id', $companyId)->orWhereNull('company_id');
        })->orderBy('order_index')->get(['id', 'name', 'group']);
        $sizes  = Size::where(function ($q) use ($companyId) {
            $q->where('company_id', $companyId)->orWhereNull('company_id');
        })->orderBy('sort_order')->get(['id', 'name', 'group']);

        return compact('types', 'stages', 'sizes');
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
