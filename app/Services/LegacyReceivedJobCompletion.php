<?php

namespace App\Services;

use App\Models\Event;
use App\Models\ProjectJob;
use App\Http\Controllers\Concerns\CalculatesEventTime;
use App\Models\ProjectJobAssignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Existing owner completion only. Does not grant access to any trial page or input. */
class LegacyReceivedJobCompletion
{
    use CalculatesEventTime;
    public function tryComplete(User $user, int $id, ?int $context = null, ?int $eventId = null): ?array
    {
        $root = ProjectJobAssignment::findOrFail($id);
        if ($eventId !== null && (int) $root->user_id !== (int) $user->id) throw new AuthorizationException;
        // Keep non-proof direct requests and privileged proxies in their existing handlers.
        if ((int) $root->user_id !== (int) $user->id || ((int) $root->sender_id !== (int) $user->id && $root->job_type !== 'proof')) return null;
        if (! $this->hasCandidate($user, $id)) return null;
        if ($context !== null && $context !== (int) $user->company_id) throw new AuthorizationException;
        $tree = $this->inspect($user, $id);
        if ($tree['mixed']) {
            // A typed ancestor must never be silently discarded. Its existing guard remains authoritative.
            if (! app(UnifiedMyJobBox::class)->available($user, $context)) $this->invalid('この系列は専用のマイジョブ詳細から完了してください。');
            return app(WorkJobMutation::class)->completeWork($user, 'assignment', $id, $context, $eventId);
        }
        if (! $tree['received'] && ! $tree['proof']) return null;
        if (DB::transactionLevel() !== 0) throw new \LogicException('Legacy received completion must own its transaction');
        $result = DB::transaction(function () use ($user, $id, $tree, $eventId) {
            foreach ($tree['chain'] as $assignment) ProjectJobAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $again = $this->inspect($user, $id, true);
            if ($again['mixed'] || (! $again['received'] && ! $again['proof']) || $this->signature($tree) !== $this->signature($again)) {
                $this->invalid('続き元または依頼が更新されました。詳細を開き直してください。');
            }
            $occurredOn = null;
            if ($eventId !== null) {
                $event = $this->lockEvent($user, $id, $eventId);
                $event->load('projectJobAssignment:id,job_type');
                $occurredOn = $this->resolveJstCarbon($event, 'starts_at')?->toDateString() ?? now()->toDateString();
            }
            // Read originals and duplicate imports under current-read locks, not an earlier RR snapshot.
            $originals = app(WorkJobReceivedCompletion::class)->originals($user, $again['chain']);
            $expected = array_unique(array_filter(array_map(fn ($a) => $a->supersedes_assignment_id, $again['chain'])));
            if (! $again['proof'] && count($originals) !== count($expected)) $this->invalid('依頼の種類が更新されました。詳細を開き直してください。');
            $changed = ! $again['chain'][count($again['chain']) - 1]->completed;
            $syncAssignments = app(WorkJobLegacyCompletion::class)->complete($user, $again['chain'], $occurredOn);
            return ['id' => $id, 'type' => 'assignment', 'changed' => $changed,
                'completed_targets' => array_map(fn ($a) => ['type' => 'assignment', 'id' => (int) $a->id], $again['chain']),
                'sync_ids' => DB::table('work_job_completion_syncs')->whereIn('assignment_id', $syncAssignments)->whereNull('synced_at')->pluck('id')->all()];
        }, 3);
        $pending = 0;
        foreach ($result['sync_ids'] as $receipt) if (! app(WorkJobCompletionSync::class)->run((int) $receipt)) $pending++;
        unset($result['sync_ids']);
        return $result + ['pending_syncs' => $pending];
    }

    /** Do not reroute unrelated legacy self jobs or direct-request continuation chains. */
    private function hasCandidate(User $user, int $id): bool
    {
        $seen = [];
        for ($depth = 0; $depth < 100; $depth++) {
            if (isset($seen[$id])) $this->invalid('続き元が循環しています。');
            $seen[$id] = true;
            $job = ProjectJobAssignment::where('user_id', $user->id)->findOrFail($id);
            if ($job->job_type === 'proof' || $job->coordinator_assignment_id || $job->supersedes_assignment_id || $job->source_personal_work_entry_id) return true;
            if (! $job->source_assignment_id) return false;
            $id = (int) $job->source_assignment_id;
        }
        $this->invalid('系列が長いため、管理者に確認してください。');
    }

    private function inspect(User $user, int $id, bool $lock = false): array
    {
        $chain = []; $seen = []; $received = false; $proof = false; $mixed = false;
        for ($depth = 0; $depth < 100; $depth++) {
            if (isset($seen[$id])) $this->invalid('続き元が循環しています。');
            $seen[$id] = true;
            $assignment = ProjectJobAssignment::with('projectJob')->findOrFail($id);
            if ($lock) $assignment->setRelation('projectJob', ProjectJob::whereKey($assignment->project_job_id)->lockForUpdate()->firstOrFail());
            if ((int) $assignment->user_id !== (int) $user->id || ((int) $assignment->sender_id !== (int) $user->id && $assignment->job_type !== 'proof')
                || ($assignment->company_id !== null && (int) $assignment->company_id !== (int) $user->company_id)
                || ($assignment->department_id !== null && (int) $assignment->department_id !== (int) $user->department_id)
                || ! $assignment->projectJob
                || ($assignment->projectJob->company_id !== null && (int) $assignment->projectJob->company_id !== (int) $user->company_id)) throw new AuthorizationException;
            $chain[] = $assignment;
            $proof = $proof || $assignment->job_type === 'proof';
            if ($assignment->coordinator_assignment_id) {
                $linked = ProjectJobAssignment::whereKey($assignment->coordinator_assignment_id)->where('user_id', $user->id)->where('project_job_id', $assignment->project_job_id)->firstOrFail();
                $proof = $proof || $linked->job_type === 'proof';
            }
            if ($assignment->supersedes_assignment_id) {
                $original = ProjectJobAssignment::whereKey($assignment->supersedes_assignment_id)
                    ->where('user_id', $user->id)->where('project_job_id', $assignment->project_job_id)->first(['id', 'job_type']);
                if (! $original) $this->invalid('元の依頼との関係を確認してください。');
                $proof = $proof || $original->job_type === 'proof';
                $received = $received || $original->job_type !== 'proof';
            }
            if ($assignment->source_personal_work_entry_id) {
                if ($assignment->source_assignment_id) $this->invalid('続き元の関係を確認してください。');
                $mixed = true;
                break;
            }
            if (! $assignment->source_assignment_id) break;
            $id = (int) $assignment->source_assignment_id;
            if ($depth === 99) $this->invalid('系列が長いため、管理者に確認してください。');
        }
        return ['chain' => array_reverse($chain), 'received' => $received, 'proof' => $proof, 'mixed' => $mixed];
    }

    private function signature(array $tree): array
    {
        return array_map(fn ($a) => $a->only(['id', 'user_id', 'sender_id', 'project_job_id', 'source_assignment_id', 'source_personal_work_entry_id', 'supersedes_assignment_id']), $tree['chain']);
    }

    private function lockEvent(User $user, int $id, int $eventId): Event
    {
        $event = Event::whereKey($eventId)->where('project_job_assignment_id', $id)->lockForUpdate()->firstOrFail();
        if ((int) $event->user_id !== (int) $user->id
            || ($event->company_id !== null && (int) $event->company_id !== (int) $user->company_id)
            || ($event->department_id !== null && (int) $event->department_id !== (int) $user->department_id)) throw new AuthorizationException;
        return $event;
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['source' => $message]);
    }
}
