<?php

namespace App\Services\MGinbon;

use App\Models\ProjectJobAssignment;
use Illuminate\Support\Facades\DB;

class MGinbonAssignmentSyncService
{
    /** 完了操作と同時に確定できるFileMaker由来の工程日付。 */
    private const COMPLETION_MILESTONE_CODES = [
        'text_input' => 'text_input_completed_on',
        'drawing' => 'drawing_completed_on',
        'initial_text_proof' => 'initial_text_proof_completed_on',
        'reproof_scan_check' => 'reproof_scan_check_completed_on',
        'reproof_text_proof' => 'reproof_text_proof_completed_on',
    ];

    private const START_MILESTONE_CODES = [
        'initial_text_proof' => 'initial_text_proof_started_on',
        'reproof_scan_check' => 'reproof_scan_check_started_on',
        'reproof_text_proof' => 'reproof_text_proof_started_on',
    ];

    /**
     * Return the MGinbon-specific labels shown on the shared MyJob detail card.
     * Continuation jobs resolve the package attached to their source assignment.
     */
    public function detailContext(ProjectJobAssignment $assignment): ?array
    {
        $db = DB::connection('mginbon');
        $package = $db->table('mginbon_work_packages as packages')
            ->join('mginbon_items as items', 'items.id', '=', 'packages.mginbon_item_id')
            ->join('mginbon_production_units as units', 'units.id', '=', 'items.mginbon_production_unit_id')
            ->join('mginbon_media_types as media', 'media.id', '=', 'items.mginbon_media_type_id')
            ->join('mginbon_stage_definitions as stages', 'stages.id', '=', 'packages.mginbon_stage_definition_id')
            ->whereIn('packages.project_job_assignment_id', $this->lineageIds($assignment))
            ->where('packages.status', '!=', 'cancelled')
            ->orderByDesc('packages.id')
            ->first([
                'packages.id', 'packages.status', 'units.display_name as production_unit_name',
                'media.name as media_name', 'stages.name as stage_name',
            ]);

        if (! $package) {
            return null;
        }

        $subjectNames = $db->table('mginbon_work_package_tasks as package_tasks')
            ->join('mginbon_stage_tasks as tasks', 'tasks.id', '=', 'package_tasks.mginbon_stage_task_id')
            ->join('mginbon_item_subjects as item_subjects', 'item_subjects.id', '=', 'tasks.mginbon_item_subject_id')
            ->join('mginbon_subjects as subjects', 'subjects.id', '=', 'item_subjects.mginbon_subject_id')
            ->where('package_tasks.mginbon_work_package_id', $package->id)
            ->orderBy('subjects.sort_order')
            ->pluck('subjects.name')
            ->unique()
            ->values()
            ->all();

        return [
            'production_unit_name' => $package->production_unit_name,
            'media_name' => $package->media_name,
            'stage_name' => $package->stage_name,
            'subject_names' => $subjectNames,
            'status' => $package->status,
        ];
    }

    /**
     * Keep an MGinbon work package in step with the canonical MyJob assignment.
     */
    public function sync(ProjectJobAssignment $assignment, ?int $actorId = null): void
    {
        if ((bool) $assignment->completed) {
            $this->complete($assignment, $actorId);
        } else {
            $this->reopen($assignment, $actorId);
        }
    }

    /**
     * Mark an MGinbon package in progress when its first calendar work is set.
     * Existing milestone dates are authoritative and are never overwritten.
     */
    public function start(ProjectJobAssignment $assignment, string $occurredOn, ?int $actorId = null): void
    {
        $assignmentIds = $this->lineageIds($assignment);
        $db = DB::connection('mginbon');

        $db->transaction(function () use ($db, $assignmentIds, $occurredOn, $actorId): void {
            $packages = $db->table('mginbon_work_packages')
                ->whereIn('project_job_assignment_id', $assignmentIds)
                ->whereIn('status', ['assigned', 'in_progress'])
                ->lockForUpdate()
                ->get();

            foreach ($packages as $package) {
                $taskIds = $db->table('mginbon_work_package_tasks')
                    ->where('mginbon_work_package_id', $package->id)
                    ->pluck('mginbon_stage_task_id');

                $db->table('mginbon_stage_tasks')
                    ->whereIn('id', $taskIds)
                    ->where('project_job_assignment_id', $package->project_job_assignment_id)
                    ->where('status', 'assigned')
                    ->update(['status' => 'in_progress', 'updated_at' => now()]);
                $db->table('mginbon_work_packages')->where('id', $package->id)
                    ->where('status', 'assigned')
                    ->update(['status' => 'in_progress', 'updated_at' => now()]);

                $this->recordStartMilestones($db, $package, $taskIds, $actorId, $occurredOn);
            }
        });
    }

