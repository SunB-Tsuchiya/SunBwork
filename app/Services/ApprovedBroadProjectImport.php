<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class ApprovedBroadProjectImport
{
    public function __construct(private ?string $catalogPath = null) {}

    public function run(int $companyId, int $departmentId, bool $dryRun, string $connection = 'mysql'): array
    {
        $db = DB::connection($connection);
        $catalog = json_decode(file_get_contents($this->catalogPath ?? database_path('data/broad-project-classifications-20261031.json')), true, 512, JSON_THROW_ON_ERROR);
        $db->beginTransaction();
        try {
            $department = $db->table('departments')->where('id', $departmentId)->where('company_id', $companyId)
                ->where('code', 'INFO')->lockForUpdate()->first();
            if (! $department || ! $db->table('companies')->where('id', $companyId)->where('company_type', '!=', 'general')->exists()) {
                throw new RuntimeException('指定した会社/部署は対象の情報出版ではありません。');
            }
            $ids = []; $created = 0; $adopted = 0; $reused = 0;
            foreach ($catalog as $item) {
                $parentId = $item['parent_key'] ? ($ids[$item['parent_key']] ?? throw new RuntimeException('親の登録順が不正です。')) : null;
                $scope = fn () => $db->table('broad_projects')->where('company_id', $companyId)->where('department_id', $departmentId);
                $node = $scope()->where('import_key', $item['key'])->first();
                if ($node) {
                    $ids[$item['key']] = $node->id; $reused++;
                    continue; // Preserve every web edit, including the parent and active state.
                }
                if ($parentId && ! $scope()->where('id', $parentId)->whereNull('parent_id')->exists()) {
                    throw new RuntimeException('中グループは大グループの中にだけ登録できます。');
                }
                $matches = $scope()->whereNull('import_key')->where('parent_id', $parentId)->where('name', $item['name'])
                    ->whereNull('client_id')->whereNull('fiscal_year')->get();
                if ($matches->count() > 1) throw new RuntimeException('同名の既存候補が複数あります。登録を中止しました。');
                if ($matches->count() === 1) {
                    $id = $matches[0]->id;
                    $scope()->where('id', $id)->update(['import_key' => $item['key']]);
                    $adopted++;
                } else {
                    $id = $db->table('broad_projects')->insertGetId([
                        'company_id' => $companyId, 'department_id' => $departmentId,
                        'name' => $item['name'], 'parent_id' => $parentId, 'client_id' => null, 'fiscal_year' => null,
                        'import_key' => $item['key'], 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $created++;
                }
                $ids[$item['key']] = $id;
            }
            $result = ['catalog_nodes' => count($catalog), 'created' => $created, 'adopted' => $adopted, 'reused' => $reused, 'dry_run' => $dryRun];
            if ($dryRun) $db->rollBack(); else $db->commit();
            return $result;
        } catch (\Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }
}
