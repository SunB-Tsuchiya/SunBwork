<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesClientGroup;
use App\Models\Sales\SalesClientGroupMember;
use App\Models\Sales\SalesImport;
use App\Models\Sales\SalesOrder;
use App\Services\SalesAnalysis\ClientGroupService;
use App\Services\SalesAnalysis\SalesImportService;
use App\Services\SalesAnalysis\SalesQueryService;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

class SalesClientGroupServiceTest extends TestCase
{
    use RefreshesSalesDatabase;

    private static int $importSeq = 0;

    private function seedMonth(string $dept, int $year, int $month, array $orders): void
    {
        self::$importSeq++;

        $import = SalesImport::create([
            'department_key' => $dept,
            'source_type' => 'monthly',
            'source_year' => $year,
            'source_month' => $month,
            'version' => 1,
            'original_filename' => 'seed.xlsx',
            'file_sha256' => hash('sha256', "cg-seed-{$dept}-{$year}-{$month}-" . self::$importSeq),
            'status' => 'completed',
            'imported_by' => 1,
            'imported_at' => now(),
            'order_count' => count($orders),
            'detail_count' => count($orders),
            'total_amount' => array_sum(array_column($orders, 'amount')),
        ]);

        foreach ($orders as $o) {
            SalesOrder::create([
                'sales_import_id' => $import->id,
                'order_number' => $o['order_number'],
                'client_name' => $o['client_name'],
                'product_name' => $o['product_name'] ?? '商品',
                'plate_date' => sprintf('%04d-%02d-15', $year, $month),
                'sales_year' => $year,
                'sales_month' => $month,
                'order_amount' => $o['amount'],
            ]);
        }

        SalesActiveMonth::updateOrCreate(
            ['department_key' => $dept, 'sales_year' => $year, 'sales_month' => $month],
            ['sales_import_id' => $import->id, 'activated_by' => 1, 'activated_at' => now()]
        );
    }

    private function service(): ClientGroupService
    {
        return new ClientGroupService();
    }

    private function queryService(): SalesQueryService
    {
        return new SalesQueryService(new SalesImportService());
    }

    public function test_unassigned_clients_excludes_grouped_names()
    {
        $this->seedMonth('planning', 2026, 1, [
            ['order_number' => 'CG-1', 'client_name' => 'A社', 'amount' => 1000],
            ['order_number' => 'CG-2', 'client_name' => 'B社', 'amount' => 2000],
        ]);

        $group = SalesClientGroup::create(['name' => 'A社グループ', 'created_by' => 1, 'updated_by' => 1]);
        SalesClientGroupMember::create(['sales_client_group_id' => $group->id, 'client_name' => 'A社', 'normalized_name' => 'A社']);

        $unassigned = collect($this->service()->unassignedClients())->pluck('client_name');

        $this->assertNotContains('A社', $unassigned);
        $this->assertContains('B社', $unassigned);
    }

    public function test_candidates_groups_by_normalized_form_and_excludes_singletons()
    {
        $this->seedMonth('planning', 2026, 1, [
            ['order_number' => 'CG-3', 'client_name' => '株式会社サンプル', 'amount' => 1000],
            ['order_number' => 'CG-4', 'client_name' => '株式会社 サンプル', 'amount' => 500],
            ['order_number' => 'CG-5', 'client_name' => 'ユニーク商事', 'amount' => 300],
        ]);

        $candidates = $this->service()->candidates();

        $this->assertCount(1, $candidates);
        $this->assertEqualsCanonicalizing(
            ['株式会社サンプル', '株式会社 サンプル'],
            $candidates[0]['client_names']
        );
    }

    public function test_preview_aggregates_amount_and_departments_without_saving()
    {
        $this->seedMonth('planning', 2026, 1, [['order_number' => 'CG-6', 'client_name' => 'C社（2）', 'amount' => 1000]]);
        $this->seedMonth('production', 2026, 1, [['order_number' => 'CG-7', 'client_name' => 'C社（3）', 'amount' => 1500]]);

        $preview = $this->service()->preview(['C社（2）', 'C社（3）']);

        $this->assertSame(2500.0, $preview['total_amount']);
        $this->assertEqualsCanonicalizing(['planning', 'production'], $preview['departments']);
        $this->assertDatabaseCount('sales_client_groups', 0, 'sales');
    }

    public function test_groups_returns_members_with_normalized_name()
    {
        $group = SalesClientGroup::create(['name' => '株式会社NON', 'created_by' => 1, 'updated_by' => 1]);
        SalesClientGroupMember::create(['sales_client_group_id' => $group->id, 'client_name' => '株式会社NON（2）', 'normalized_name' => 'x']);

        $groups = $this->service()->groups();

        $this->assertCount(1, $groups);
        $this->assertSame('株式会社NON', $groups[0]['name']);
        $this->assertSame('株式会社NON（2）', $groups[0]['members'][0]['client_name']);
    }

