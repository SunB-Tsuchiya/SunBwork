<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/** Add-only, reviewed ID manifest. Never classifies ProjectJobs or rewrites a tree. */
class BroadProjectClientImport
{
    public function run(int $company, int $department, array $manifest, bool $dryRun, string $connection = 'mysql'): array
    {
        $data = Validator::make(['rows' => $manifest], [
            'rows' => ['required', 'array', 'list', 'min:1', 'max:500'],
            'rows.*.broad_project_id' => ['required', 'integer', 'min:1', 'distinct'],
            'rows.*.name' => ['required', 'string'],
            'rows.*.parent_id' => ['present', 'nullable', 'integer', 'min:1'],
            'rows.*.client_ids' => ['present', 'array', 'list', 'max:200'],
            'rows.*.client_ids.*' => ['required', 'integer', 'min:1'],
        ])->validate();
        $db = DB::connection($connection);
        return $db->transaction(function () use ($db, $company, $department, $data, $dryRun) {
            $scope = $db->table('departments')->where('id', $department)->where('company_id', $company)->where('code', 'INFO')->lockForUpdate()->first();
            if (! $scope || ! $db->table('companies')->where('id', $company)->where('company_type', '!=', 'general')->exists()) {
                throw new RuntimeException('会社・部署の試用対象が一致しません。');
            }
            $plan = []; $add = 0;
            foreach ($data['rows'] as $row) {
                $node = $db->table('broad_projects')->where('id', $row['broad_project_id'])
                    ->where('company_id', $company)->where('department_id', $department)->first();
                if (! $node || $node->name !== $row['name'] || (string) $node->parent_id !== (string) $row['parent_id']) {
                    throw new RuntimeException('分類の名前・所属が確認表と一致しません。');
                }
                $current = $db->table('broad_project_clients')->where('broad_project_id', $node->id)->pluck('client_id')->map('intval')->all();
                $ids = array_values(array_unique(array_map('intval', $row['client_ids'])));
                if (count($ids) !== count($row['client_ids'])) throw new RuntimeException('取引先の重複指定があります。');
                // Include an eligible historical single association without ever replacing it.
                if ($node->client_id) {
                    $legacyAllowed = $db->table('company_clients')->where('company_id', $company)->where('client_id', $node->client_id)->exists();
                    if (! $legacyAllowed) throw new RuntimeException('既存単一取引先の会社関連を確認してください。');
                    $ids = array_values(array_unique([...$ids, (int) $node->client_id]));
                }
                $allowed = $db->table('clients as c')->whereIn('c.id', $ids)->whereExists(fn ($q) => $q->selectRaw('1')->from('company_clients as cc')
                    ->whereColumn('cc.client_id', 'c.id')->where('cc.company_id', $company))
                    ->where(fn ($q) => $q->where('c.is_dormant', false)->orWhereIn('c.id', [...$current, (int) $node->client_id]))
                    ->pluck('c.id')->map('intval')->all();
                if (array_diff($ids, $allowed)) throw new RuntimeException('未許可・他社・新規休眠の取引先が含まれます。');
                $new = array_values(array_diff($ids, $current)); $add += count($new);
                $plan[] = ['broad_project_id' => (int) $node->id, 'add_client_ids' => $new];
            }
            // Validate every row before the first insert; transaction protects against partial failures.
            if (! $dryRun) foreach ($plan as $row) foreach ($row['add_client_ids'] as $id) {
                $db->table('broad_project_clients')->insert(['broad_project_id' => $row['broad_project_id'], 'client_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
            return ['groups' => count($plan), 'additions' => $add, 'dry_run' => $dryRun, 'plan' => $plan];
        });
    }
}
