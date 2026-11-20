<?php

namespace App\Services;

use App\Models\{ProgressCell, ProjectJobAssignment, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Stage confirmation is explicit. Nightly work-record closure must never call this service. */
class ProgressStageCompletion
{
    public static function columnType(ProgressCell $cell): string
    {
        $find = function (array $nodes) use (&$find, $cell): ?string {
            foreach ($nodes as $node) {
                if (($node['key'] ?? null) === $cell->col_key) return $node['type'] ?? 'worker';
                if ($type = $find($node['children'] ?? [])) return $type;
            }
            return null;
        };
        return $find($cell->row?->sheet?->column_config ?? []) ?? $cell->cell_type ?? 'worker';
    }

    public static function confirmed(ProgressCell $cell, ?string $type = null): bool
    {
        if ($cell->completed_at) return true;
        if (in_array($type ?? self::columnType($cell), ['proof_user', 'proof_v2'], true)) {
            return $cell->assignment?->proof_completed_at !== null || $cell->proofAssignment?->proof_completed_at !== null;
        }
        return false;
    }

    public function pendingChain(ProjectJobAssignment $assignment): bool
    {
        $chain = []; $seen = []; $owner = (int) $assignment->user_id;
        for ($i = 0; $i < 100; $i++) {
            if (isset($seen[$assignment->id]) || (int) $assignment->user_id !== $owner) return false;
            $seen[$assignment->id] = true; $chain[] = $assignment;
            if (! $assignment->source_assignment_id) return $this->pending($chain);
            $parent = ProjectJobAssignment::find($assignment->source_assignment_id);
            if (! $parent || ($parent->company_id !== null && $assignment->company_id !== null
                && (int) $parent->company_id !== (int) $assignment->company_id)) return false;
            $assignment = $parent;
        }
        return false;
    }

    /** Read-only: the caller already authorized these jobs; only their same-project cells count. */
    public function pending(array $assignments): bool
    {
        foreach ($assignments as $assignment) {
            $references = [$assignment];
            if ($assignment->supersedes_assignment_id) {
                $original = ProjectJobAssignment::find($assignment->supersedes_assignment_id);
                if ($original && (int) $original->user_id === (int) $assignment->user_id
                    && (int) $original->project_job_id === (int) $assignment->project_job_id) $references[] = $original;
            }
            foreach ($references as $reference) {
                foreach ($this->cells($reference) as $cell) {
                    if ((int) $cell->row?->sheet?->project_job_id === (int) $reference->project_job_id
                        && (in_array(self::columnType($cell), ['proof_user', 'proof_v2'], true) ? ($reference->job_type === 'proof' || $reference->proof_completed_at) : true)
                        && ! self::confirmed($cell)) return true;
                }
            }
        }
        return false;
    }

    /** Caller holds the assignment lock and owns the authorization/notification transaction. */
    public function confirm(User $actor, ProjectJobAssignment $assignment, bool $proofOnly = false): array
    {
        if (DB::transactionLevel() < 1) throw new \LogicException('Stage confirmation requires a transaction');
        // Serialize linked progress updates for the same project before locking its cells.
        \App\Models\ProjectJob::whereKey($assignment->project_job_id)->lockForUpdate()->firstOrFail();
        $linked = false; $changed = false;
        foreach ($this->cells($assignment, true) as $cell) {
            $project = $cell->row?->sheet?->projectJob;
            if (! $project || (int) $project->id !== (int) $assignment->project_job_id
                || ($project->company_id !== null && (int) $project->company_id !== (int) $actor->company_id)
                || ($assignment->company_id !== null && (int) $assignment->company_id !== (int) $actor->company_id)) {
                throw ValidationException::withMessages(['source' => '進行表とジョブの案件・会社の関係を確認してください。']);
            }
            $type = self::columnType($cell);
            if ($proofOnly && ! in_array($type, ['proof_user', 'proof_v2'], true)) continue;
            if (in_array($type, ['worker', 'joblink', 'coordinator'], true) && $cell->value_user_id !== null
                && (int) $cell->value_user_id !== (int) $assignment->user_id) {
                throw ValidationException::withMessages(['source' => '工程の担当者とジョブの担当者が一致しません。']);
            }
            // A worker finishing work is never a substitute for a separate proofreader.
            if (in_array($type, ['proof_user', 'proof_v2'], true) && $assignment->job_type !== 'proof'
                && ! $assignment->proof_completed_at) continue;
            $linked = true;
            if (! $cell->completed_at) {
                $cell->completed_at = now(); $cell->save();
                ProgressLinkService::recalculate($cell);
                $changed = true;
            }
        }
        return ['linked' => $linked, 'changed' => $changed];
    }

    public function confirmCell(User $actor, ProgressCell $reference): ProgressCell
    {
        return DB::transaction(function () use ($actor, $reference) {
            $assignment = $reference->assignment_id ? ProjectJobAssignment::whereKey($reference->assignment_id)->lockForUpdate()->firstOrFail() : null;
            $projectId = $reference->row?->sheet?->project_job_id;
            \App\Models\ProjectJob::whereKey($projectId)->lockForUpdate()->firstOrFail();
            $cell = ProgressCell::whereKey($reference->id)->lockForUpdate()->firstOrFail();
            abort_unless($cell->assignment_id === $reference->assignment_id && $cell->row_id === $reference->row_id
                && $cell->value_user_id === $reference->value_user_id, 409);
            $project = $cell->row?->sheet?->projectJob;
            abort_unless($project && ($project->company_id === null || (int) $project->company_id === (int) $actor->company_id), 403);
            if (! $cell->completed_at) {
                if ($assignment) {
                    $stage = $this->confirm($actor, $assignment);
                    if ($stage['changed'] && $assignment->job_type !== 'proof') JobNotificationService::notifyProgressCompleted($actor, $project, $assignment, true);
                    $cell->refresh();
                    abort_unless($cell->completed_at, 422, '校正ジョブから工程完了を確定してください。');
                } else {
                    $cell->completed_at = now(); $cell->save();
                    ProgressLinkService::recalculate($cell);
                }
                if (self::columnType($cell) === 'schedlink' && $cell->schedule_id) {
                    $schedule = \App\Models\ProjectSchedule::whereKey($cell->schedule_id)->lockForUpdate()->firstOrFail();
                    abort_unless((int) $schedule->project_job_id === (int) $project->id, 422);
                    if (! $schedule->completed_at) { $schedule->completed_at = $cell->completed_at; $schedule->save(); }
                }
            }
            return $cell;
        }, 3);
    }

    public function reopen(ProjectJobAssignment $assignment): void
    {
        if (DB::transactionLevel() < 1) throw new \LogicException('Stage reopening requires a transaction');
        \App\Models\ProjectJob::whereKey($assignment->project_job_id)->lockForUpdate()->firstOrFail();
        foreach ($this->cells($assignment, true) as $cell) {
            abort_unless((int) $cell->row?->sheet?->project_job_id === (int) $assignment->project_job_id, 422);
            if ($cell->completed_at) {
                $cell->completed_at = null; $cell->save();
                ProgressLinkService::recalculate($cell);
            }
        }
    }

    private function cells(ProjectJobAssignment $assignment, bool $lock = false)
    {
        $query = ProgressCell::where(function ($q) use ($assignment) {
            $q->where('assignment_id', $assignment->id);
            if ($assignment->job_type === 'proof') $q->orWhere('proof_assignment_id', $assignment->id);
        })->with('row.sheet.projectJob', 'assignment', 'proofAssignment')->orderBy('id');
        if ($lock) $query->lockForUpdate();
        return $query->get();
    }
}
