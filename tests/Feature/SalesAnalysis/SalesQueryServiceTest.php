<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesClientGroup;
use App\Models\Sales\SalesClientGroupMember;
use App\Models\Sales\SalesImport;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderDetail;
use App\Services\SalesAnalysis\SalesImportService;
use App\Services\SalesAnalysis\SalesQueryService;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

class SalesQueryServiceTest extends TestCase
{
    use RefreshesSalesDatabase;

    private static int $importSeq = 0;

    /**
     * 架空データで1ヶ月分の取込＋active化を行う。
     * $orders: [['order_number', 'client_name', 'amount', 'category', 'item_name'], ...]
     */
    private function seedMonth(string $dept, int $year, int $month, array $orders): SalesImport
    {
        self::$importSeq++;

        $import = SalesImport::create([
            'department_key' => $dept,
            'source_type' => 'monthly',
            'source_year' => $year,
            'source_month' => $month,
            'version' => 1,
            'original_filename' => 'seed.xlsx',
            'file_sha256' => hash('sha256', "seed-{$dept}-{$year}-{$month}-" . self::$importSeq),
            'status' => 'completed',
            'imported_by' => 1,
            'imported_at' => now(),
            'order_count' => count($orders),
            'detail_count' => count($orders),
            'total_amount' => array_sum(array_column($orders, 'amount')),
        ]);

        foreach ($orders as $o) {
            $order = SalesOrder::create([
                'sales_import_id' => $import->id,
                'order_number' => $o['order_number'],
                'client_name' => $o['client_name'],
                'product_name' => $o['product_name'] ?? '商品',
                'plate_date' => sprintf('%04d-%02d-15', $year, $month),
                'sales_year' => $year,
                'sales_month' => $month,
                'order_amount' => $o['amount'],
            ]);

            SalesOrderDetail::create([
                'sales_order_id' => $order->id,
                'source_row_number' => 1,
                'client_name' => $o['client_name'],
                'product_name' => $o['product_name'] ?? '商品',
                'category' => $o['category'] ?? '組版',
                'item_name' => $o['item_name'] ?? '新規',
                'format_size' => 'A4',
                'color_count' => 1,
                'quantity' => 1,
                'unit_price' => $o['amount'],
                'line_amount' => $o['amount'],
                'order_amount_component' => $o['amount'],
                'plate_date' => sprintf('%04d-%02d-15', $year, $month),
            ]);
        }

        // updateOrCreate: 同一部署・年月への再取込（needs_review検証など）で2回目のseedMonthが
        // ユニーク制約違反にならないようにする
        SalesActiveMonth::updateOrCreate(
            ['department_key' => $dept, 'sales_year' => $year, 'sales_month' => $month],
            ['sales_import_id' => $import->id, 'activated_by' => 1, 'activated_at' => now()]
        );

        return $import;
    }

    private function service(): SalesQueryService
    {
        return new SalesQueryService(new SalesImportService());
    }

    public function test_monthly_total_is_null_for_uningested_month()
    {
        $this->assertNull($this->service()->monthlyTotal('planning', 2026, 5));
    }

    public function test_monthly_total_returns_correct_amount()
    {
        $this->seedMonth('planning', 2026, 9, [
            ['order_number' => 'A1', 'client_name' => 'X社', 'amount' => 1000],
            ['order_number' => 'A2', 'client_name' => 'Y社', 'amount' => 2000],
        ]);

        $total = $this->service()->monthlyTotal('planning', 2026, 9);

        $this->assertSame(2, $total['order_count']);
        $this->assertSame(3000.0, $total['total_amount']);
        $this->assertSame(1500.0, $total['average_amount']);
    }

    public function test_monthly_comparison_returns_null_rate_when_base_is_zero_or_missing()
    {
        // 前月は0円で取込済み、前年同月は未取込
        $this->seedMonth('planning', 2026, 8, []);
        $this->seedMonth('planning', 2026, 9, [
            ['order_number' => 'B1', 'client_name' => 'X社', 'amount' => 1000],
        ]);

        $comparison = $this->service()->monthlyComparison('planning', 2026, 9);

        $this->assertSame(1000.0, $comparison['current']['total_amount']);
        $this->assertSame(0.0, $comparison['previous']['total_amount']);
        $this->assertNull($comparison['vs_previous']['rate']); // 分母0はnull
        $this->assertNull($comparison['previous_year_same_month']); // 未取込
        $this->assertNull($comparison['vs_previous_year']['diff']);
        $this->assertNull($comparison['vs_previous_year']['rate']);
    }

