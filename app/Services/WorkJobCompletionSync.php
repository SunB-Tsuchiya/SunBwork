<?php

namespace App\Services;

use App\Models\ProjectJobAssignment;
use App\Models\User;
use App\Services\MGinbon\MGinbonAssignmentSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Durable bridge to the separate MGinbon DB; never calls the sales connection. */
class WorkJobCompletionSync
{
    public function run(int $id): bool
    {
        try {
            return DB::transaction(function () use ($id) {
                $receipt = DB::table('work_job_completion_syncs')->where('id', $id)->lockForUpdate()->firstOrFail();
                if ($receipt->synced_at) return true;
                $assignment = ProjectJobAssignment::with('projectJob')->findOrFail($receipt->assignment_id);
                $user = User::findOrFail($receipt->user_id);
                // Direct proof jobs are validated by the proof completion path, not ordinary import links.
                if ((int) $assignment->sender_id !== (int) $user->id && $assignment->job_type !== 'proof') {
                    app(WorkJobReceivedCompletion::class)->validateSync($user, $assignment);
                }
                abort_unless($assignment->completed && (int) $assignment->user_id === (int) $user->id
                    && (int) $user->company_id === (int) $receipt->company_id
                    && (int) $user->department_id === (int) $receipt->department_id
                    && ($assignment->company_id === null || (int) $assignment->company_id === (int) $receipt->company_id)
                    && ($assignment->department_id === null || (int) $assignment->department_id === (int) $receipt->department_id)
                    && ($assignment->projectJob?->company_id === null || (int) $assignment->projectJob->company_id === (int) $receipt->company_id), 409);
                app(MGinbonAssignmentSyncService::class)->complete($assignment, (int) $user->id, $receipt->occurred_on, false);
                DB::table('work_job_completion_syncs')->where('id', $id)->update([
                    'attempts' => $receipt->attempts + 1, 'synced_at' => now(), 'last_failed_at' => null, 'updated_at' => now(),
                ]);
                return true;
            }, 3);
        } catch (\Throwable $e) {
            DB::table('work_job_completion_syncs')->where('id', $id)->whereNull('synced_at')->update([
                'attempts' => DB::raw('attempts + 1'), 'last_failed_at' => now(), 'updated_at' => now(),
            ]);
            Log::warning('Work completion MGinbon sync pending', ['receipt_id' => $id]);
            return false;
        }
    }
}
