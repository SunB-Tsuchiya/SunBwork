<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Sales\SalesDepartmentDefinition;
use Illuminate\Database\Seeder;

/**
 * 会社別データ分離（2026-09-05）。売上分析の部署区分は会社ごとに異なるため
 * （サン・ブレーン=企画/制作/オンデマンドの制作ライン区分、サンエー印刷=全社単一区分）、
 * companiesテーブルから会社を特定して投入する。updateOrCreateで再実行しても安全。
 * サン・ブレーンはcode='SUNBRAIN'で判定する（company_typeはCompanySeeder経由の再構築だと
 * 'general'のままになる環境があり確実でないため）。サンエー印刷はまだ専用のcompany_type/codeが
 * 無いため会社名で特定する（未登録環境では単にスキップする）。
 */
class SalesDepartmentDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $sunbrain = Company::where('code', 'SUNBRAIN')->first();
        if ($sunbrain) {
            $this->upsert($sunbrain->id, [
                ['key' => 'planning', 'label' => '企画', 'sort_order' => 1],
                ['key' => 'production', 'label' => '制作', 'sort_order' => 2],
                ['key' => 'ondemand', 'label' => 'オンデマンド', 'sort_order' => 3],
            ]);
        }

        $sunA = Company::where('name', '株式会社サンエー印刷')->first();
        if ($sunA) {
            $this->upsert($sunA->id, [
                ['key' => 'general', 'label' => '全社', 'sort_order' => 1],
            ]);
        }
    }

    private function upsert(int $companyId, array $rows): void
    {
        foreach ($rows as $row) {
            SalesDepartmentDefinition::updateOrCreate(
                ['company_id' => $companyId, 'key' => $row['key']],
                ['label' => $row['label'], 'sort_order' => $row['sort_order']]
            );
        }
    }
}
