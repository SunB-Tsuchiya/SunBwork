<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\MGinbon\MGinbonItem;
use App\Models\ProjectJob;
use App\Models\Subcontractor;
use App\Models\User;
use App\Services\ProjectJobAssigneeOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MGinbonWorkPackageController extends Controller
{
    public function store(Request $request, MGinbonItem $item, ProjectJobAssigneeOptions $assigneeOptions): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'stage_definition_id' => ['required', 'integer'],
            'subject_ids' => ['required', 'array', 'min:1', 'max:4'],
            'subject_ids.*' => ['required', 'integer'],
            'target' => ['required', 'string', 'max:100'],
            'updated_at' => ['required', 'string'],
        ]);
        [$targetType, $targetId] = array_pad(explode(':', $validated['target'], 2), 2, null);
        abort_unless(in_array($targetType, ['user', 'subcontractor'], true) && ctype_digit((string) $targetId), 422);

        $db = DB::connection('mginbon');
        $unit = $db->table('mginbon_production_units')->where('id', $item->mginbon_production_unit_id)->first();
        $project = $unit ? $db->table('mginbon_projects')->where('id', $unit->mginbon_project_id)->first() : null;
        abort_unless($project?->project_job_id, 422, '先に銀本年度をSBWork案件へ接続してください。');
        $projectJob = ProjectJob::findOrFail($project->project_job_id);
        $options = $assigneeOptions->for($projectJob, $request->user());
        $allowedIds = $targetType === 'user' ? $options['users']->pluck('id') : $options['subcontractors']->pluck('id');
        abort_unless($allowedIds->contains((int) $targetId), 422, 'この案件へ設定できない担当者です。');
        $target = $targetType === 'user'
            ? User::withGhosts()->findOrFail((int) $targetId)
            : Subcontractor::findOrFail((int) $targetId);

        $result = $db->transaction(function () use ($request, $item, $project, $validated, $targetType, $target, $db) {
            $lockedItem = $db->table('mginbon_items')->where('id', $item->id)->lockForUpdate()->first();
            if (! $lockedItem || (string) $lockedItem->updated_at !== $validated['updated_at']) {
                throw ValidationException::withMessages(['updated_at' => 'ほかの利用者が更新しました。画面を再読み込みしてください。']);
            }
            $stage = $db->table('mginbon_stage_definitions')->where('id', $validated['stage_definition_id'])
                ->where('mginbon_project_id', $project->id)->where('is_active', true)->first();
            abort_unless($stage, 422, '対象工程が見つかりません。');
            $subjectIds = $db->table('mginbon_item_subjects')->where('mginbon_item_id', $item->id)
                ->whereIn('id', array_unique($validated['subject_ids']))->pluck('id');
            abort_unless($subjectIds->count() === count(array_unique($validated['subject_ids'])), 422, '教科が不正です。');
            $tasks = $db->table('mginbon_stage_tasks')->where('mginbon_item_id', $item->id)
                ->where('mginbon_stage_definition_id', $stage->id)
                ->whereIn('mginbon_item_subject_id', $subjectIds)->lockForUpdate()->get();
            abort_unless($tasks->count() === $subjectIds->count(), 422, '工程データが不足しています。');
            abort_if($tasks->contains(fn ($task) => $task->project_job_assignment_id
                || in_array($task->status, ['assigned', 'in_progress', 'completed'], true)),
                422, '正式登録済み、開始済み、または完了済みの担当者は変更できません。');

            $oldPackages = $db->table('mginbon_work_package_tasks as links')
                ->join('mginbon_work_packages as packages', 'packages.id', '=', 'links.mginbon_work_package_id')
                ->whereIn('links.mginbon_stage_task_id', $tasks->pluck('id'))
                ->where('packages.status', 'planned')
                ->get(['packages.id', 'packages.user_id', 'packages.subcontractor_id'])->unique('id');
            $oldTargets = $oldPackages->map(fn ($package) => $package->user_id
                ? 'user:'.$package->user_id : ($package->subcontractor_id ? 'subcontractor:'.$package->subcontractor_id : null))
                ->filter()->values()->all();
            $oldPackageIds = $oldPackages->pluck('id');
            $db->table('mginbon_work_package_tasks')->whereIn('mginbon_stage_task_id', $tasks->pluck('id'))->delete();
            if ($oldPackageIds->isNotEmpty()) {
                $db->table('mginbon_work_packages')->whereIn('id', $oldPackageIds)
                    ->update(['status' => 'cancelled', 'cancelled_at' => now(), 'updated_at' => now()]);
            }

            $packageId = $db->table('mginbon_work_packages')->insertGetId([
                'mginbon_project_id' => $project->id, 'mginbon_item_id' => $item->id,
                'mginbon_stage_definition_id' => $stage->id,
                'user_id' => $targetType === 'user' ? $target->id : null,
                'subcontractor_id' => $targetType === 'subcontractor' ? $target->id : null,
                'project_job_assignment_id' => null, 'status' => 'planned',
                'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($tasks as $task) {
                $db->table('mginbon_work_package_tasks')->insert([
                    'mginbon_work_package_id' => $packageId, 'mginbon_stage_task_id' => $task->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $participant = $db->table('mginbon_stage_task_participants')
                    ->where('mginbon_stage_task_id', $task->id)->where('role_type', $stage->activity_type)->first();
                $values = [
                    'user_id' => $targetType === 'user' ? $target->id : null,
                    'subcontractor_id' => $targetType === 'subcontractor' ? $target->id : null,
                    'execution_type' => $targetType === 'user' ? 'internal' : 'subcontracted',
                    'resolution_status' => 'planned', 'updated_at' => now(),
                ];
                $participant
                    ? $db->table('mginbon_stage_task_participants')->where('id', $participant->id)->update($values)
                    : $db->table('mginbon_stage_task_participants')->insert([
                        'mginbon_stage_task_id' => $task->id, 'role_type' => $stage->activity_type,
                        ...$values, 'created_at' => now(),
                    ]);
            }
            $now = now();
            $db->table('mginbon_items')->where('id', $item->id)->update(['updated_at' => $now]);
            $db->table('mginbon_change_logs')->insert([
                'mginbon_project_id' => $project->id, 'mginbon_item_id' => $item->id,
                'changed_by' => $request->user()->id, 'field_path' => 'work_package.planned_actor',
                'old_value' => json_encode($oldTargets, JSON_UNESCAPED_UNICODE),
                'new_value' => json_encode(['package_id' => $packageId, 'stage' => $stage->code,
                    'subject_ids' => $subjectIds->all(), 'target' => $validated['target']], JSON_UNESCAPED_UNICODE),
                'source' => 'manual', 'created_at' => now(), 'updated_at' => now(),
            ]);

            return ['actor' => $target->name, 'target' => $validated['target'], 'assignment_id' => null,
                'status' => 'not_started', 'planned' => true, 'updated_at' => $now->format('Y-m-d H:i:s')];
        });

        return $request->header('X-Inertia')
            ? back()->with('success', '仮担当者を設定しました。依頼ジョブは送信していません。')
            : response()->json($result);
    }
}
