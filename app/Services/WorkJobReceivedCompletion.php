<?php

namespace App\Services;

use App\Models\ProjectJobAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Explicit received-request links only; never follows continuation links as replacements. */
class WorkJobReceivedCompletion
{
    /** Lock and validate all ordinary originals before any completion effects. Proof keeps its dedicated path. */
    public function originals(User $user, array $assignments): array
    {
        if (DB::transactionLevel() < 1) throw new \LogicException('Received completion requires a transaction');
        $links = [];
        foreach ($assignments as $assignment) {
            if ($assignment->supersedes_assignment_id) $links[(int) $assignment->supersedes_assignment_id][] = $assignment;
        }
        ksort($links);
        $originals = [];
        foreach ($links as $id => $imports) {
            $original = ProjectJobAssignment::whereKey($id)->lockForUpdate()->first();
            if (! $original) $this->invalid();
            // The existing proof validator/notification owns proof links, including legacy self-proof.
            if ($original->job_type === 'proof') continue;
            if (count($imports) !== 1) $this->invalid();
            $this->validate($user, $original, $imports[0]);
            $originals[$id] = $original;
        }
        return $originals;
    }

    /** Revalidate an ordinary original when consuming its durable cross-DB sync receipt. */
    public function validateSync(User $user, ProjectJobAssignment $original): void
    {
        $imports = ProjectJobAssignment::where('supersedes_assignment_id', $original->id)->orderBy('id')->lockForUpdate()->limit(2)->get();
        if ($imports->count() !== 1 || ! $imports->first()->completed) $this->invalid();
        $this->validate($user, $original, $imports->first());
    }

    private function validate(User $user, ProjectJobAssignment $original, ProjectJobAssignment $import): void
    {
        if ($original->job_type === 'proof' || $import->job_type === 'proof'
            || (int) $original->id === (int) $import->id
            || (int) $original->user_id !== (int) $user->id
            || (int) $original->sender_id === (int) $user->id
            || (int) $import->user_id !== (int) $user->id || (int) $import->sender_id !== (int) $user->id
            || (int) $import->supersedes_assignment_id !== (int) $original->id
            || (int) $original->project_job_id !== (int) $import->project_job_id
            || ! $original->projectJob || ! $import->projectJob) $this->invalid();
        foreach ([$original, $import] as $assignment) {
            if (($assignment->company_id !== null && (int) $assignment->company_id !== (int) $user->company_id)
                || ($assignment->department_id !== null && (int) $assignment->department_id !== (int) $user->department_id)
                || ($assignment->projectJob->company_id !== null && (int) $assignment->projectJob->company_id !== (int) $user->company_id)) $this->invalid();
        }
        // Do not guess which of multiple imports represents this request.
        $ids = ProjectJobAssignment::where('supersedes_assignment_id', $original->id)->orderBy('id')->lockForUpdate()->limit(2)->pluck('id');
        if ($ids->count() !== 1 || (int) $ids->first() !== (int) $import->id) $this->invalid();
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['source' => '元の依頼との関係を一意に確認できません。管理者に確認してください。']);
    }
}