    public function test_fiscal_year_to_date_calendar_mode()
    {
        $this->seedMonth('planning', 2026, 1, [['order_number' => 'C1', 'client_name' => 'X', 'amount' => 1000]]);
        $this->seedMonth('planning', 2026, 2, [['order_number' => 'C2', 'client_name' => 'X', 'amount' => 2000]]);
        $this->seedMonth('planning', 2025, 1, [['order_number' => 'C3', 'client_name' => 'X', 'amount' => 500]]);

        $result = $this->service()->fiscalYearToDate('planning', 2026, 2, SalesQueryService::FISCAL_MODE_CALENDAR);

        $this->assertSame(2026, $result['fiscal_year']);
        $this->assertSame(3000.0, $result['current']['total_amount']);
        $this->assertSame(500.0, $result['previous']['total_amount']);
        $this->assertEqualsWithDelta(500.0, $result['rate'], 0.01); // (3000-500)/500*100
    }

    public function test_fiscal_year_to_date_april_mode_spans_previous_calendar_year()
    {
        // 2026年度（2026/4〜2027/3）の途中2027年1月時点
        $this->seedMonth('planning', 2026, 4, [['order_number' => 'D1', 'client_name' => 'X', 'amount' => 1000]]);
        $this->seedMonth('planning', 2027, 1, [['order_number' => 'D2', 'client_name' => 'X', 'amount' => 2000]]);
        // 前年度（2025年度: 2025/4〜2026/3）の対応範囲
        $this->seedMonth('planning', 2025, 4, [['order_number' => 'D3', 'client_name' => 'X', 'amount' => 100]]);

        $result = $this->service()->fiscalYearToDate('planning', 2027, 1, SalesQueryService::FISCAL_MODE_APRIL);

        $this->assertSame(2026, $result['fiscal_year']); // 開始年を年度名として採用
        $this->assertSame(3000.0, $result['current']['total_amount']);
        $this->assertSame(100.0, $result['previous']['total_amount']);
    }

    public function test_monthly_trend_marks_uningested_months_as_null()
    {
        $this->seedMonth('planning', 2026, 9, [['order_number' => 'E1', 'client_name' => 'X', 'amount' => 1000]]);

        $trend = $this->service()->monthlyTrend('planning', 2026, 9, 1); // 直近1年

        $septEntry = collect($trend)->firstWhere(fn ($m) => $m['year'] === 2026 && $m['month'] === 9);
        $augEntry = collect($trend)->firstWhere(fn ($m) => $m['year'] === 2026 && $m['month'] === 8);

        $this->assertSame(1000.0, $septEntry['total_amount']);
        $this->assertNull($augEntry['total_amount']);
        $this->assertCount(12, $trend);
    }

    public function test_client_ranking_without_consolidation_groups_by_raw_name()
    {
        $this->seedMonth('planning', 2026, 9, [
            ['order_number' => 'F1', 'client_name' => '株式会社NON（2）', 'amount' => 1000],
            ['order_number' => 'F2', 'client_name' => '株式会社NON（3）', 'amount' => 2000],
        ]);

        $ranking = $this->service()->clientRanking('planning', 2026, 9, false);

        $this->assertCount(2, $ranking['ranking']);
        $this->assertSame(3000.0, $ranking['total_amount']);
    }

    public function test_client_ranking_with_consolidation_merges_grouped_clients()
    {
        $group = SalesClientGroup::create(['name' => '株式会社NON', 'created_by' => 1, 'updated_by' => 1]);
        SalesClientGroupMember::create(['sales_client_group_id' => $group->id, 'client_name' => '株式会社NON（2）', 'normalized_name' => 'non']);
        SalesClientGroupMember::create(['sales_client_group_id' => $group->id, 'client_name' => '株式会社NON（3）', 'normalized_name' => 'non']);

        $this->seedMonth('planning', 2026, 9, [
            ['order_number' => 'G1', 'client_name' => '株式会社NON（2）', 'amount' => 1000],
            ['order_number' => 'G2', 'client_name' => '株式会社NON（3）', 'amount' => 2000],
            ['order_number' => 'G3', 'client_name' => '未所属商事', 'amount' => 500],
        ]);

        $ranking = $this->service()->clientRanking('planning', 2026, 9, true);

        $this->assertCount(2, $ranking['ranking']); // NON統合 + 未所属商事
        $merged = collect($ranking['ranking'])->firstWhere('name', '株式会社NON');
        $this->assertSame(3000.0, $merged['amount']);
    }