    /**
     * Reopen a completed package without discarding its assignment or history.
     */
    public function reopen(ProjectJobAssignment $assignment, ?int $actorId = null): void
    {
        $assignmentIds = $this->lineageIds($assignment);
        $db = DB::connection('mginbon');

        $db->transaction(function () use ($db, $assignmentIds, $actorId): void {
            $packages = $db->table('mginbon_work_packages')
                ->whereIn('project_job_assignment_id', $assignmentIds)
                ->where('status', 'completed')
                ->lockForUpdate()
                ->get();

            foreach ($packages as $package) {
                $taskIds = $db->table('mginbon_work_package_tasks')
                    ->where('mginbon_work_package_id', $package->id)
                    ->pluck('mginbon_stage_task_id');

                $this->restoreCompletionMilestones($db, $package, $taskIds, $actorId);

                $db->table('mginbon_stage_tasks')
                    ->whereIn('id', $taskIds)
                    ->where('project_job_assignment_id', $package->project_job_assignment_id)
                    ->update(['status' => 'assigned', 'updated_at' => now()]);

                $db->table('mginbon_work_packages')->where('id', $package->id)->update([
                    'status' => 'assigned',
                    'completed_at' => null,
                    'updated_at' => now(),
                ]);

                $this->log($db, $package, $actorId, 'assigned', 'assignment_reopened');
            }
        });
    }

    /**
     * Completing a continuation also completes the MGinbon package attached to
     * one of its ancestors.
     */
    public function complete(ProjectJobAssignment $assignment, ?int $actorId = null, ?string $occurredOn = null): void
    {
        $assignmentIds = $this->lineageIds($assignment);
        $db = DB::connection('mginbon');
        $occurredOn ??= now()->toDateString();

        $db->transaction(function () use ($db, $assignmentIds, $actorId, $occurredOn): void {
            $packages = $db->table('mginbon_work_packages')
                ->whereIn('project_job_assignment_id', $assignmentIds)
                ->whereIn('status', ['assigned', 'in_progress'])
                ->lockForUpdate()
                ->get();

            foreach ($packages as $package) {
                $taskIds = $db->table('mginbon_work_package_tasks')
                    ->where('mginbon_work_package_id', $package->id)
                    ->pluck('mginbon_stage_task_id');

                $db->table('mginbon_stage_tasks')
                    ->whereIn('id', $taskIds)
                    ->where('project_job_assignment_id', $package->project_job_assignment_id)
                    ->update(['status' => 'completed', 'updated_at' => now()]);

                $db->table('mginbon_work_packages')->where('id', $package->id)
                    ->update(['status' => 'completed', 'completed_at' => now(), 'updated_at' => now()]);

                $this->recordCompletionMilestones($db, $package, $taskIds, $actorId, $occurredOn);
                $this->log($db, $package, $actorId, 'completed', 'calendar_or_myjob');
            }
        });
    }

    /**
     * Release tasks when their MyJob is deleted. Historical packages and links
     * remain as cancelled records; only the current assignee is removed.
     */
    public function release(int $assignmentId, ?int $actorId = null, string $reason = 'myjob_deleted'): int
    {
        $db = DB::connection('mginbon');

        return $db->transaction(function () use ($db, $assignmentId, $actorId, $reason): int {
            $packages = $db->table('mginbon_work_packages')
                ->where('project_job_assignment_id', $assignmentId)
                ->whereIn('status', ['assigned', 'in_progress', 'completed'])
                ->lockForUpdate()
                ->get();

            foreach ($packages as $package) {
                $taskIds = $db->table('mginbon_work_package_tasks')
                    ->where('mginbon_work_package_id', $package->id)
                    ->pluck('mginbon_stage_task_id');

                if ($package->status === 'completed') {
                    $this->restoreCompletionMilestones($db, $package, $taskIds, $actorId);
                }
                $this->removeAutoStartMilestones($db, $package, $taskIds, $actorId);

                $db->table('mginbon_stage_tasks')
                    ->whereIn('id', $taskIds)
                    ->where('project_job_assignment_id', $assignmentId)
                    ->update([
                        'status' => 'not_started',
                        'project_job_assignment_id' => null,
                        'updated_at' => now(),
                    ]);

                // Self-registration creates a separate resolved participant row.
                // Imported legacy participants are deliberately retained.
                if ($package->user_id) {
                    $db->table('mginbon_stage_task_participants')
                        ->whereIn('mginbon_stage_task_id', $taskIds)
                        ->where('user_id', $package->user_id)
                        ->whereNull('legacy_value')
                        ->delete();
                }

                $db->table('mginbon_work_packages')->where('id', $package->id)
                    ->update(['status' => 'cancelled', 'cancelled_at' => now(), 'updated_at' => now()]);

                $this->log($db, $package, $actorId, 'not_started', $reason);
            }

            return $packages->count();
        });
    }

