<?php

namespace App\Http\Controllers\Leader;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ChecksLeaderPermission;
use App\Http\Controllers\Concerns\ResolvesContextCompany;
use App\Models\Department;
use App\Models\DepartmentFieldConfig;
use App\Models\WorkItemType;
use App\Models\Stage;
use App\Models\Size;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DepartmentFieldConfigController extends Controller
{
    use ChecksLeaderPermission, ResolvesContextCompany;

    public function edit()
    {
        $this->requireLeaderPermission('workload_setting');
        $user = Auth::user();

        $department = Department::find($user->department_id);
        abort_if(!$department, 404, '所属部署が見つかりません');

        $companyId = $department->company_id;
        $configs   = DepartmentFieldConfig::where('department_id', $department->id)
            ->get()
            ->keyBy('slot');

        return Inertia::render('Leader/DepartmentFieldConfig', [
            'department' => $department->only('id', 'name'),
            'configs'    => $configs,
            'masters'    => $this->buildMasters($companyId),
        ]);
    }

    public function update(Request $request)
    {
        $this->requireLeaderPermission('workload_setting');
        $user = Auth::user();

        $department = Department::find($user->department_id);
        abort_if(!$department, 404, '所属部署が見つかりません');

        $request->validate([
            'slots'                      => 'required|array',
            'slots.*.slot'               => 'required|in:type,stage,size,amounts',
            'slots.*.label'              => 'nullable|string|max:100',
            'slots.*.enabled'            => 'boolean',
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

        return redirect()->route('leader.department_field_config.edit')
            ->with('success', 'フィールド設定を保存しました');
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
}
