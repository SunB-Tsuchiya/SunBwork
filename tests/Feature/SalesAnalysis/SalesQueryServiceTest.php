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
            'company_id' => $this->salesTestCompanyId(),
            'company_id' => $this->salesTestCompanyId(),
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
            ['company_id' => $this->salesTestCompanyId(), 'department_key' => $dept, 'sales_year' => $year, 'sales_month' => $month],
            ['sales_import_id' => $import->id, 'activated_by' => 1, 'activated_at' => now()]
        );

        return $import;
    }

    private function service(): SalesQueryService
    {
        return (new SalesQueryService(new SalesImportService()))->forCompany($this->salesTestCompanyId());
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

    /** REVIEW3 13.2-B対応（Phase 12）: 直近Nヶ月＋3ヶ月移動平均 */
    public function test_recent_monthly_trend_computes_three_month_moving_average()
    {
        foreach ([6, 7, 8, 9] as $m) {
            $this->seedMonth('planning', 2026, $m, [['order_number' => "MA-{$m}", 'client_name' => 'X', 'amount' => 300]]);
        }

        $trend = $this->service()->recentMonthlyTrend('planning', 2026, 9, 6);

        $this->assertCount(6, $trend);
        $sep = collect($trend)->firstWhere(fn ($m) => $m['year'] === 2026 && $m['month'] === 9);
        $jun = collect($trend)->firstWhere(fn ($m) => $m['year'] === 2026 && $m['month'] === 6);
        $this->assertSame(300.0, $sep['moving_avg_3m']); // 7・8・9月がすべて300円なので平均も300円
        $this->assertNull($jun['moving_avg_3m']); // 直前2ヶ月分のデータが範囲外で算出不可
    }

    /** REVIEW3 13.2-C対応（Phase 12）: 選択月だけを対象とした複数年推移（未登録年はnull） */
    public function test_same_month_across_years_marks_unregistered_years_as_null()
    {
        $this->seedMonth('planning', 2024, 9, [['order_number' => 'SM-24', 'client_name' => 'X', 'amount' => 500]]);
        $this->seedMonth('planning', 2026, 9, [['order_number' => 'SM-26', 'client_name' => 'X', 'amount' => 700]]);

        $rows = $this->service()->sameMonthAcrossYears('planning', 9, 2026, 3);

        $this->assertSame([2024, 2025, 2026], collect($rows)->pluck('year')->all());
        $this->assertSame(500.0, $rows[0]['amount']);
        $this->assertNull($rows[1]['amount']);
        $this->assertSame(700.0, $rows[2]['amount']);
    }

    /** REVIEW3 13.1対応（Phase 12）: 期間ナビゲーターの未登録月案内・最新登録月ジャンプ */
    public function test_nearest_registered_months_and_latest_registered_month()
    {
        $this->seedMonth('planning', 2026, 6, [['order_number' => 'NB-1', 'client_name' => 'X', 'amount' => 100]]);
        $this->seedMonth('planning', 2026, 9, [['order_number' => 'NB-2', 'client_name' => 'X', 'amount' => 200]]);

        $status = $this->service()->nearestRegisteredMonths('planning', 2026, 8);
        $this->assertFalse($status['has_data']);
        $this->assertSame(['year' => 2026, 'month' => 6], $status['nearest_before']);
        $this->assertSame(['year' => 2026, 'month' => 9], $status['nearest_after']);

        $onData = $this->service()->nearestRegisteredMonths('planning', 2026, 9);
        $this->assertTrue($onData['has_data']);

        $latest = $this->service()->latestRegisteredMonth('planning');
        $this->assertSame(['year' => 2026, 'month' => 9], $latest);
        $this->assertNull($this->service()->latestRegisteredMonth('production'));
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
        $group = SalesClientGroup::create(['company_id' => $this->salesTestCompanyId(), 'name' => '株式会社NON', 'created_by' => 1, 'updated_by' => 1]);
        SalesClientGroupMember::create(['sales_client_group_id' => $group->id, 'company_id' => $this->salesTestCompanyId(), 'client_name' => '株式会社NON（2）', 'normalized_name' => 'non']);
        SalesClientGroupMember::create(['sales_client_group_id' => $group->id, 'company_id' => $this->salesTestCompanyId(), 'client_name' => '株式会社NON（3）', 'normalized_name' => 'non']);

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

    /** REVIEW3 13.2-D対応（Phase 12）: 得意先パネルのvs_previous_yearモード（発散棒グラフ用の差額） */
    public function test_monthly_client_panel_vs_previous_year_mode_computes_diff()
    {
        $this->seedMonth('planning', 2025, 9, [['order_number' => 'CP-25', 'client_name' => 'A社', 'amount' => 1000]]);
        $this->seedMonth('planning', 2026, 9, [
            ['order_number' => 'CP-26A', 'client_name' => 'A社', 'amount' => 1500],
            ['order_number' => 'CP-26B', 'client_name' => 'B社', 'amount' => 800],
        ]);

        $panel = $this->service()->monthlyClientPanel('planning', 2026, 9, 'vs_previous_year', false, null, 'amount', 'desc', 10, 1);

        $a = collect($panel['rows'])->firstWhere('label', 'A社');
        $b = collect($panel['rows'])->firstWhere('label', 'B社');
        $this->assertSame(500.0, $a['diff']); // 1500 - 1000
        $this->assertSame(800.0, $b['diff']); // 前年同月は0円扱い（新規）
        $this->assertSame(2, $panel['total_count']);
    }

    /** REVIEW3 15.1節対応（Phase 12）: 内訳パネルのキーワード絞り込み・ページング */
    public function test_monthly_breakdown_panel_supports_keyword_and_paging()
    {
        $this->seedMonth('planning', 2026, 9, [
            ['order_number' => 'BP-1', 'client_name' => 'X', 'amount' => 1000, 'category' => '印刷'],
            ['order_number' => 'BP-2', 'client_name' => 'X', 'amount' => 500, 'category' => '製本'],
        ]);

        $filtered = $this->service()->monthlyBreakdownPanel('planning', 2026, 9, 'category', '印刷', 'amount', 'desc', 10, 1);
        $this->assertCount(1, $filtered['rows']);
        $this->assertSame('印刷', $filtered['rows'][0]['label']);

        $paged = $this->service()->monthlyBreakdownPanel('planning', 2026, 9, 'category', null, 'amount', 'desc', 1, 1);
        $this->assertCount(1, $paged['rows']);
        $this->assertSame(2, $paged['total_count']);
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
            'company_id' => $this->salesTestCompanyId(),
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

    /**
     * REVIEW3 16.1節回帰テスト: 年の途中に欠落月（3月が無い）があると、登録月数・対象月・
     * 欠落月が正しく返る（`months_registered`は「最後に登録された月」ではなく「登録済み件数」）。
     */
    public function test_annual_summary_reports_registered_and_missing_months_when_gap_exists()
    {
        foreach ([1, 2, 4] as $m) {
            $this->seedMonth('planning', 2025, $m, [['order_number' => "G-{$m}", 'client_name' => 'A社', 'amount' => 1000]]);
        }

        $summary = $this->service()->annualSummary('planning', 2025);

        $this->assertSame([1, 2, 4], $summary['registered_months']);
        $this->assertSame([3], $summary['missing_months']);
        $this->assertSame(3, $summary['months_registered']); // 件数（4ではない）
        $this->assertSame(4, $summary['last_registered_month']);
        // 確定方針（ユーザー確認済み・2026-09-04）: 欠落があっても実データはそのまま合算する
        $this->assertSame(3000.0, $summary['kpi']['period_amount']); // 1000 * 3（1・2・4月分）
    }

    /**
     * REVIEW3 16.1節回帰テスト: 「全部署合計」で1部署だけ未登録の月は`coverage.is_complete`が
     * falseになり、完全登録に見えない（金額はそのまま合算する）。
     */
    public function test_annual_summary_flags_incomplete_department_coverage_for_all_departments()
    {
        // 5月: 3部署すべて登録済み（完全）
        $this->seedMonth('planning', 2025, 5, [['order_number' => 'CV-P5', 'client_name' => 'A社', 'amount' => 1000]]);
        $this->seedMonth('production', 2025, 5, [['order_number' => 'CV-R5', 'client_name' => 'A社', 'amount' => 1000]]);
        $this->seedMonth('ondemand', 2025, 5, [['order_number' => 'CV-O5', 'client_name' => 'A社', 'amount' => 1000]]);
        // 6月: planningのみ登録済み（一部登録）
        $this->seedMonth('planning', 2025, 6, [['order_number' => 'CV-P6', 'client_name' => 'A社', 'amount' => 500]]);

        $summary = $this->service()->annualSummary('all', 2025);
        $months = collect($summary['monthly'])->keyBy('month');

        $this->assertTrue($months[5]['coverage']['is_complete']);
        $this->assertCount(3, $months[5]['coverage']['registered_departments']);

        $this->assertFalse($months[6]['coverage']['is_complete']);
        $this->assertSame(['planning'], $months[6]['coverage']['registered_departments']);
        // 一部登録でも金額はそのまま合算表示する（確定方針）
        $this->assertSame(500.0, $months[6]['amount']);
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

    public function test_annual_summary_consolidates_top_clients_when_flag_true()
    {
        $group = SalesClientGroup::create(['company_id' => $this->salesTestCompanyId(), 'name' => '株式会社NON', 'created_by' => 1, 'updated_by' => 1]);
        SalesClientGroupMember::create(['sales_client_group_id' => $group->id, 'company_id' => $this->salesTestCompanyId(), 'client_name' => '株式会社NON（2）', 'normalized_name' => 'x']);
        SalesClientGroupMember::create(['sales_client_group_id' => $group->id, 'company_id' => $this->salesTestCompanyId(), 'client_name' => '株式会社NON（3）', 'normalized_name' => 'y']);

        $this->seedMonth('planning', 2025, 4, [
            ['order_number' => 'AS-1', 'client_name' => '株式会社NON（2）', 'amount' => 1000],
            ['order_number' => 'AS-2', 'client_name' => '株式会社NON（3）', 'amount' => 2000],
        ]);

        $off = $this->service()->annualSummary('planning', 2025, false);
        $this->assertCount(2, $off['top_clients']);
        $this->assertFalse($off['consolidate_clients']);

        $on = $this->service()->annualSummary('planning', 2025, true);
        $this->assertCount(1, $on['top_clients']);
        $this->assertSame('株式会社NON', $on['top_clients'][0]['client_name']);
        $this->assertSame(3000.0, $on['top_clients'][0]['amount']);
        $this->assertTrue($on['consolidate_clients']);
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

    /** REVIEW3 13.1節対応（Phase 13）: 年次分析の「最新年」ジャンプ（'all'部署合算にも対応） */
    /** REVIEW3 13.1節対応（Phase 13）: 同月比較の期間ナビゲーター（年を持たず月だけを返す） */
    public function test_latest_registered_month_number_supports_all_departments()
    {
        $this->seedMonth('planning', 2024, 3, [['order_number' => 'LM-1', 'client_name' => 'A社', 'amount' => 100]]);
        $this->seedMonth('production', 2026, 7, [['order_number' => 'LM-2', 'client_name' => 'A社', 'amount' => 200]]);

        $this->assertSame(3, $this->service()->latestRegisteredMonthNumber('planning'));
        $this->assertSame(7, $this->service()->latestRegisteredMonthNumber('all'));
        $this->assertNull($this->service()->latestRegisteredMonthNumber('ondemand'));
    }

    public function test_latest_registered_year_supports_all_departments()
    {
        $this->seedMonth('planning', 2024, 1, [['order_number' => 'LY-1', 'client_name' => 'A社', 'amount' => 100]]);
        $this->seedMonth('production', 2026, 1, [['order_number' => 'LY-2', 'client_name' => 'A社', 'amount' => 200]]);

        $this->assertSame(2024, $this->service()->latestRegisteredYear('planning'));
        $this->assertSame(2026, $this->service()->latestRegisteredYear('all'));
        $this->assertNull($this->service()->latestRegisteredYear('ondemand'));
    }

    /**
     * 実機フィードバック対応（2026-09-04）: 「期別分析」（4月始まり〜翌3月終わり）新設。
     * fiscal_year=2025は2025年4月〜2026年3月を指す。年またぎで正しく集計・月配列が
     * 4月始まりになっているかを検証する。
     */
    public function test_fiscal_year_summary_spans_calendar_years_and_orders_months_from_april()
    {
        $this->seedMonth('planning', 2024, 4, [['order_number' => 'FY-0', 'client_name' => 'A社', 'amount' => 700]]);
        $this->seedMonth('planning', 2025, 4, [['order_number' => 'FY-1', 'client_name' => 'A社', 'amount' => 1000]]);
        $this->seedMonth('planning', 2025, 12, [['order_number' => 'FY-2', 'client_name' => 'A社', 'amount' => 500]]);
        $this->seedMonth('planning', 2026, 2, [['order_number' => 'FY-3', 'client_name' => 'A社', 'amount' => 300]]);

        $summary = $this->service()->fiscalYearSummary('planning', 2025);

        $this->assertSame(2025, $summary['fiscal_year']);
        $this->assertSame(2024, $summary['comparison_fiscal_year']);
        $this->assertSame(['year' => 2025, 'month' => 4], $summary['period_start']);
        $this->assertSame(['year' => 2026, 'month' => 2], $summary['period_end']); // 最終登録=fiscal_month11=2026年2月

        // 月配列は4月始まり（fiscal_month1=4月 … fiscal_month12=翌3月）
        $this->assertSame(1, $summary['monthly'][0]['fiscal_month']);
        $this->assertSame(2025, $summary['monthly'][0]['calendar_year']);
        $this->assertSame(4, $summary['monthly'][0]['calendar_month']);
        $this->assertSame(1000.0, $summary['monthly'][0]['amount']);
        $this->assertSame(700.0, $summary['monthly'][0]['prior_year_amount']);
        $this->assertSame(300.0, $summary['monthly'][0]['diff']);

        // fiscal_month10 = 2026年1月（未登録）
        $this->assertSame(2026, $summary['monthly'][9]['calendar_year']);
        $this->assertSame(1, $summary['monthly'][9]['calendar_month']);
        $this->assertNull($summary['monthly'][9]['amount']);
        // fiscal_month11 = 2026年2月
        $this->assertSame(2, $summary['monthly'][10]['calendar_month']);
        $this->assertSame(300.0, $summary['monthly'][10]['amount']);

        $this->assertSame([1, 9, 11], $summary['registered_months']);
        $this->assertSame(3, $summary['months_registered']);
        $this->assertSame(11, $summary['last_registered_month']);
        $this->assertEqualsCanonicalizing([2, 3, 4, 5, 6, 7, 8, 10], $summary['missing_months']);
        $this->assertEquals(1800.0, $summary['kpi']['period_amount']); // 1000+500+300
    }

    public function test_fiscal_year_client_panel_computes_diff_against_prior_fiscal_year()
    {
        $this->seedMonth('planning', 2024, 4, [['order_number' => 'FYC-24', 'client_name' => 'A社', 'amount' => 1000]]);
        $this->seedMonth('planning', 2025, 4, [
            ['order_number' => 'FYC-25A', 'client_name' => 'A社', 'amount' => 1200],
            ['order_number' => 'FYC-25B', 'client_name' => 'B社', 'amount' => 300],
        ]);

        $panel = $this->service()->fiscalYearClientPanel('planning', 2025, false, null, 'amount', 'desc', 10, 1);

        $a = collect($panel['rows'])->firstWhere('label', 'A社');
        $b = collect($panel['rows'])->firstWhere('label', 'B社');
        $this->assertSame(200.0, $a['diff']); // 1200-1000
        $this->assertSame(300.0, $b['diff']); // 前期は0円扱い（新規）
    }

    /** Codexレビュー指摘の回帰テスト（2026-09-05）: annualClientPanel()と同種の離脱得意先消失バグ */
    public function test_fiscal_year_client_panel_includes_clients_that_departed_since_prior_fiscal_year()
    {
        $this->seedMonth('planning', 2024, 4, [['order_number' => 'FYCD-24', 'client_name' => 'C社', 'amount' => 500]]);
        $this->seedMonth('planning', 2025, 4, [['order_number' => 'FYCD-25', 'client_name' => 'A社', 'amount' => 1000]]);

        $panel = $this->service()->fiscalYearClientPanel('planning', 2025, false, null, 'amount', 'desc', 10, 1);

        $c = collect($panel['rows'])->firstWhere('label', 'C社');
        $this->assertNotNull($c, 'C社（離脱得意先）が一覧から消えている');
        $this->assertSame(0.0, $c['amount']);
        $this->assertSame(-500.0, $c['diff']);
    }

    public function test_latest_registered_fiscal_year_resolves_month_before_and_after_april()
    {
        $this->seedMonth('planning', 2025, 3, [['order_number' => 'LFY-1', 'client_name' => 'A社', 'amount' => 100]]);
        $this->assertSame(2024, $this->service()->latestRegisteredFiscalYear('planning')); // 3月は前年度扱い

        $this->seedMonth('planning', 2025, 4, [['order_number' => 'LFY-2', 'client_name' => 'A社', 'amount' => 100]]);
        $this->assertSame(2025, $this->service()->latestRegisteredFiscalYear('planning')); // 4月は当年度扱い

        $this->assertNull($this->service()->latestRegisteredFiscalYear('ondemand'));
    }

    public function test_multi_year_fiscal_monthly_series_returns_requested_fiscal_years()
    {
        $this->seedMonth('planning', 2024, 4, [['order_number' => 'MYF-24', 'client_name' => 'A社', 'amount' => 500]]);
        $this->seedMonth('planning', 2026, 4, [['order_number' => 'MYF-26', 'client_name' => 'A社', 'amount' => 900]]);

        $series = $this->service()->multiYearFiscalMonthlySeries('planning', 2026, 3);

        $this->assertSame([2024, 2025, 2026], collect($series)->pluck('fiscal_year')->all());
        $this->assertSame(500.0, $series[0]['months'][0]); // 2024年度・fiscal_month1(4月)
        $this->assertNull($series[1]['months'][0]);
        $this->assertSame(900.0, $series[2]['months'][0]);
        $this->assertCount(12, $series[0]['months']);
    }

    /** REVIEW3 14章Priority A対応（Phase 13）: 年次得意先パネルは常に前年同期間との差額を返す */
    public function test_annual_client_panel_computes_diff_against_prior_year_same_period()
    {
        $this->seedMonth('planning', 2025, 1, [['order_number' => 'AC-25', 'client_name' => 'A社', 'amount' => 1000]]);
        $this->seedMonth('planning', 2026, 1, [
            ['order_number' => 'AC-26A', 'client_name' => 'A社', 'amount' => 1200],
            ['order_number' => 'AC-26B', 'client_name' => 'B社', 'amount' => 300],
        ]);

        $panel = $this->service()->annualClientPanel('planning', 2026, false, null, 'amount', 'desc', 10, 1);

        $a = collect($panel['rows'])->firstWhere('label', 'A社');
        $b = collect($panel['rows'])->firstWhere('label', 'B社');
        $this->assertSame(200.0, $a['diff']); // 1200 - 1000
        $this->assertSame(300.0, $b['diff']); // 前年同期間は0円扱い（新規）
    }

    /**
     * Codexレビュー指摘の回帰テスト（2026-09-05）: 前年のみに存在し今年は受注が無い（＝離脱した）
     * 得意先が、$currentのキーだけを回していたために一覧から丸ごと消えていた。
     */
    public function test_annual_client_panel_includes_clients_that_departed_since_prior_year()
    {
        $this->seedMonth('planning', 2025, 1, [['order_number' => 'ACD-25', 'client_name' => 'C社', 'amount' => 500]]);
        $this->seedMonth('planning', 2026, 1, [['order_number' => 'ACD-26', 'client_name' => 'A社', 'amount' => 1000]]);

        $panel = $this->service()->annualClientPanel('planning', 2026, false, null, 'amount', 'desc', 10, 1);

        $c = collect($panel['rows'])->firstWhere('label', 'C社');
        $this->assertNotNull($c, 'C社（離脱得意先）が一覧から消えている');
        $this->assertSame(0.0, $c['amount']);
        $this->assertSame(-500.0, $c['diff']);
    }

    /** 実機フィードバック対応（2026-09-04）: 年次分析「月別売上」の3年/5年重ね表示 */
    public function test_multi_year_monthly_series_returns_null_for_unregistered_months()
    {
        $this->seedMonth('planning', 2024, 3, [['order_number' => 'MY-24', 'client_name' => 'A社', 'amount' => 1000]]);
        $this->seedMonth('planning', 2026, 3, [['order_number' => 'MY-26', 'client_name' => 'A社', 'amount' => 1500]]);

        $series = $this->service()->multiYearMonthlySeries('planning', 2026, 3);

        $this->assertSame([2024, 2025, 2026], collect($series)->pluck('year')->all());
        $this->assertSame(1000.0, $series[0]['months'][2]); // 2024年3月（0始まりで index2）
        $this->assertNull($series[1]['months'][2]); // 2025年3月は未登録
        $this->assertSame(1500.0, $series[2]['months'][2]); // 2026年3月
        $this->assertCount(12, $series[0]['months']);
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

    public function test_same_month_comparison_years_end_at_current_year()
    {
        $currentYear = (int) now()->format('Y');

        // 何も取込んでいなくても、年配列は「今年を終点にyears_requested年分」を機械的に生成する
        $summary = $this->service()->sameMonthComparison('planning', 1, 5, false);

        $this->assertSame(range($currentYear - 4, $currentYear), $summary['years']);
        $this->assertSame('no_data', collect($summary['yearly'])->firstWhere('year', $currentYear)['state']);
    }

    public function test_same_month_comparison_computes_prior_year_diff()
    {
        $currentYear = (int) now()->format('Y');

        $this->seedMonth('planning', $currentYear - 1, 1, [['order_number' => 'SM-1', 'client_name' => 'A社', 'amount' => 1000]]);
        $this->seedMonth('planning', $currentYear, 1, [['order_number' => 'SM-2', 'client_name' => 'A社', 'amount' => 1500]]);

        $summary = $this->service()->sameMonthComparison('planning', 1, 5, false);
        $yearly = collect($summary['yearly'])->keyBy('year');

        $this->assertSame(1500.0, $yearly[$currentYear]['amount']);
        $this->assertSame(500.0, $yearly[$currentYear]['prior_year_diff']);
        $this->assertSame(50.0, $yearly[$currentYear]['prior_year_rate']);

        // 表示年の最古年（currentYear-4）はさらに1年前（currentYear-5）のデータが無いため比較不可
        $this->assertSame(1000.0, $yearly[$currentYear - 1]['amount']);
        $this->assertNull($yearly[$currentYear - 1]['prior_year_diff']);
    }

    public function test_same_month_comparison_all_departments_aggregates_without_deduplicating_order_count()
    {
        $currentYear = (int) now()->format('Y');

        $this->seedMonth('planning', $currentYear, 1, [['order_number' => 'SAME-NO', 'client_name' => 'A社', 'amount' => 1000]]);
        $this->seedMonth('production', $currentYear, 1, [['order_number' => 'SAME-NO', 'client_name' => 'A社', 'amount' => 2000]]);
        $this->seedMonth('ondemand', $currentYear, 1, [['order_number' => 'OD-1', 'client_name' => 'B社', 'amount' => 500]]);

        $summary = $this->service()->sameMonthComparison('all', 1, 5, false);
        $current = collect($summary['yearly'])->firstWhere('year', $currentYear);

        $this->assertSame(3500.0, $current['amount']);
        $this->assertSame(3, $current['order_count']);
    }

    public function test_same_month_comparison_flags_needs_review()
    {
        $currentYear = (int) now()->format('Y');

        $this->seedMonth('planning', $currentYear, 1, [['order_number' => 'NR-1', 'client_name' => 'A社', 'amount' => 1000]]);
        $this->travel(1)->seconds();
        $this->seedMonth('planning', $currentYear, 1, [['order_number' => 'NR-2', 'client_name' => 'A社', 'amount' => 1200]]);

        $summary = $this->service()->sameMonthComparison('planning', 1, 5, false);
        $current = collect($summary['yearly'])->firstWhere('year', $currentYear);

        $this->assertTrue($current['needs_review']);
    }

    public function test_same_month_comparison_client_matrix_and_new_departed_top_lists()
    {
        $currentYear = (int) now()->format('Y');

        // 表示範囲の最古年より前（未登録）は年全体がnullになることを確認する対照データ
        $this->seedMonth('planning', $currentYear - 1, 1, [
            ['order_number' => 'CM-A-PY', 'client_name' => 'A社', 'amount' => 1000],
            ['order_number' => 'CM-B-PY', 'client_name' => 'B社', 'amount' => 500],
        ]);
        $this->seedMonth('planning', $currentYear, 1, [
            ['order_number' => 'CM-A-CY', 'client_name' => 'A社', 'amount' => 1200], // 増加 +200
            ['order_number' => 'CM-C-CY', 'client_name' => 'C社', 'amount' => 300], // 新規
            // B社は今年は受注なし（離脱）
        ]);

        $summary = $this->service()->sameMonthComparison('planning', 1, 5, false);

        $matrixByName = collect($summary['client_matrix']['clients'])->keyBy('client_name');
        $this->assertSame(1200.0, $matrixByName['A社']['latest_amount']);
        $this->assertSame(1000.0, $matrixByName['A社']['prior_year_amount']);
        $this->assertSame(200.0, $matrixByName['A社']['diff']);

        // 表示範囲の最古年（未登録）はnull、登録済みだが受注が無ければ0円
        $oldestYear = $summary['years'][0];
        $this->assertNull($matrixByName['A社']['amounts'][(string) $oldestYear]);

        $newClientNames = array_column($summary['new_clients'], 'client_name');
        $this->assertContains('C社', $newClientNames);

        $departedClientNames = array_column($summary['departed_clients'], 'client_name');
        $this->assertContains('B社', $departedClientNames);

        // 新規のC社(+300)は増加額でA社(+200)を上回る
        $this->assertSame('C社', $summary['top_increase'][0]['client_name']);
        $this->assertSame('B社', $summary['top_decrease'][0]['client_name']);
        $this->assertSame(-500.0, $summary['top_decrease'][0]['diff']);
    }

    /**
     * Codexレビュー指摘の回帰テスト（2026-09-05）: 符号で絞り込んでいなかったため、
     * 対象が全員減少している期間でも「増加額上位」にマイナスのdiffが混ざって表示されていた。
     */
    public function test_same_month_comparison_top_increase_excludes_negative_diff_rows_when_all_decreased()
    {
        $currentYear = (int) now()->format('Y');

        $this->seedMonth('planning', $currentYear - 1, 2, [
            ['order_number' => 'SGN-A-PY', 'client_name' => 'A社', 'amount' => 1000],
            ['order_number' => 'SGN-B-PY', 'client_name' => 'B社', 'amount' => 800],
        ]);
        $this->seedMonth('planning', $currentYear, 2, [
            ['order_number' => 'SGN-A-CY', 'client_name' => 'A社', 'amount' => 600], // -400
            ['order_number' => 'SGN-B-CY', 'client_name' => 'B社', 'amount' => 500], // -300
        ]);

        $summary = $this->service()->sameMonthComparison('planning', 2, 5, false);

        $this->assertSame([], $summary['top_increase']);
        $this->assertCount(2, $summary['top_decrease']);
    }

    public function test_same_month_comparison_category_offset_comparison_handles_missing_years()
    {
        $currentYear = (int) now()->format('Y');

        $this->seedMonth('planning', $currentYear, 1, [['order_number' => 'CAT-0', 'client_name' => 'A社', 'amount' => 700, 'category' => '組版']]);
        $this->seedMonth('planning', $currentYear - 1, 1, [['order_number' => 'CAT-1', 'client_name' => 'A社', 'amount' => 650, 'category' => '組版']]);
        // currentYear-3 はあえて未登録のままにする
        $this->seedMonth('planning', $currentYear - 5, 1, [['order_number' => 'CAT-5', 'client_name' => 'A社', 'amount' => 550, 'category' => '組版']]);

        $summary = $this->service()->sameMonthComparison('planning', 1, 5, false);
        $comparison = $summary['category_item_comparison'];

        $this->assertSame($currentYear, $comparison['reference_year']);

        $row = collect($comparison['categories'])->firstWhere('label', '組版');
        $this->assertSame(700.0, $row['amount']);

        $byOffset = collect($row['comparisons'])->keyBy('years_ago');
        $this->assertSame(650.0, $byOffset[1]['amount']);
        $this->assertSame(50.0, $byOffset[1]['diff']);
        $this->assertNull($byOffset[3]['amount']);
        $this->assertNull($byOffset[3]['diff']);
        $this->assertSame(550.0, $byOffset[5]['amount']);
        $this->assertSame(150.0, $byOffset[5]['diff']);
    }

    public function test_side_by_side_comparison_year_vs_year_does_not_align_registered_month_counts()
    {
        // A: 1〜3月のみ登録、B: 1〜2月のみ登録（意図的に長さを揃えない）
        for ($m = 1; $m <= 3; $m++) {
            $this->seedMonth('planning', 2024, $m, [['order_number' => "SBS-A-{$m}", 'client_name' => 'A社', 'amount' => 1000]]);
        }
        for ($m = 1; $m <= 2; $m++) {
            $this->seedMonth('planning', 2025, $m, [['order_number' => "SBS-B-{$m}", 'client_name' => 'A社', 'amount' => 1200]]);
        }

        $summary = $this->service()->sideBySideComparison(
            'planning',
            ['type' => 'year', 'year' => 2024],
            ['type' => 'year', 'year' => 2025]
        );

        $this->assertSame(3000.0, $summary['period_a']['amount']); // 1000*3
        $this->assertSame(3, $summary['period_a']['registered_month_count']);
        $this->assertSame(2400.0, $summary['period_b']['amount']); // 1200*2
        $this->assertSame(2, $summary['period_b']['registered_month_count']);
        $this->assertSame(-600.0, $summary['diff']['amount']); // 揃えずそのまま差し引く
    }

    public function test_side_by_side_comparison_month_vs_month()
    {
        $this->seedMonth('planning', 2025, 8, [['order_number' => 'SBS-M1', 'client_name' => 'A社', 'amount' => 1000]]);
        $this->seedMonth('planning', 2025, 9, [['order_number' => 'SBS-M2', 'client_name' => 'A社', 'amount' => 1500]]);

        $summary = $this->service()->sideBySideComparison(
            'planning',
            ['type' => 'month', 'year' => 2025, 'month' => 8],
            ['type' => 'month', 'year' => 2025, 'month' => 9]
        );

        $this->assertSame(1000.0, $summary['period_a']['amount']);
        $this->assertSame(1500.0, $summary['period_b']['amount']);
        $this->assertSame(500.0, $summary['diff']['amount']);
        $this->assertSame(50.0, $summary['diff']['rate']);
    }

    public function test_side_by_side_comparison_returns_null_for_unregistered_period()
    {
        $this->seedMonth('planning', 2025, 8, [['order_number' => 'SBS-N1', 'client_name' => 'A社', 'amount' => 1000]]);

        $summary = $this->service()->sideBySideComparison(
            'planning',
            ['type' => 'month', 'year' => 2020, 'month' => 1],
            ['type' => 'month', 'year' => 2025, 'month' => 8]
        );

        $this->assertNull($summary['period_a']['amount']);
        $this->assertSame(0, $summary['period_a']['registered_month_count']);
        $this->assertNull($summary['diff']['amount']);
        $this->assertNull($summary['diff']['rate']);
    }

    public function test_side_by_side_comparison_clients_include_zero_for_missing_side()
    {
        $this->seedMonth('planning', 2025, 1, [['order_number' => 'SBS-C1', 'client_name' => 'B社', 'amount' => 500]]);
        $this->seedMonth('planning', 2026, 1, [['order_number' => 'SBS-C2', 'client_name' => 'C社', 'amount' => 300]]);

        $summary = $this->service()->sideBySideComparison(
            'planning',
            ['type' => 'month', 'year' => 2025, 'month' => 1],
            ['type' => 'month', 'year' => 2026, 'month' => 1]
        );

        $rows = collect($summary['clients']['rows'])->keyBy('client_name');

        $this->assertSame(500.0, $rows['B社']['amount_a']);
        $this->assertSame(0.0, $rows['B社']['amount_b']);
        $this->assertSame(-100.0, $rows['B社']['rate']); // 消滅（今期実績なし）

        $this->assertSame(0.0, $rows['C社']['amount_a']);
        $this->assertSame(300.0, $rows['C社']['amount_b']);
        $this->assertNull($rows['C社']['rate']); // 新規（前期実績なし）
    }

    public function test_side_by_side_comparison_all_departments_aggregates_without_deduplicating_order_count()
    {
        $this->seedMonth('planning', 2025, 5, [['order_number' => 'SBS-D1', 'client_name' => 'A社', 'amount' => 1000]]);
        $this->seedMonth('production', 2025, 5, [['order_number' => 'SBS-D1', 'client_name' => 'A社', 'amount' => 2000]]);

        $summary = $this->service()->sideBySideComparison(
            'all',
            ['type' => 'month', 'year' => 2025, 'month' => 5],
            ['type' => 'month', 'year' => 2025, 'month' => 5]
        );

        $this->assertSame(3000.0, $summary['period_a']['amount']);
        $this->assertSame(2, $summary['period_a']['order_count']);
    }

    public function test_side_by_side_comparison_consolidates_clients_when_flag_true()
    {
        $group = SalesClientGroup::create(['company_id' => $this->salesTestCompanyId(), 'name' => '株式会社NON', 'created_by' => 1, 'updated_by' => 1]);
        SalesClientGroupMember::create(['sales_client_group_id' => $group->id, 'company_id' => $this->salesTestCompanyId(), 'client_name' => '株式会社NON（2）', 'normalized_name' => 'non']);
        SalesClientGroupMember::create(['sales_client_group_id' => $group->id, 'company_id' => $this->salesTestCompanyId(), 'client_name' => '株式会社NON（3）', 'normalized_name' => 'non']);

        $this->seedMonth('planning', 2025, 6, [['order_number' => 'SBS-G1', 'client_name' => '株式会社NON（2）', 'amount' => 1000]]);
        $this->seedMonth('planning', 2026, 6, [['order_number' => 'SBS-G2', 'client_name' => '株式会社NON（3）', 'amount' => 1500]]);

        $summary = $this->service()->sideBySideComparison(
            'planning',
            ['type' => 'month', 'year' => 2025, 'month' => 6],
            ['type' => 'month', 'year' => 2026, 'month' => 6],
            true
        );

        $rows = collect($summary['clients']['rows'])->keyBy('client_name');
        $this->assertCount(1, $rows);
        $this->assertSame(1000.0, $rows['株式会社NON']['amount_a']);
        $this->assertSame(1500.0, $rows['株式会社NON']['amount_b']);
    }

    public function test_product_ranking_for_period_sums_across_range_and_computes_share()
    {
        $this->seedMonth('planning', 2026, 1, [
            ['order_number' => 'PR-1', 'client_name' => 'A社', 'product_name' => '名刺', 'amount' => 600],
            ['order_number' => 'PR-2', 'client_name' => 'B社', 'product_name' => '封筒', 'amount' => 400],
        ]);
        $this->seedMonth('planning', 2026, 2, [
            ['order_number' => 'PR-3', 'client_name' => 'A社', 'product_name' => '名刺', 'amount' => 400],
        ]);

        $result = $this->service()->productRankingForPeriod('planning', 2026, 1, 2026, 2, null);

        $ranking = collect($result['ranking'])->keyBy('product_name');
        $this->assertSame(1000.0, $ranking['名刺']['amount']);
        $this->assertSame(400.0, $ranking['封筒']['amount']);
        $this->assertSame(71.4, $ranking['名刺']['share_pct']);
    }

    public function test_product_analysis_panel_returns_paginated_rows_like_client_panel()
    {
        $this->seedMonth('planning', 2026, 3, [
            ['order_number' => 'PP-1', 'client_name' => 'A社', 'product_name' => '名刺', 'amount' => 1000],
            ['order_number' => 'PP-2', 'client_name' => 'B社', 'product_name' => '封筒', 'amount' => 500],
        ]);

        $result = $this->service()->productAnalysisPanel('planning', 2026, 3, 2026, 3, null, 'amount', 'desc', 10, 1);

        $this->assertSame(1500.0, $result['total_amount']);
        $this->assertSame('名刺', $result['rows'][0]['label']);
        $this->assertSame(1000.0, $result['rows'][0]['amount']);
    }

    public function test_product_detail_returns_yearly_trend_orders_and_client_ranking()
    {
        $this->seedMonth('planning', 2025, 6, [
            ['order_number' => 'PD-1', 'client_name' => 'A社', 'product_name' => '名刺', 'amount' => 500],
        ]);
        $this->seedMonth('planning', 2026, 6, [
            ['order_number' => 'PD-2', 'client_name' => 'A社', 'product_name' => '名刺', 'amount' => 800],
            ['order_number' => 'PD-3', 'client_name' => 'B社', 'product_name' => '名刺', 'amount' => 200],
        ]);

        $result = $this->service()->productDetail('planning', '名刺', 2025, 6, 2026, 6);

        $yearly = collect($result['yearly'])->keyBy('year');
        $this->assertSame(500.0, $yearly[2025]['amount']);
        $this->assertSame(1000.0, $yearly[2026]['amount']);
        $this->assertSame(500.0, $yearly[2026]['prior_year_diff']);
        $this->assertCount(3, $result['orders']);

        // client_ranking は開始〜終了の全期間合計（A社は2025年500+2026年800=1300）
        $clientRanking = collect($result['client_ranking'])->keyBy('client_name');
        $this->assertSame(1300.0, $clientRanking['A社']['amount']);
        $this->assertSame(200.0, $clientRanking['B社']['amount']);
    }

    public function test_product_year_over_year_comparison_flags_new_and_discontinued_products()
    {
        $this->seedMonth('planning', 2025, 1, [
            ['order_number' => 'YOY-1', 'client_name' => 'A社', 'product_name' => '封筒', 'amount' => 300],
            ['order_number' => 'YOY-2', 'client_name' => 'A社', 'product_name' => '名刺', 'amount' => 1000],
        ]);
        $this->seedMonth('planning', 2026, 1, [
            ['order_number' => 'YOY-3', 'client_name' => 'A社', 'product_name' => '名刺', 'amount' => 200],
            ['order_number' => 'YOY-4', 'client_name' => 'A社', 'product_name' => 'チラシ', 'amount' => 900],
        ]);

        $result = $this->service()->productYearOverYearComparison('planning');

        $this->assertTrue($result['has_comparison_pair']);
        $this->assertSame(2026, $result['latest_year']);
        $this->assertSame(2025, $result['prior_year']);

        $newProducts = collect($result['new_products'])->keyBy('product_name');
        $this->assertArrayHasKey('チラシ', $newProducts->all());
        $this->assertSame(900.0, $newProducts['チラシ']['amount']);

        $discontinued = collect($result['discontinued_products'])->keyBy('product_name');
        $this->assertArrayHasKey('封筒', $discontinued->all());
        $this->assertSame(300.0, $discontinued['封筒']['prior_year_amount']);

        $decrease = collect($result['top_decrease'])->keyBy('product_name');
        $this->assertSame(-800.0, $decrease['名刺']['diff']);
    }

    /**
     * 実機フィードバック回帰テスト（2026-09-05）: 教材・テキスト等は年度だけ変えて毎年作られるため、
     * 年度表記のみが違う商品名（例:「2027年度用中学入試問題集組版代」対「2026年度用中学入試問題集組版代」）を
     * 新規/取扱終了として誤検知してはならない。
     */
    public function test_product_year_over_year_comparison_treats_year_variant_names_as_same_product()
    {
        $this->seedMonth('planning', 2025, 4, [
            ['order_number' => 'YOY-N1', 'client_name' => 'A社', 'product_name' => '2025年度用中学入試問題集組版代 銀本α版通常校データ', 'amount' => 1000],
        ]);
        $this->seedMonth('planning', 2026, 4, [
            ['order_number' => 'YOY-N2', 'client_name' => 'A社', 'product_name' => '2026年度用中学入試問題集組版代 銀本α版通常校データ', 'amount' => 1200],
        ]);

        $result = $this->service()->productYearOverYearComparison('planning');

        $newNames = collect($result['new_products'])->pluck('product_name')->all();
        $discontinuedNames = collect($result['discontinued_products'])->pluck('product_name')->all();
        $this->assertSame([], $newNames);
        $this->assertSame([], $discontinuedNames);

        // 増加額上位には、今年の名称（年度除去前の原名）で1200-1000=200円の増加として現れる
        $increase = collect($result['top_increase'])->first(fn ($r) => str_contains($r['product_name'], '中学入試問題集組版代'));
        $this->assertNotNull($increase);
        $this->assertSame('2026年度用中学入試問題集組版代 銀本α版通常校データ', $increase['product_name']);
        $this->assertSame(200.0, $increase['diff']);
    }

    /**
     * Codexレビュー指摘の回帰テスト（2026-09-05）: 同月比較と同種のバグ。全商品が減少している年でも
     * 「増加額上位」にマイナスのdiffが混ざって表示されないことを確認する。
     */
    public function test_product_year_over_year_comparison_top_increase_excludes_negative_diff_rows_when_all_decreased()
    {
        $this->seedMonth('planning', 2025, 1, [
            ['order_number' => 'PYOY-1', 'client_name' => 'A社', 'product_name' => '名刺', 'amount' => 1000],
            ['order_number' => 'PYOY-2', 'client_name' => 'A社', 'product_name' => '封筒', 'amount' => 800],
        ]);
        $this->seedMonth('planning', 2026, 1, [
            ['order_number' => 'PYOY-3', 'client_name' => 'A社', 'product_name' => '名刺', 'amount' => 600], // -400
            ['order_number' => 'PYOY-4', 'client_name' => 'A社', 'product_name' => '封筒', 'amount' => 500], // -300
        ]);

        $result = $this->service()->productYearOverYearComparison('planning');

        $this->assertSame([], $result['top_increase']);
        $this->assertCount(2, $result['top_decrease']);
    }

    public function test_product_year_over_year_comparison_has_no_pair_when_prior_year_unregistered()
    {
        $this->seedMonth('planning', 2026, 1, [
            ['order_number' => 'YOY-5', 'client_name' => 'A社', 'product_name' => '名刺', 'amount' => 500],
        ]);

        $result = $this->service()->productYearOverYearComparison('planning');

        $this->assertFalse($result['has_comparison_pair']);
        $this->assertSame([], $result['new_products']);
        $this->assertSame([], $result['discontinued_products']);
    }
}
