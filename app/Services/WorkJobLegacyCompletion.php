<?php

namespace App\Services;

use App\Models\Event;
use App\Models\ProgressCell;
use App\Models\JobAssignmentMessage;
use Illuminate\Support\Facades\Schema;
use App\Models\ProjectJobAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Called inside an owned completion transaction after all series authorization. */
class WorkJobLegacyCompletion
{
    public function complete(User $user, array $assignments, ?string $occurredOn = null): array
    {
        if (! $assignments) return [];
        $status = DB::table('statuses')->where('key', 'completed')->orWhere('slug', 'completed')->first();
        if (! $status) throw ValidationException::withMessages(['source' => '完了ステータスがありません。管理者に確認してください。']);
        $originals = app(WorkJobReceivedCompletion::class)->originals($user, $assignments);
        $allAssignments = array_merge($assignments, array_values($originals));
        $notifications = []; $progressProjects = []; $receivedNotifications = [];
        foreach ($allAssignments as $assignment) {
            $changed = ! $assignment->completed;
            $assignment->completed = true;
            $assignment->status_id = $status->id;
            $assignment->save();
            $progress = ProgressCell::where('assignment_id', $assignment->id)->exists();
            ProgressCell::where('assignment_id', $assignment->id)->whereNull('completed_at')->update(['completed_at' => now()]);
            \App\Models\WorkflowCell::where('assignment_id', $assignment->id)->whereNull('completed_at')->update(['completed_at' => now()]);
            foreach (Event::where('project_job_assignment_id', $assignment->id)->lockForUpdate()->get() as $event) {
                if ((int) $event->user_id !== (int) $user->id
                    || ($event->company_id !== null && (int) $event->company_id !== (int) $user->company_id)
                    || ($event->department_id !== null && (int) $event->department_id !== (int) $user->department_id)) {
                    throw ValidationException::withMessages(['source' => '元の依頼の予定との関係を確認してください。']);
                }
                if (! str_starts_with((string) $event->title, '【完了】')) {
                    $event->title = '【完了】'.$event->title;
                    $event->save();
                }
            }
            if ($progress) $progressProjects[(int) $assignment->project_job_id] = true;
            if (isset($originals[$assignment->id])) {
                if ($changed) $receivedNotifications[] = [$assignment, $progress];
                $messageState = [];
                if (Schema::hasColumn('job_assignment_messages', 'completed')) $messageState['completed'] = true;
                if (Schema::hasColumn('job_assignment_messages', 'status_id')) $messageState['status_id'] = $status->id;
                if ($messageState) JobAssignmentMessage::where('project_job_assignment_id', $assignment->id)->update($messageState);
            } elseif ($changed) $this->completeProof($user, $assignment);
            if ($changed && $assignment->projectJob && ! isset($originals[$assignment->id])
                && ! isset($originals[$assignment->supersedes_assignment_id])) {
                // One notification per project per operation; a progress-linked ancestor wins.
                $key = (int) $assignment->project_job_id;
                if (! isset($notifications[$key]) || $progress) $notifications[$key] = [$assignment, $progress];
            }
            DB::table('work_job_completion_syncs')->insertOrIgnore([
                'assignment_id' => $assignment->id, 'user_id' => $user->id, 'company_id' => $user->company_id,
                'department_id' => $user->department_id, 'occurred_on' => $occurredOn ?? now()->toDateString(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        // Original request notifications replace the import's generic/project notification, including on replay.
        $receivedProjects = array_fill_keys(array_map(fn ($pair) => (int) $pair[0]->project_job_id, $receivedNotifications), true);
        foreach ($receivedNotifications as [$original, $progress]) {
            JobNotificationService::notifyReceivedCompleted($user, $original, $original->projectJob,
                $progress || isset($progressProjects[(int) $original->project_job_id]));
        }
        foreach ($notifications as $projectId => [$assignment, $progress]) {
            if (isset($receivedProjects[$projectId])) continue;
            if (isset($progressProjects[$projectId])) JobNotificationService::notifyProgressCompleted($user, $assignment->projectJob, $assignment, true);
            elseif ($assignment->job_type !== 'proof') JobNotificationService::notifyCompleted($user, $assignment, $assignment->projectJob, true);
        }
        return array_map(fn ($a) => (int) $a->id, $allAssignments);
    }

    private function completeProof(User $user, ProjectJobAssignment $assignment): void
    {
        app(ProofRequestCompletion::class)->completeAssignment($user, $assignment);
    }
}
