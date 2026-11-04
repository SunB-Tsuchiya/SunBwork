<?php

namespace App\Services;

use App\Models\Event;
use App\Models\ProgressCell;
use App\Models\ProjectJobAssignment;
use App\Models\ProofRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Called only inside the trial completion transaction, after all series authorization. */
class WorkJobLegacyCompletion
{
    public function complete(User $user, array $assignments): void
    {
        if (! $assignments) return;
        $status = DB::table('statuses')->where('key', 'completed')->orWhere('slug', 'completed')->first();
        if (! $status) throw ValidationException::withMessages(['source' => '完了ステータスがありません。管理者に確認してください。']);
        $notifications = []; $progressProjects = [];
        foreach ($assignments as $assignment) {
            $changed = ! $assignment->completed;
            $assignment->completed = true;
            $assignment->status_id = $status->id;
            $assignment->save();
            $progress = ProgressCell::where('assignment_id', $assignment->id)->exists();
            ProgressCell::where('assignment_id', $assignment->id)->whereNull('completed_at')->update(['completed_at' => now()]);
            foreach (Event::where('project_job_assignment_id', $assignment->id)->lockForUpdate()->get() as $event) {
                if (! str_starts_with((string) $event->title, '【完了】')) {
                    $event->title = '【完了】'.$event->title;
                    $event->save();
                }
            }
            if ($progress) $progressProjects[(int) $assignment->project_job_id] = true;
            if ($changed) $this->completeProof($user, $assignment);
            if ($changed && $assignment->projectJob) {
                // One notification per project per operation; a progress-linked ancestor wins.
                $key = (int) $assignment->project_job_id;
                if (! isset($notifications[$key]) || $progress) $notifications[$key] = [$assignment, $progress];
            }
            DB::table('work_job_completion_syncs')->insertOrIgnore([
                'assignment_id' => $assignment->id, 'user_id' => $user->id, 'company_id' => $user->company_id,
                'department_id' => $user->department_id, 'occurred_on' => now()->toDateString(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        foreach ($notifications as $projectId => [$assignment, $progress]) {
            if (isset($progressProjects[$projectId])) JobNotificationService::notifyProgressCompleted($user, $assignment->projectJob, $assignment, true);
            else JobNotificationService::notifyCompleted($user, $assignment, $assignment->projectJob, true);
        }
    }

    private function completeProof(User $user, ProjectJobAssignment $assignment): void
    {
        $proof = $assignment->job_type === 'proof' ? $assignment : null;
        if (! $proof && $assignment->supersedes_assignment_id) {
            $candidate = ProjectJobAssignment::whereKey($assignment->supersedes_assignment_id)->lockForUpdate()->firstOrFail();
            if ((int) $candidate->user_id !== (int) $user->id || (int) $candidate->project_job_id !== (int) $assignment->project_job_id) {
                throw ValidationException::withMessages(['source' => '元の依頼との関係を確認してください。']);
            }
            if ($candidate->job_type === 'proof') $proof = $candidate;
        }
        if (! $proof) return;
        if ((int) $proof->user_id !== (int) $user->id || (int) $proof->project_job_id !== (int) $assignment->project_job_id
            || ($proof->company_id !== null && (int) $proof->company_id !== (int) $user->company_id)
            || ($proof->department_id !== null && (int) $proof->department_id !== (int) $user->department_id)) {
            throw ValidationException::withMessages(['source' => '校正依頼との関係を確認してください。']);
        }
        $requests = ProofRequest::where('project_job_id', $proof->project_job_id)->where('proofreader_id', $user->id)
            ->whereIn('status', ['assigned', 'in_progress'])->orderBy('id')->lockForUpdate()->limit(2)->get();
        if ($requests->count() > 1) throw ValidationException::withMessages(['source' => '対応する校正依頼を一意に確認できません。']);
        if (! $proof->completed) { $proof->completed = true; $proof->save(); }
        if (! $request = $requests->first()) return;
        if ($request->project_job_assignment_id) {
            $coordinator = ProjectJobAssignment::whereKey($request->project_job_assignment_id)->lockForUpdate()->firstOrFail();
            if ((int) $coordinator->project_job_id !== (int) $proof->project_job_id
                || ($coordinator->company_id !== null && (int) $coordinator->company_id !== (int) $user->company_id)) {
                throw ValidationException::withMessages(['source' => '校正依頼の案件を確認してください。']);
            }
            if (! $coordinator->proof_completed_at) { $coordinator->proof_completed_at = now(); $coordinator->save(); }
        }
        $request->status = 'completed'; $request->completed_at = now(); $request->save();
        JobNotificationService::notifyProofCompleted($user, $request, true);
    }
}
