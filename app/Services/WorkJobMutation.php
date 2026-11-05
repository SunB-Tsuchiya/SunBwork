<?php

namespace App\Services;

use App\Http\Controllers\Schedule\DepartmentWorkController;
use App\Models\PersonalWorkEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Explicit simple continuations from either source; mixed completion uses the same authorized ancestor locks. */
class WorkJobMutation
{
    public function __construct(private WorkJobReader $reader) {}

    private function lockAncestors(User $user, int $id, ?int $context, string $type = 'entry', bool $allowMixed = false): array
    {
        $ancestors = $this->reader->ancestors($user, $type, $id, $context);
        if (! $allowMixed && array_diff(array_column($ancestors, 'type'), ['entry'])) {
            throw ValidationException::withMessages(['source' => '旧形式を含む系列の操作は、接続対応後に利用できます。']);
        }
        foreach ($ancestors as $node) {
            $job = $this->reader->resolve($user, $node['type'], $node['id'], $context);
            $job->newQuery()->whereKey($node['id'])->lockForUpdate()->firstOrFail();
        }
        // The root-first lock order serializes completion and continuation creation.
        $again = $this->reader->ancestors($user, $type, $id, $context);
        if ($ancestors !== $again) throw ValidationException::withMessages(['source' => '仕事が更新されました。再度開いてください。']);
        $tree = $this->reader->series($user, $id, $context, false, $type);
        if (! $allowMixed && ! $tree['operationsEnabled']) throw ValidationException::withMessages(['source' => '旧形式を含む系列の操作は、接続対応後に利用できます。']);
        return $again;
    }

    public function lockContinuationSource(User $user, int $id, ?int $context): array
    {
        if (DB::transactionLevel() < 1) throw new \LogicException('Continuation requires a transaction');
        return $this->lockAncestors($user, $id, $context, 'entry', true);
    }

    public function continueEntry(Request $request, int $source, ?int $context, DepartmentWorkController $entries, string $sourceType = 'entry'): array
    {
        $user = $request->user();
        $this->reader->resolve($user, $sourceType, $source, $context);
        $token = $request->validate(['request_token' => ['required', 'uuid']])['request_token'];
        $key = hash('sha256', $user->id.':'.$token);
        return DB::transaction(function () use ($user, $source, $context, $key, $sourceType, $entries, $request) {
            $this->lockAncestors($user, $source, $context, $sourceType, true);
            $parent = $this->reader->resolve($user, $sourceType, $source, $context);
            $data = $entries->validatedEntry($request, $user, false, null, $parent->project_job_id ? (int) $parent->project_job_id : null);
            // Revalidate after locking; hash only the validated fields.
            ksort($data);
            $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            $receipt = DB::table('work_job_continuations')->where('request_key', $key)->first();
            if ($receipt) {
                abort_unless((int) $receipt->user_id === (int) $user->id && (int) ($sourceType === 'entry' ? $receipt->source_entry_id : $receipt->source_assignment_id) === $source
                    && ($sourceType === 'entry' ? $receipt->source_assignment_id === null : $receipt->source_entry_id === null)
                    && $receipt->assignment_id === null && hash_equals($receipt->payload_hash, $hash), 422, '同じ送信キーの内容が変わっています。画面を開き直してください。');
                abort_unless($receipt->entry_id, 410, '作成済みの続きは削除されています。');
                $this->reader->resolve($user, 'entry', (int) $receipt->entry_id, $context);
                return ['id' => (int) $receipt->entry_id, 'replayed' => true];
            }
            if (count($this->reader->series($user, $source, $context, false, $sourceType)['items']) >= 100) {
                throw ValidationException::withMessages(['source' => '系列が多いため、これ以上の続きは登録できません。']);
            }
            $entry = new PersonalWorkEntry($data + ['user_id' => $user->id,
                'company_id' => $user->company_id, 'department_id' => $user->department_id]);
            $entry->setAttribute($sourceType === 'entry' ? 'source_entry_id' : 'source_assignment_id', $source);
            $entry->save();
            DB::table('work_job_continuations')->insert(['request_key' => $key, 'user_id' => $user->id,
                'source_entry_id' => $sourceType === 'entry' ? $source : null,
                'source_assignment_id' => $sourceType === 'assignment' ? $source : null, 'payload_hash' => $hash, 'entry_id' => $entry->id,
                'created_at' => now(), 'updated_at' => now()]);
            return ['id' => (int) $entry->id, 'replayed' => false];
        }, 3);
    }

    public function completeEntry(User $user, int $id, ?int $context): array
    {
        return $this->completeWork($user, 'entry', $id, $context);
    }

    public function completeWork(User $user, string $type, int $id, ?int $context): array
    {
        if (DB::transactionLevel() !== 0) throw new \LogicException('Completion must own its transaction');
        $result = DB::transaction(function () use ($user, $type, $id, $context) {
            $ancestors = $this->lockAncestors($user, $id, $context, $type, true);
            $entryIds = []; $assignments = [];
            foreach ($ancestors as $node) {
                if ($node['type'] === 'entry') $entryIds[] = $node['id'];
                else $assignments[] = $this->reader->resolve($user, 'assignment', $node['id'], $context);
            }
            app(WorkJobLegacyCompletion::class)->complete($user, $assignments);
            PersonalWorkEntry::whereIn('id', $entryIds)->whereNull('completed_at')->update(['completed_at' => now()]);
            return ['id' => $id, 'type' => $type, 'completed_ids' => $entryIds,
                'completed_targets' => array_map(fn ($node) => ['type' => $node['type'], 'id' => $node['id']], $ancestors),
                'sync_ids' => $assignments ? DB::table('work_job_completion_syncs')
                    ->whereIn('assignment_id', array_map(fn ($a) => $a->id, $assignments))->whereNull('synced_at')->pluck('id')->all() : []];
        }, 3);
        // This method owns the transaction. Do not issue a cross-DB write before its commit.
        $pending = 0;
        foreach ($result['sync_ids'] as $receipt) if (! app(WorkJobCompletionSync::class)->run((int) $receipt)) $pending++;
        unset($result['sync_ids']);
        return $result + ['pending_syncs' => $pending];
    }
}