    private function lineageIds(ProjectJobAssignment $assignment): array
    {
        $ids = [$assignment->id];
        $current = $assignment;

        for ($i = 0; $i < 20 && $current->source_assignment_id; $i++) {
            $current = ProjectJobAssignment::query()->find($current->source_assignment_id);
            if (! $current || in_array($current->id, $ids, true)) {
                break;
            }
            $ids[] = $current->id;
        }

        return $ids;
    }

    private function recordCompletionMilestones(
        $db,
        object $package,
        $taskIds,
        ?int $actorId,
        string $occurredOn
    ): void {
        $stageCode = $db->table('mginbon_stage_definitions')
            ->where('id', $package->mginbon_stage_definition_id)
            ->value('code');
        $milestoneCode = self::COMPLETION_MILESTONE_CODES[$stageCode] ?? null;
        if (! $milestoneCode) {
            return;
        }

        $tasks = $db->table('mginbon_stage_tasks')
            ->whereIn('id', $taskIds)
            ->where('project_job_assignment_id', $package->project_job_assignment_id)
            ->get(['mginbon_item_subject_id']);

        foreach ($tasks as $task) {
            $scope = $db->table('mginbon_milestones')
                ->where('mginbon_item_id', $package->mginbon_item_id)
                ->where('mginbon_item_subject_id', $task->mginbon_item_subject_id)
                ->where('code', $milestoneCode);
            $existing = $scope->first();
            $oldDate = $existing?->occurred_on;

            if ($existing) {
                $scope->update([
                    'occurred_on' => $occurredOn,
                    'source' => 'assignment_sync',
                    'updated_at' => now(),
                ]);
            } else {
                $db->table('mginbon_milestones')->insert([
                    'mginbon_item_id' => $package->mginbon_item_id,
                    'mginbon_item_subject_id' => $task->mginbon_item_subject_id,
                    'code' => $milestoneCode,
                    'occurred_on' => $occurredOn,
                    'source' => 'assignment_sync',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ((string) $oldDate !== $occurredOn) {
                $db->table('mginbon_change_logs')->insert([
                    'mginbon_project_id' => $package->mginbon_project_id,
                    'mginbon_item_id' => $package->mginbon_item_id,
                    'mginbon_item_subject_id' => $task->mginbon_item_subject_id,
                    'changed_by' => $actorId,
                    'field_path' => 'milestones.'.$milestoneCode,
                    'old_value' => $oldDate ? json_encode($oldDate) : null,
                    'new_value' => json_encode($occurredOn),
                    'source' => 'assignment_sync',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function recordStartMilestones(
        $db,
        object $package,
        $taskIds,
        ?int $actorId,
        string $occurredOn
    ): void {
        $stageCode = $db->table('mginbon_stage_definitions')
            ->where('id', $package->mginbon_stage_definition_id)
            ->value('code');
        $milestoneCode = self::START_MILESTONE_CODES[$stageCode] ?? null;
        if (! $milestoneCode) {
            return;
        }

        $subjectIds = $db->table('mginbon_stage_tasks')
            ->whereIn('id', $taskIds)
            ->where('project_job_assignment_id', $package->project_job_assignment_id)
            ->pluck('mginbon_item_subject_id');

        foreach ($subjectIds as $subjectId) {
            $exists = $db->table('mginbon_milestones')
                ->where('mginbon_item_id', $package->mginbon_item_id)
                ->where('mginbon_item_subject_id', $subjectId)
                ->where('code', $milestoneCode)
                ->exists();
            if ($exists) {
                continue;
            }

            $db->table('mginbon_milestones')->insert([
                'mginbon_item_id' => $package->mginbon_item_id,
                'mginbon_item_subject_id' => $subjectId,
                'code' => $milestoneCode,
                'occurred_on' => $occurredOn,
                'source' => 'assignment_sync',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $db->table('mginbon_change_logs')->insert([
                'mginbon_project_id' => $package->mginbon_project_id,
                'mginbon_item_id' => $package->mginbon_item_id,
                'mginbon_item_subject_id' => $subjectId,
                'changed_by' => $actorId,
                'field_path' => 'milestones.'.$milestoneCode,
                'old_value' => null,
                'new_value' => json_encode($occurredOn),
                'source' => 'assignment_sync',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function restoreCompletionMilestones($db, object $package, $taskIds, ?int $actorId): void
    {
        $stageCode = $db->table('mginbon_stage_definitions')
            ->where('id', $package->mginbon_stage_definition_id)
            ->value('code');
        $milestoneCode = self::COMPLETION_MILESTONE_CODES[$stageCode] ?? null;
        if (! $milestoneCode) {
            return;
        }

        $subjectIds = $db->table('mginbon_stage_tasks')
            ->whereIn('id', $taskIds)
            ->where('project_job_assignment_id', $package->project_job_assignment_id)
            ->pluck('mginbon_item_subject_id');

        foreach ($subjectIds as $subjectId) {
            $fieldPath = 'milestones.'.$milestoneCode;
            $lastChange = $db->table('mginbon_change_logs')
                ->where('mginbon_project_id', $package->mginbon_project_id)
                ->where('mginbon_item_id', $package->mginbon_item_id)
                ->where('mginbon_item_subject_id', $subjectId)
                ->where('field_path', $fieldPath)
                ->where('source', 'assignment_sync')
                ->latest('id')
                ->first();
            if (! $lastChange) {
                continue;
            }

            $oldDate = $lastChange->old_value !== null
                ? json_decode($lastChange->old_value, true)
                : null;
            $scope = $db->table('mginbon_milestones')
                ->where('mginbon_item_id', $package->mginbon_item_id)
                ->where('mginbon_item_subject_id', $subjectId)
                ->where('code', $milestoneCode)
                ->where('source', 'assignment_sync');

            if ($oldDate) {
                $scope->update([
                    'occurred_on' => $oldDate,
                    'source' => 'manual',
                    'updated_at' => now(),
                ]);
            } else {
                $scope->delete();
            }

            $db->table('mginbon_change_logs')->insert([
                'mginbon_project_id' => $package->mginbon_project_id,
                'mginbon_item_id' => $package->mginbon_item_id,
                'mginbon_item_subject_id' => $subjectId,
                'changed_by' => $actorId,
                'field_path' => $fieldPath,
                'old_value' => $lastChange->new_value,
                'new_value' => $oldDate ? json_encode($oldDate) : null,
                'source' => 'assignment_reopened',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function removeAutoStartMilestones($db, object $package, $taskIds, ?int $actorId): void
    {
        $stageCode = $db->table('mginbon_stage_definitions')
            ->where('id', $package->mginbon_stage_definition_id)
            ->value('code');
        $milestoneCode = self::START_MILESTONE_CODES[$stageCode] ?? null;
        if (! $milestoneCode) {
            return;
        }

        $subjectIds = $db->table('mginbon_stage_tasks')
            ->whereIn('id', $taskIds)
            ->where('project_job_assignment_id', $package->project_job_assignment_id)
            ->pluck('mginbon_item_subject_id');

        foreach ($subjectIds as $subjectId) {
            $scope = $db->table('mginbon_milestones')
                ->where('mginbon_item_id', $package->mginbon_item_id)
                ->where('mginbon_item_subject_id', $subjectId)
                ->where('code', $milestoneCode)
                ->where('source', 'assignment_sync');
            $oldDate = $scope->value('occurred_on');
            if (! $oldDate) {
                continue;
            }

            $scope->delete();
            $db->table('mginbon_change_logs')->insert([
                'mginbon_project_id' => $package->mginbon_project_id,
                'mginbon_item_id' => $package->mginbon_item_id,
                'mginbon_item_subject_id' => $subjectId,
                'changed_by' => $actorId,
                'field_path' => 'milestones.'.$milestoneCode,
                'old_value' => json_encode($oldDate),
                'new_value' => null,
                'source' => 'assignment_deleted',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function log($db, object $package, ?int $actorId, string $status, string $source): void
    {
        $db->table('mginbon_change_logs')->insert([
            'mginbon_project_id' => $package->mginbon_project_id,
            'mginbon_item_id' => $package->mginbon_item_id,
            'changed_by' => $actorId,
            'field_path' => 'work_packages.'.$package->id.'.assignment_status',
            'old_value' => json_encode([
                'project_job_assignment_id' => $package->project_job_assignment_id,
                'status' => $package->status,
            ], JSON_UNESCAPED_UNICODE),
            'new_value' => json_encode(['status' => $status], JSON_UNESCAPED_UNICODE),
            'source' => $source,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