    public function test_category_and_item_breakdown()
    {
        $this->seedMonth('planning', 2026, 9, [
            ['order_number' => 'H1', 'client_name' => 'X', 'amount' => 1000, 'category' => '組版', 'item_name' => '新規'],
            ['order_number' => 'H2', 'client_name' => 'X', 'amount' => 2000, 'category' => 'デザイン制作', 'item_name' => 'その他'],
        ]);

        $categories = $this->service()->categoryBreakdown('planning', 2026, 9);
        $items = $this->service()->itemBreakdown('planning', 2026, 9);

        $this->assertSame(3000.0, $categories['total_amount']);
        $this->assertCount(2, $categories['breakdown']);
        $this->assertCount(2, $items['breakdown']);
    }

    public function test_search_by_product_name_matches_partial_keyword()
    {
        $this->seedMonth('planning', 2026, 9, [
            ['order_number' => 'I1', 'client_name' => 'X', 'amount' => 1000, 'product_name' => '記述力模試　制作代'],
            ['order_number' => 'I2', 'client_name' => 'X', 'amount' => 2000, 'product_name' => '別の商品'],
        ]);

        $results = $this->service()->searchByProductName('planning', 2026, 9, '模試');

        $this->assertCount(1, $results);
        $this->assertSame('I1', $results[0]['order_number']);
    }

    public function test_only_active_version_is_counted_after_reimport()
    {
        $old = $this->seedMonth('planning', 2026, 9, [
            ['order_number' => 'J1', 'client_name' => 'X', 'amount' => 1000],
        ]);

        // 再取込で新版に切り替え（旧版データは残るがactiveではなくなる）
        $newImport = SalesImport::create([
            'department_key' => 'planning',
            'source_type' => 'monthly',
            'source_year' => 2026,
            'source_month' => 9,
            'version' => 2,
            'original_filename' => 'seed2.xlsx',
            'file_sha256' => hash('sha256', 'seed-v2'),
            'status' => 'completed',
            'imported_by' => 1,
            'imported_at' => now(),
            'order_count' => 1,
            'detail_count' => 1,
            'total_amount' => 5000,
        ]);
        SalesOrder::create([
            'sales_import_id' => $newImport->id,
            'order_number' => 'J2',
            'client_name' => 'Y',
            'product_name' => '商品',
            'plate_date' => '2026-09-15',
            'sales_year' => 2026,
            'sales_month' => 9,
            'order_amount' => 5000,
        ]);
        SalesActiveMonth::where('department_key', 'planning')
            ->where('sales_year', 2026)->where('sales_month', 9)
            ->update(['sales_import_id' => $newImport->id]);

        $total = $this->service()->monthlyTotal('planning', 2026, 9);

        $this->assertSame(5000.0, $total['total_amount']);
        $this->assertSame(1, $total['order_count']);
        // 旧版のデータはactive集計に含まれない
        $this->assertDatabaseHas('sales_orders', ['id' => SalesOrder::where('order_number', 'J1')->first()->id], 'sales');
    }

    public function test_annual_summary_full_year_uses_all_twelve_months_for_comparison()
    {
        for ($m = 1; $m <= 12; $m++) {
            $this->seedMonth('planning', 2024, $m, [['order_number' => "P24-{$m}", 'client_name' => 'A社', 'amount' => 1000]]);
            $this->seedMonth('planning', 2025, $m, [['order_number' => "P25-{$m}", 'client_name' => 'A社', 'amount' => 1200]]);
        }

        $summary = $this->service()->annualSummary('planning', 2025);

        $this->assertSame('full', $summary['comparison_mode']);
        $this->assertSame([1, 12], $summary['comparison_month_range']);
        $this->assertSame(12, $summary['months_registered']);
        $this->assertSame(14400.0, $summary['kpi']['period_amount']); // 1200 * 12
        $this->assertSame(12000.0, $summary['kpi']['prior_period_amount']); // 1000 * 12
        $this->assertSame(12000.0, $summary['kpi']['full_prior_year_amount']); // 満年比較なのでprior_period_amountと一致
        $this->assertCount(12, $summary['monthly']);
        $this->assertSame('has_sales', $summary['monthly'][0]['state']);
    }

