<?php

namespace App\Services;

use App\Http\Controllers\Schedule\DepartmentWorkController;
use App\Models\PersonalWorkEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Stage 2a: mutations for simple-entry chains; legacy side effects stay disconnected. */
class WorkJobMutation
{
    public function __construct(private WorkJobReader $reader) {}

    private function lockAncestors(User $user, int $id, ?int $context): array
    {
        $ancestors = $this->reader->ancestors($user, 'entry', $id, $context);
        if (array_diff(array_column($ancestors, 'type'), ['entry'])) {
            throw ValidationException::withMessages(['source' => '旧形式を含む系列の操作は、接続対応後に利用できます。']);
        }
        foreach ($ancestors as $node) PersonalWorkEntry::whereKey($node['id'])->lockForUpdate()->firstOrFail();
        // The root-first lock order serializes completion and continuation creation.
        $again = $this->reader->ancestors($user, 'entry', $id, $context);
        if ($ancestors !== $again) throw ValidationException::withMessages(['source' => '仕事が更新されました。再度開いてください。']);
        $tree = $this->reader->series($user, $id, $context, false);
        if (! $tree['operationsEnabled']) throw ValidationException::withMessages(['source' => '旧形式を含む系列の操作は、接続対応後に利用できます。']);
        return $again;
    }

    public function continueEntry(Request $request, int $source, ?int $context, DepartmentWorkController $entries): array
    {
        $user = $request->user();
        $this->reader->resolve($user, 'entry', $source, $context);
        $token = $request->validate(['request_token' => ['required', 'uuid']])['request_token'];
        $data = $entries->validatedEntry($request, $user);
        // Hash normalized validated values only; arbitrary source/status/owner fields are ignored.
        ksort($data);
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        $key = hash('sha256', $user->id.':'.$token);
        return DB::transaction(function () use ($user, $source, $context, $data, $hash, $key) {
            $this->lockAncestors($user, $source, $context);
            $receipt = DB::table('work_job_continuations')->where('request_key', $key)->first();
            if ($receipt) {
                abort_unless((int) $receipt->user_id === (int) $user->id && (int) $receipt->source_entry_id === $source
                    && hash_equals($receipt->payload_hash, $hash), 422, '同じ送信キーの内容が変わっています。画面を開き直してください。');
                abort_unless($receipt->entry_id, 410, '作成済みの続きは削除されています。');
                $this->reader->resolve($user, 'entry', (int) $receipt->entry_id, $context);
                return ['id' => (int) $receipt->entry_id, 'replayed' => true];
            }
            if (count($this->reader->series($user, $source, $context, false)['items']) >= 100) {
                throw ValidationException::withMessages(['source' => '系列が多いため、これ以上の続きは登録できません。']);
            }
            $entry = new PersonalWorkEntry($data + ['user_id' => $user->id,
                'company_id' => $user->company_id, 'department_id' => $user->department_id]);
            $entry->source_entry_id = $source;
            $entry->save();
            DB::table('work_job_continuations')->insert(['request_key' => $key, 'user_id' => $user->id,
                'source_entry_id' => $source, 'payload_hash' => $hash, 'entry_id' => $entry->id,
                'created_at' => now(), 'updated_at' => now()]);
            return ['id' => (int) $entry->id, 'replayed' => false];
        }, 3);
    }

    public function completeEntry(User $user, int $id, ?int $context): array
    {
        return DB::transaction(function () use ($user, $id, $context) {
            $ancestors = $this->lockAncestors($user, $id, $context);
            $ids = array_column($ancestors, 'id');
            PersonalWorkEntry::whereIn('id', $ids)->whereNull('completed_at')->update(['completed_at' => now()]);
            return ['id' => $id, 'completed_ids' => $ids];
        }, 3);
    }
}