    public function test_client_ranking_for_period_spans_multiple_years_and_keeps_share_denominator_before_keyword_filter()
    {
        $this->seedMonth('planning', 2024, 12, [['order_number' => 'CR-1', 'client_name' => 'A社', 'amount' => 1000]]);
        $this->seedMonth('planning', 2025, 1, [
            ['order_number' => 'CR-2', 'client_name' => 'A社', 'amount' => 500],
            ['order_number' => 'CR-3', 'client_name' => 'B社', 'amount' => 2000],
        ]);

        $result = $this->queryService()->clientRankingForPeriod('planning', 2024, 12, 2025, 1, false);

        $this->assertSame(3500.0, $result['total_amount']);
        $a = collect($result['ranking'])->firstWhere('client_name', 'A社');
        $this->assertSame(1500.0, $a['amount']); // 1000+500（年またぎ合算）

        $filtered = $this->queryService()->clientRankingForPeriod('planning', 2024, 12, 2025, 1, false, 'A社');
        $this->assertCount(1, $filtered['ranking']);
        // share_pctはキーワード絞り込み前の合計を分母にする
        $this->assertSame(round(1500 / 3500 * 100, 1), $filtered['ranking'][0]['share_pct']);
    }

    public function test_client_ranking_for_period_consolidates_when_flag_true()
    {
        $group = SalesClientGroup::create(['name' => '株式会社NON', 'created_by' => 1, 'updated_by' => 1]);
        SalesClientGroupMember::create(['sales_client_group_id' => $group->id, 'client_name' => '株式会社NON（2）', 'normalized_name' => 'x']);
        SalesClientGroupMember::create(['sales_client_group_id' => $group->id, 'client_name' => '株式会社NON（3）', 'normalized_name' => 'y']);

        $this->seedMonth('planning', 2026, 3, [
            ['order_number' => 'CR-4', 'client_name' => '株式会社NON（2）', 'amount' => 1000],
            ['order_number' => 'CR-5', 'client_name' => '株式会社NON（3）', 'amount' => 2000],
        ]);

        $result = $this->queryService()->clientRankingForPeriod('planning', 2026, 3, 2026, 3, true);

        $this->assertCount(1, $result['ranking']);
        $this->assertSame('株式会社NON', $result['ranking'][0]['client_name']);
        $this->assertSame(3000.0, $result['ranking'][0]['amount']);
    }

    /** REVIEW3 14章Priority A対応（Phase 15）: 得意先分析のTop10/20＋全件詳細ドロワー契約 */
    public function test_client_analysis_panel_paginates_and_matches_ranking_for_period_totals()
    {
        $this->seedMonth('planning', 2024, 12, [['order_number' => 'CP-1', 'client_name' => 'A社', 'amount' => 1000]]);
        $this->seedMonth('planning', 2025, 1, [
            ['order_number' => 'CP-2', 'client_name' => 'A社', 'amount' => 500],
            ['order_number' => 'CP-3', 'client_name' => 'B社', 'amount' => 2000],
        ]);

        $panel = $this->queryService()->clientAnalysisPanel('planning', 2024, 12, 2025, 1, false, null, 'amount', 'desc', 1, 1);

        $this->assertSame(3500.0, $panel['total_amount']);
        $this->assertSame(2, $panel['total_count']);
        $this->assertCount(1, $panel['rows']); // limit=1
        $this->assertSame('B社', $panel['rows'][0]['label']); // 金額降順
    }

    public function test_client_detail_computes_yearly_trend_with_null_for_unregistered_years()
    {
        $this->seedMonth('planning', 2024, 5, [['order_number' => 'CD-1', 'client_name' => 'A社', 'amount' => 1000]]);
        $this->seedMonth('planning', 2026, 5, [['order_number' => 'CD-2', 'client_name' => 'A社', 'amount' => 1500]]);

        $detail = $this->queryService()->clientDetail('planning', 'A社', 2024, 1, 2026, 12, false);
        $yearly = collect($detail['yearly'])->keyBy('year');

        $this->assertSame(1000.0, $yearly[2024]['amount']);
        $this->assertNull($yearly[2025]['amount']); // 2025年は部署自体が未登録
        $this->assertSame(1500.0, $yearly[2026]['amount']);
        // 直前年(2025)がnullのため2026の前年差は比較不可
        $this->assertNull($yearly[2026]['prior_year_diff']);
    }

    public function test_client_detail_uses_group_name_to_resolve_raw_names_when_consolidated()
    {
        $group = SalesClientGroup::create(['name' => '株式会社NON', 'created_by' => 1, 'updated_by' => 1]);
        SalesClientGroupMember::create(['sales_client_group_id' => $group->id, 'client_name' => '株式会社NON（2）', 'normalized_name' => 'x']);
        SalesClientGroupMember::create(['sales_client_group_id' => $group->id, 'client_name' => '株式会社NON（3）', 'normalized_name' => 'y']);

        $this->seedMonth('planning', 2026, 4, [
            ['order_number' => 'CD-3', 'client_name' => '株式会社NON（2）', 'amount' => 1000],
            ['order_number' => 'CD-4', 'client_name' => '株式会社NON（3）', 'amount' => 2000],
        ]);

        $detail = $this->queryService()->clientDetail('planning', '株式会社NON', 2026, 1, 2026, 12, true);

        $this->assertSame(3000.0, $detail['yearly'][0]['amount']);
        $this->assertCount(2, $detail['orders']);
    }
}
