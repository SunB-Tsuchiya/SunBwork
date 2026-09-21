<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\MGinbon\MGinbonItem;
use App\Models\Subcontractor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MGinbonWorkPackageController extends Controller
{
    public function store(Request $request, MGinbonItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'stage_definition_id' => ['required', 'integer'],
            'subject_ids' => ['required', 'array', 'min:1', 'max:4'],
            'subject_ids.*' => ['required', 'integer'],
            'target' => ['required', 'string', 'max:100'],
        ]);
        [$targetType, $targetId] = array_pad(explode(':', $validated['target'], 2), 2, null);
        abort_unless(in_array($targetType, ['user', 'subcontractor'], true), 422);

        $companyId = $request->user()?->user_role === 'superadmin'
            ? session('superadmin_context.company_id') : $request->user()?->company_id;
        abort_if($request->user()?->user_role === 'superadmin' && $companyId === null, 422, '会社を選択してください。');
        $target = $targetType === 'user'
            ? User::query()->where('company_id', $companyId)->findOrFail((int) $targetId)
            : Subcontractor::query()->where('company_id', $companyId)->findOrFail((int) $targetId);

        $db = DB::connection('mginbon');
        $db->transaction(function () use ($request, $item, $validated, $targetType, $target, $db) {
            $unit = $db->table('mginbon_production_units')->where('id', $item->mginbon_production_unit_id)->first();
            $stage = $db->table('mginbon_stage_definitions')->where('id', $validated['stage_definition_id'])
                ->where('mginbon_project_id', $unit->mginbon_project_id)->first();
            abort_unless($stage, 422);

            $subjectIds = $db->table('mginbon_item_subjects')->where('mginbon_item_id', $item->id)
                ->whereIn('id', $validated['subject_ids'])->pluck('id');
            abort_unless($subjectIds->count() === count(array_unique($validated['subject_ids'])), 422);
            $taskIds = $db->table('mginbon_stage_tasks')->where('mginbon_item_id', $item->id)
                ->where('mginbon_stage_definition_id', $stage->id)
                ->whereIn('mginbon_item_subject_id', $subjectIds)->lockForUpdate()->pluck('id');
            abort_unless($taskIds->count() === $subjectIds->count(), 422);

            $oldPackageIds = $db->table('mginbon_work_package_tasks')->whereIn('mginbon_stage_task_id', $taskIds)
                ->pluck('mginbon_work_package_id')->unique();
            $db->table('mginbon_work_package_tasks')->whereIn('mginbon_stage_task_id', $taskIds)->delete();
            foreach ($oldPackageIds as $oldPackageId) {
                if (! $db->table('mginbon_work_package_tasks')->where('mginbon_work_package_id', $oldPackageId)->exists()) {
                    $db->table('mginbon_work_packages')->where('id', $oldPackageId)->update(['status' => 'cancelled', 'updated_at' => now()]);
                }
            }

            $packageId = $db->table('mginbon_work_packages')->insertGetId([
                'mginbon_project_id' => $unit->mginbon_project_id, 'mginbon_item_id' => $item->id,
                'mginbon_stage_definition_id' => $stage->id,
                'user_id' => $targetType === 'user' ? $target->id : null,
                'subcontractor_id' => $targetType === 'subcontractor' ? $target->id : null,
                'status' => 'assigned', 'assigned_at' => now(), 'created_by' => $request->user()?->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($taskIds as $taskId) {
                $db->table('mginbon_work_package_tasks')->insert([
                    'mginbon_work_package_id' => $packageId, 'mginbon_stage_task_id' => $taskId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $participant = $db->table('mginbon_stage_task_participants')->where('mginbon_stage_task_id', $taskId)
                    ->where('role_type', $stage->activity_type)->first();
                $values = [
                    'user_id' => $targetType === 'user' ? $target->id : null,
                    'subcontractor_id' => $targetType === 'subcontractor' ? $target->id : null,
                    'execution_type' => $targetType === 'user' ? 'internal' : 'subcontracted',
                    'resolution_status' => 'resolved', 'updated_at' => now(),
                ];
                if ($participant) {
                    $db->table('mginbon_stage_task_participants')->where('id', $participant->id)->update($values);
                } else {
                    $db->table('mginbon_stage_task_participants')->insert([
                        'mginbon_stage_task_id' => $taskId, 'role_type' => $stage->activity_type,
                        ...$values, 'created_at' => now(),
                    ]);
                }
            }
            $db->table('mginbon_stage_tasks')->whereIn('id', $taskIds)->update(['status' => 'assigned', 'updated_at' => now()]);
            $db->table('mginbon_items')->where('id', $item->id)->update(['updated_at' => now()]);
            $db->table('mginbon_change_logs')->insert([
                'mginbon_project_id' => $unit->mginbon_project_id, 'mginbon_item_id' => $item->id,
                'changed_by' => $request->user()?->id, 'field_path' => 'work_package.assigned',
                'new_value' => json_encode(['package_id' => $packageId, 'stage' => $stage->code, 'subject_ids' => $subjectIds->all(), 'target_type' => $targetType, 'target_id' => $target->id, 'target_label' => $target->name], JSON_UNESCAPED_UNICODE),
                'source' => 'manual', 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return back()->with('success', '選択した教科へ担当を反映しました。');
    }
}