    public function test_annual_summary_partial_year_compares_same_registered_months_only()
    {
        // 進行中の年を想定: 1〜3月のみ登録済み
        for ($m = 1; $m <= 3; $m++) {
            $this->seedMonth('planning', 2025, $m, [['order_number' => "Q-{$m}", 'client_name' => 'A社', 'amount' => 1000]]);
        }
        // 前年は12ヶ月分登録済み（年間合計は参考情報としてのみ使う）
        for ($m = 1; $m <= 12; $m++) {
            $this->seedMonth('planning', 2024, $m, [['order_number' => "R-{$m}", 'client_name' => 'A社', 'amount' => 900]]);
        }

        $summary = $this->service()->annualSummary('planning', 2025);

        $this->assertSame('partial', $summary['comparison_mode']);
        $this->assertSame([1, 3], $summary['comparison_month_range']);
        $this->assertSame(3, $summary['months_registered']);
        $this->assertSame(3000.0, $summary['kpi']['period_amount']); // 1000 * 3
        $this->assertSame(2700.0, $summary['kpi']['prior_period_amount']); // 900 * 3（1〜3月のみ）
        $this->assertSame(10800.0, $summary['kpi']['full_prior_year_amount']); // 900 * 12（参考情報）

        $months = collect($summary['monthly'])->keyBy('month');
        $this->assertSame('no_data', $months[4]['state']);
        $this->assertNull($months[4]['amount']);
    }

    public function test_annual_summary_all_departments_aggregates_without_deduplicating_order_count()
    {
        $this->seedMonth('planning', 2025, 5, [['order_number' => 'SAME-NO', 'client_name' => 'A社', 'amount' => 1000]]);
        $this->seedMonth('production', 2025, 5, [['order_number' => 'SAME-NO', 'client_name' => 'A社', 'amount' => 2000]]);
        $this->seedMonth('ondemand', 2025, 5, [['order_number' => 'OD-1', 'client_name' => 'B社', 'amount' => 500]]);

        $summary = $this->service()->annualSummary('all', 2025);

        // 同じ受注Noが複数部署にまたがっても名寄せせず、各部署の金額・件数をそのまま合算する
        $this->assertSame(3500.0, $summary['kpi']['period_amount']);
        $this->assertSame(3, $summary['kpi']['order_count']);

        // 得意先ランキングは得意先名で部署横断合算する（ユーザー確認済み仕様）
        $clientA = collect($summary['top_clients'])->firstWhere('client_name', 'A社');
        $this->assertSame(3000.0, $clientA['amount']);
    }

    public function test_annual_summary_flags_needs_review_and_has_issue()
    {
        $this->seedMonth('planning', 2025, 6, [['order_number' => 'NR-1', 'client_name' => 'A社', 'amount' => 1000]]);
        $this->travel(1)->seconds();
        $this->seedMonth('planning', 2025, 6, [['order_number' => 'NR-2', 'client_name' => 'A社', 'amount' => 1200]]);

        $summary = $this->service()->annualSummary('planning', 2025);
        $months = collect($summary['monthly'])->keyBy('month');

        $this->assertTrue($months[6]['needs_review']);
    }

    public function test_search_by_product_name_for_year_matches_across_registered_months()
    {
        $this->seedMonth('planning', 2025, 2, [['order_number' => 'PR-1', 'client_name' => 'A社', 'amount' => 1000, 'product_name' => '名刺セットA']]);
        $this->seedMonth('planning', 2025, 9, [['order_number' => 'PR-2', 'client_name' => 'B社', 'amount' => 2000, 'product_name' => '名刺セットB']]);
        $this->seedMonth('planning', 2025, 3, [['order_number' => 'PR-3', 'client_name' => 'C社', 'amount' => 3000, 'product_name' => '封筒']]);

        $results = $this->service()->searchByProductNameForYear('planning', 2025, '名刺');

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing(['PR-1', 'PR-2'], array_column($results, 'order_number'));
    }
}
