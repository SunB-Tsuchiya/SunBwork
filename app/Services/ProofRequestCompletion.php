<?php

namespace App\Services;

use App\Models\ProjectJobAssignment;
use App\Models\ProofRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Called after the entrypoint's existing authorization; owns/joins the completion transaction. */
class ProofRequestCompletion
{
    private function complete(User $actor, int $id, ?ProjectJobAssignment $proof = null): bool
    {
        return DB::transaction(function () use ($actor, $id, $proof) {
            $request = ProofRequest::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($request->status === 'completed') return false; // No historical backfill on replay.
            if (! in_array($request->status, ['pending', 'assigned', 'in_progress'], true)) {
                $this->invalid('この校正依頼は完了できる状態ではありません。');
            }
            $requester = User::findOrFail($request->requester_id);
            if ((int) $requester->company_id !== (int) $actor->company_id
                || ($request->project_job_id && (! $request->projectJob || ($request->projectJob->company_id !== null && (int) $request->projectJob->company_id !== (int) $actor->company_id)))) {
                $this->invalid('校正依頼の会社を確認してください。');
            }
            if ($request->project_job_assignment_id) {
                $source = ProjectJobAssignment::whereKey($request->project_job_assignment_id)->lockForUpdate()->firstOrFail();
                if ((int) $source->project_job_id !== (int) $request->project_job_id
                    || ($source->company_id !== null && (int) $source->company_id !== (int) $actor->company_id)) {
                    $this->invalid('校正依頼の案件を確認してください。');
                }
                if (! $source->proof_completed_at) { $source->proof_completed_at = now(); $source->save(); }
            }
            if ($proof) {
                $proof = ProjectJobAssignment::whereKey($proof->id)->lockForUpdate()->firstOrFail();
                if ($proof->job_type !== 'proof' || (int) $proof->project_job_id !== (int) $request->project_job_id
                    || (int) $proof->user_id !== (int) $request->proofreader_id) $this->invalid('校正担当の関係を確認してください。');
                $proof->completed = true;
                if ($status = DB::table('statuses')->where('key', 'completed')->orWhere('slug', 'completed')->first()) $proof->status_id = $status->id;
                $proof->save();
            }
            $request->status = 'completed'; $request->completed_at = now(); $request->save();
            JobNotificationService::notifyProofCompleted($actor, $request, true);
            return true;
        });
    }

    /** An assignment has just transitioned, inside its caller's transaction. */
    public function completeAssignment(User $actor, ProjectJobAssignment $assignment): void
    {
        if (DB::transactionLevel() === 0) throw new \LogicException('Proof completion needs an assignment transaction');
        $proof = $assignment->job_type === 'proof' ? $assignment : null;
        $reference = $assignment->job_type === 'proof' && $assignment->coordinator_assignment_id
            ? $assignment->coordinator_assignment_id : $assignment->supersedes_assignment_id;
        if ($reference) {
            $candidate = ProjectJobAssignment::whereKey($reference)->lockForUpdate()->firstOrFail();
            if ((int) $candidate->user_id !== (int) $assignment->user_id
                || (int) $candidate->project_job_id !== (int) $assignment->project_job_id) $this->invalid('元の依頼との関係を確認してください。');
            if ($candidate->job_type === 'proof') {
                if ($candidate->completed) return; // A later request must not be attached to an old import.
                $proof = $candidate;
            }
        }
        if (! $proof) return;
        if (($proof->company_id !== null && (int) $proof->company_id !== (int) $actor->company_id)
            || ($proof->department_id !== null && (int) $proof->department_id !== (int) $actor->department_id)) {
            $this->invalid('校正依頼との所属を確認してください。');
        }
        $query = ProofRequest::where('project_job_id', $proof->project_job_id)->where('proofreader_id', $proof->user_id)
            ->whereIn('status', ['assigned', 'in_progress']);
        // Old records without a coordinator remain supported; populated coordinators must match.
        if ($proof->sender_id) $query->where(fn ($q) => $q->whereNull('proof_coordinator_id')->orWhere('proof_coordinator_id', $proof->sender_id));
        if ($proof->created_at) $query->where('created_at', '<=', $proof->getRawOriginal('created_at'));
        $requests = $query->orderBy('id')->lockForUpdate()->limit(2)->get();
        if ($requests->count() > 1) $this->invalid('対応する校正依頼を一意に確認できません。校正状況から確認してください。');
        if ($request = $requests->first()) $this->complete($actor, (int) $request->id, $proof);
    }

    /** Explicit request pages must not complete every unrelated job for the same project/worker. */
    public function completeRequest(User $actor, int $id): bool
    {
        return DB::transaction(function () use ($actor, $id) {
            $request = ProofRequest::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($request->status === 'completed') return false;
            $proof = null;
            if ($request->proofreader_id && $request->proof_coordinator_id) {
                $jobs = ProjectJobAssignment::where('project_job_id', $request->project_job_id)
                    ->where('user_id', $request->proofreader_id)->where('sender_id', $request->proof_coordinator_id)
                    ->where('job_type', 'proof')->where('completed', false)->orderBy('id')->lockForUpdate()->limit(2)->get();
                if ($jobs->count() > 1) $this->invalid('対応する校正割当を一意に確認できません。');
                $proof = $jobs->first();
                if ($proof) {
                    $others = ProofRequest::where('project_job_id', $request->project_job_id)
                        ->where('proofreader_id', $request->proofreader_id)
                        ->where(fn ($q) => $q->whereNull('proof_coordinator_id')->orWhere('proof_coordinator_id', $request->proof_coordinator_id))
                        ->whereIn('status', ['assigned', 'in_progress'])->orderBy('id')->lockForUpdate()->limit(2)->get();
                    if ($others->count() > 1) $this->invalid('同じ担当の校正依頼が複数あります。割当を確認してください。');
                }
            }
            return $this->complete($actor, $id, $proof);
        }, 3);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['source' => $message]);
    }
}
