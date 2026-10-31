<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class ApprovedBroadProjectOrder
{
    public function __construct(private ?string $catalogPath = null) {}

    public function run(int $companyId, int $departmentId, bool $dryRun, string $connection = 'mysql'): array
    {
        $db = DB::connection($connection);
        $catalog = json_decode(file_get_contents($this->catalogPath ?? database_path('data/broad-project-classifications-20261031.json')), true, 512, JSON_THROW_ON_ERROR);
        $positions = array_flip(array_column($catalog, 'key'));
        $db->beginTransaction();
        try {
            $department = $db->table('departments')->where('id', $departmentId)->where('company_id', $companyId)
                ->where('code', 'INFO')->lockForUpdate()->first();
            if (! $department || ! $db->table('companies')->where('id', $companyId)->where('company_type', '!=', 'general')->exists()) {
                throw new RuntimeException('対象の情報出版の会社/部署を明示してください。');
            }
            $nodes = $db->table('broad_projects')->where('company_id', $companyId)->where('department_id', $departmentId)->orderBy('name')->orderBy('id')->get();
            $byId = $nodes->keyBy('id');
            foreach ($nodes as $node) {
                if ($node->parent_id !== null && (! isset($byId[$node->parent_id]) || $byId[$node->parent_id]->parent_id !== null)) {
                    throw new RuntimeException('大中2段以外の分類があります。自動移動せず停止しました。');
                }
            }
            $updated = 0; $groups = 0; $skipped = 0;
            foreach ($nodes->groupBy(fn ($node) => $node->parent_id ?? 'root') as $siblings) {
                // A web edit or earlier initialization owns the whole sibling order.
                if ($siblings->contains(fn ($node) => $node->sort_order !== null)) { $skipped++; continue; }
                $ordered = $siblings->sortBy(fn ($node) => $positions[$node->import_key] ?? PHP_INT_MAX)->values();
                foreach ($ordered as $index => $node) {
                    $db->table('broad_projects')->where('id', $node->id)->where('company_id', $companyId)->where('department_id', $departmentId)
                        ->whereNull('sort_order')->update(['sort_order' => ($index + 1) * 10]);
                    $updated++;
                }
                $groups++;
            }
            $result = ['total' => $nodes->count(), 'initialized_nodes' => $updated, 'initialized_groups' => $groups, 'skipped_groups' => $skipped, 'dry_run' => $dryRun];
            if ($dryRun) $db->rollBack(); else $db->commit();
            return $result;
        } catch (\Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }
}
