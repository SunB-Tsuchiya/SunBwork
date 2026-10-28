<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Company;
use App\Models\Sales\SalesExpenseImport;
use App\Models\Sales\SalesExpenseLineItem;
use App\Services\SalesAnalysis\SalesExpenseQueryService;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

/** Phase 21: 部署別経費データの集計サービス */
class SalesExpenseQueryServiceTest extends TestCase
{
    use RefreshesSalesDatabase;

    private function markCompanyAsSunbrain(): void
    {
        Company::find($this->salesTestCompanyId())->update(['code' => 'SUNBRAIN']);
    }

    private function seedImport(int $fiscalYear, int $version, array $items): SalesExpenseImport
    {
        $import = SalesExpenseImport::create([
            'company_id' => $this->salesTestCompanyId(),
            'fiscal_year' => $fiscalYear,
            'version' => $version,
            'original_filename' => 'seed.xlsx',
            'file_sha256' => hash('sha256', "expense-seed-{$fiscalYear}-{$version}-" . uniqid()),
            'status' => 'completed',
            'imported_by' => 1,
            'imported_at' => now(),
            'category_count' => count(array_unique(array_column($items, 'category'))),
            'line_item_count' => count($items),
            'total_amount' => array_sum(array_column($items, 'amount')),
        ]);

        foreach ($items as $item) {
            SalesExpenseLineItem::create(array_merge(['sales_expense_import_id' => $import->id], $item));
        }

        return $import;
    }

    private function service(): SalesExpenseQueryService
    {
        return (new SalesExpenseQueryService())->forCompany($this->salesTestCompanyId());
    }

    public function test_has_data_and_department_monthly_totals_return_fiscal_month_order()
    {
        $this->markCompanyAsSunbrain();

        $this->seedImport(2025, 1, [
            ['department_key' => 'planning', 'category' => '主材料費', 'vendor_name' => 'A社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 10000, 'is_shared_split' => false],
            ['department_key' => 'planning', 'category' => '主材料費', 'vendor_name' => 'B社', 'calendar_year' => 2026, 'calendar_month' => 1, 'amount' => 5000, 'is_shared_split' => false],
        ]);

        $this->assertTrue($this->service()->hasData(2025));
        $this->assertFalse($this->service()->hasData(2099));

        $result = $this->service()->departmentMonthlyTotals(2025, 'planning');

        $this->assertTrue($result['has_data']);
        $this->assertSame(15000.0, $result['total_amount']);

        $category = collect($result['categories'])->firstWhere('category', '主材料費');
        $this->assertSame(15000.0, $category['total_amount']);
        // fiscal_month_index 1 = 4月（会計年度の開始月）
        $this->assertSame(10000.0, $category['months'][0]['amount']);
        $this->assertSame(4, $category['months'][0]['calendar_month']);
        $this->assertSame(2025, $category['months'][0]['calendar_year']);
        // fiscal_month_index 10 = 1月（翌暦年）
        $this->assertSame(5000.0, $category['months'][9]['amount']);
        $this->assertSame(1, $category['months'][9]['calendar_month']);
        $this->assertSame(2026, $category['months'][9]['calendar_year']);
    }

    public function test_only_active_version_is_counted_after_reimport()
    {
        $this->markCompanyAsSunbrain();

        $this->seedImport(2025, 1, [
            ['department_key' => 'planning', 'category' => '主材料費', 'vendor_name' => 'A社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 10000, 'is_shared_split' => false],
        ]);
        $this->seedImport(2025, 2, [
            ['department_key' => 'planning', 'category' => '主材料費', 'vendor_name' => 'A社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 99999, 'is_shared_split' => false],
        ]);

        $result = $this->service()->departmentMonthlyTotals(2025, 'planning');

        $this->assertSame(99999.0, $result['total_amount']);
    }

    public function test_shared_department_is_independent_from_planning_production_ondemand()
    {
        $this->markCompanyAsSunbrain();

        $this->seedImport(2025, 1, [
            ['department_key' => 'planning', 'category' => '主材料費', 'vendor_name' => 'A社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 1000, 'is_shared_split' => false],
            ['department_key' => 'shared', 'category' => '通信費', 'vendor_name' => 'B社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 2000, 'is_shared_split' => true],
        ]);

        $planning = $this->service()->departmentMonthlyTotals(2025, 'planning');
        $shared = $this->service()->departmentMonthlyTotals(2025, 'shared');

        $this->assertSame(1000.0, $planning['total_amount']);
        $this->assertSame(2000.0, $shared['total_amount']);
        $this->assertTrue($shared['categories'][0]['is_shared_split']);
        $this->assertFalse($planning['categories'][0]['is_shared_split']);
    }

    public function test_vendor_breakdown_computes_share_and_split_flag()
    {
        $this->markCompanyAsSunbrain();

        $this->seedImport(2025, 1, [
            ['department_key' => 'production', 'category' => '外注加工費', 'vendor_name' => 'A社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 3000, 'is_shared_split' => false],
            ['department_key' => 'production', 'category' => '外注加工費', 'vendor_name' => 'B社', 'calendar_year' => 2025, 'calendar_month' => 5, 'amount' => 1000, 'is_shared_split' => false],
        ]);

        $result = $this->service()->vendorBreakdown(2025, 'production', '外注加工費');

        $this->assertTrue($result['has_data']);
        $this->assertSame(4000.0, $result['total_amount']);
        $this->assertFalse($result['is_shared_split']);

        // 小計の区切りが無いデータのため、グループは1つにまとまる
        $this->assertCount(1, $result['groups']);
        $this->assertNull($result['groups'][0]['subtotal_label']);

        $byVendor = collect($result['groups'][0]['vendors'])->keyBy('vendor_name');
        $this->assertSame(3000.0, $byVendor['A社']['amount']);
        $this->assertSame(75.0, $byVendor['A社']['share_pct']);
        $this->assertSame(25.0, $byVendor['B社']['share_pct']);
    }

    public function test_vendor_breakdown_reproduces_excel_subtotal_groups_in_order()
    {
        $this->markCompanyAsSunbrain();

        $this->seedImport(2025, 1, [
            ['department_key' => 'production', 'category' => '外注加工費', 'vendor_name' => 'A社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 3000, 'is_shared_split' => false, 'subtotal_group' => 1, 'subtotal_label' => '小計①'],
            ['department_key' => 'production', 'category' => '外注加工費', 'vendor_name' => 'B社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 1000, 'is_shared_split' => false, 'subtotal_group' => 1, 'subtotal_label' => '小計①'],
            ['department_key' => 'production', 'category' => '外注加工費', 'vendor_name' => 'C社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 500, 'is_shared_split' => false, 'subtotal_group' => 2, 'subtotal_label' => null],
        ]);

        $result = $this->service()->vendorBreakdown(2025, 'production', '外注加工費');

        $this->assertSame(4500.0, $result['total_amount']);
        $this->assertCount(2, $result['groups']);
        $this->assertSame('小計①', $result['groups'][0]['subtotal_label']);
        $this->assertSame(4000.0, $result['groups'][0]['subtotal_amount']);
        $this->assertSame(['A社', 'B社'], collect($result['groups'][0]['vendors'])->pluck('vendor_name')->all());
        $this->assertNull($result['groups'][1]['subtotal_label']);
        $this->assertSame(500.0, $result['groups'][1]['subtotal_amount']);
    }

    public function test_department_monthly_totals_orders_categories_like_excel()
    {
        $this->markCompanyAsSunbrain();

        $this->seedImport(2025, 1, [
            ['department_key' => 'planning', 'category' => '主材料費', 'category_order' => 2, 'vendor_name' => 'A社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 1000, 'is_shared_split' => false],
            ['department_key' => 'planning', 'category' => '外注加工費', 'category_order' => 1, 'vendor_name' => 'B社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 999999, 'is_shared_split' => false],
        ]);

        $result = $this->service()->departmentMonthlyTotals(2025, 'planning');

        // 金額ではなくExcelの登場順（category_order）で並ぶことを確認
        $this->assertSame('外注加工費', $result['categories'][0]['category']);
        $this->assertSame('主材料費', $result['categories'][1]['category']);
    }

    public function test_vendor_breakdown_returns_no_data_for_unregistered_fiscal_year()
    {
        $this->markCompanyAsSunbrain();

        $result = $this->service()->vendorBreakdown(2099, 'planning', '主材料費');

        $this->assertFalse($result['has_data']);
        $this->assertSame([], $result['groups']);
    }

    // --- Phase 22: 経費比較 ---

    public function test_available_fiscal_years_returns_sorted_distinct_years()
    {
        $this->markCompanyAsSunbrain();

        $this->seedImport(2024, 1, [
            ['department_key' => 'planning', 'category' => '主材料費', 'vendor_name' => 'A社', 'calendar_year' => 2024, 'calendar_month' => 4, 'amount' => 1000, 'is_shared_split' => false],
        ]);
        $this->seedImport(2025, 1, [
            ['department_key' => 'planning', 'category' => '主材料費', 'vendor_name' => 'A社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 1000, 'is_shared_split' => false],
        ]);

        $this->assertSame([2024, 2025], $this->service()->availableFiscalYears());
    }

    public function test_categories_for_department_uses_most_recent_fiscal_year_with_data()
    {
        $this->markCompanyAsSunbrain();

        $this->seedImport(2024, 1, [
            ['department_key' => 'planning', 'category' => '旧科目', 'category_order' => 1, 'vendor_name' => 'A社', 'calendar_year' => 2024, 'calendar_month' => 4, 'amount' => 1000, 'is_shared_split' => false],
        ]);
        $this->seedImport(2025, 1, [
            ['department_key' => 'planning', 'category' => '外注加工費', 'category_order' => 1, 'vendor_name' => 'A社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 2000, 'is_shared_split' => false],
            ['department_key' => 'planning', 'category' => '主材料費', 'category_order' => 2, 'vendor_name' => 'B社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 3000, 'is_shared_split' => false],
        ]);

        $this->assertSame(['外注加工費', '主材料費'], $this->service()->categoriesForDepartment('planning'));
    }

    public function test_category_multi_year_trend_includes_zero_amount_years()
    {
        $this->markCompanyAsSunbrain();

        $this->seedImport(2024, 1, [
            ['department_key' => 'planning', 'category' => '消耗品費', 'vendor_name' => 'A社', 'calendar_year' => 2024, 'calendar_month' => 4, 'amount' => 1000, 'is_shared_split' => false],
        ]);
        $this->seedImport(2025, 1, [
            ['department_key' => 'planning', 'category' => '外注加工費', 'vendor_name' => 'A社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 3000, 'is_shared_split' => false],
            ['department_key' => 'planning', 'category' => '消耗品費', 'vendor_name' => 'B社', 'calendar_year' => 2025, 'calendar_month' => 5, 'amount' => 1000, 'is_shared_split' => false],
        ]);

        $result = $this->service()->categoryMultiYearTrend('planning', '外注加工費');

        $this->assertCount(2, $result['years']);
        $this->assertSame(2024, $result['years'][0]['fiscal_year']);
        $this->assertSame(0.0, $result['years'][0]['amount']);
        $this->assertSame(0.0, $result['years'][0]['share_pct']);
        $this->assertSame(2025, $result['years'][1]['fiscal_year']);
        $this->assertSame(3000.0, $result['years'][1]['amount']);
        $this->assertSame(75.0, $result['years'][1]['share_pct']);
    }

    public function test_vendor_ranking_sums_across_subtotal_groups_and_sorts_by_amount()
    {
        $this->markCompanyAsSunbrain();

        $this->seedImport(2025, 1, [
            ['department_key' => 'production', 'category' => '外注加工費', 'vendor_name' => 'A社', 'subtotal_group' => 1, 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 1000, 'is_shared_split' => false],
            ['department_key' => 'production', 'category' => '外注加工費', 'vendor_name' => 'A社', 'subtotal_group' => 2, 'calendar_year' => 2025, 'calendar_month' => 5, 'amount' => 4000, 'is_shared_split' => false],
            ['department_key' => 'production', 'category' => '外注加工費', 'vendor_name' => 'B社', 'subtotal_group' => 1, 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 2000, 'is_shared_split' => false],
        ]);

        $result = $this->service()->vendorRanking(2025, 'production', '外注加工費');

        // A社はグループ1+2の合計5000で、グループを無視してフラットに集計される
        $this->assertSame('A社', $result['vendors'][0]['vendor_name']);
        $this->assertSame(5000.0, $result['vendors'][0]['amount']);
        $this->assertSame('B社', $result['vendors'][1]['vendor_name']);
    }

    public function test_vendor_comparison_merges_previous_year_amounts_and_diff()
    {
        $this->markCompanyAsSunbrain();

        $this->seedImport(2024, 1, [
            ['department_key' => 'production', 'category' => '外注加工費', 'vendor_name' => 'A社', 'calendar_year' => 2024, 'calendar_month' => 4, 'amount' => 1000, 'is_shared_split' => false],
        ]);
        $this->seedImport(2025, 1, [
            ['department_key' => 'production', 'category' => '外注加工費', 'vendor_name' => 'A社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 1500, 'is_shared_split' => false],
            ['department_key' => 'production', 'category' => '外注加工費', 'vendor_name' => 'C社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 500, 'is_shared_split' => false],
        ]);

        $result = $this->service()->vendorComparison(2025, 'production', '外注加工費');

        $this->assertTrue($result['previous_has_data']);
        $this->assertSame(2024, $result['previous_fiscal_year']);

        $byVendor = collect($result['vendors'])->keyBy('vendor_name');
        $this->assertSame(1000.0, $byVendor['A社']['previous_amount']);
        $this->assertSame(500.0, $byVendor['A社']['diff']);
        $this->assertSame(50.0, $byVendor['A社']['diff_pct']);

        // 前年に存在しなかった支払先はprevious_amountがnull
        $this->assertNull($byVendor['C社']['previous_amount']);
        $this->assertNull($byVendor['C社']['diff']);
    }

    public function test_vendor_comparison_previous_has_data_false_when_no_prior_import()
    {
        $this->markCompanyAsSunbrain();

        $this->seedImport(2025, 1, [
            ['department_key' => 'production', 'category' => '外注加工費', 'vendor_name' => 'A社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 1000, 'is_shared_split' => false],
        ]);

        $result = $this->service()->vendorComparison(2025, 'production', '外注加工費');

        $this->assertFalse($result['previous_has_data']);
        $this->assertNull($result['vendors'][0]['previous_amount']);
    }

    public function test_department_sheet_reproduces_excel_order_subtotals_and_months()
    {
        $this->markCompanyAsSunbrain();

        // 登録順: B社（小額）→A社（高額）。Excel登場順（登録順）で並び、金額順にはならない
        $this->seedImport(2025, 1, [
            ['department_key' => 'production', 'category' => '外注加工費', 'category_order' => 2, 'vendor_name' => 'B社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 1000, 'is_shared_split' => false, 'subtotal_group' => 1, 'subtotal_label' => '小計①'],
            ['department_key' => 'production', 'category' => '外注加工費', 'category_order' => 2, 'vendor_name' => 'A社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 3000, 'is_shared_split' => false, 'subtotal_group' => 1, 'subtotal_label' => '小計①'],
            ['department_key' => 'production', 'category' => '外注加工費', 'category_order' => 2, 'vendor_name' => 'A社', 'calendar_year' => 2026, 'calendar_month' => 3, 'amount' => 500, 'is_shared_split' => false, 'subtotal_group' => 1, 'subtotal_label' => '小計①'],
            ['department_key' => 'production', 'category' => '外注加工費', 'category_order' => 2, 'vendor_name' => 'C社', 'calendar_year' => 2025, 'calendar_month' => 5, 'amount' => 500, 'is_shared_split' => false, 'subtotal_group' => 2, 'subtotal_label' => null],
            // 科目順: 主材料費（order=1）が先
            ['department_key' => 'production', 'category' => '主材料費', 'category_order' => 1, 'vendor_name' => 'D社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 5000, 'is_shared_split' => true, 'subtotal_group' => 1, 'subtotal_label' => null],
            // 別部署は混ざらない
            ['department_key' => 'planning', 'category' => '主材料費', 'category_order' => 1, 'vendor_name' => 'X社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 99999, 'is_shared_split' => false, 'subtotal_group' => 1, 'subtotal_label' => null],
        ]);

        $sheet = $this->service()->departmentSheet(2025, 'production');

        $this->assertTrue($sheet['has_data']);
        $this->assertCount(12, $sheet['months']);
        $this->assertSame([2025, 4], [$sheet['months'][0]['calendar_year'], $sheet['months'][0]['calendar_month']]);
        $this->assertSame([2026, 3], [$sheet['months'][11]['calendar_year'], $sheet['months'][11]['calendar_month']]);

        $this->assertSame(['主材料費', '外注加工費'], array_column($sheet['categories'], 'category'));
        $this->assertTrue($sheet['categories'][0]['is_shared_split']);
        $this->assertSame(10000.0, $sheet['total_amount']);
        $this->assertSame(50.0, $sheet['categories'][0]['share_pct']);

        $outsourcing = $sheet['categories'][1];
        $this->assertSame(5000.0, $outsourcing['total_amount']);
        $this->assertCount(2, $outsourcing['groups']);
        $this->assertSame('小計①', $outsourcing['groups'][0]['subtotal_label']);
        $this->assertSame(4500.0, $outsourcing['groups'][0]['subtotal_amount']);
        $this->assertNull($outsourcing['groups'][1]['subtotal_label']);
        $this->assertSame(['B社', 'A社'], array_column($outsourcing['groups'][0]['vendors'], 'vendor_name'));

        $a = $outsourcing['groups'][0]['vendors'][1];
        $this->assertSame(3000.0, $a['months'][0]);
        $this->assertSame(500.0, $a['months'][11]);
        $this->assertSame(3500.0, $a['total_amount']);

        // 月別の部署合計: 4月=B1000+A3000+D5000、5月=C500、3月=A500
        $this->assertSame(9000.0, $sheet['month_totals'][0]);
        $this->assertSame(500.0, $sheet['month_totals'][1]);
        $this->assertSame(500.0, $sheet['month_totals'][11]);
    }

    public function test_department_sheet_adds_previous_year_comparison_and_new_flags()
    {
        $this->markCompanyAsSunbrain();

        $this->seedImport(2024, 1, [
            ['department_key' => 'production', 'category' => '外注加工費', 'category_order' => 1, 'vendor_name' => 'A社', 'calendar_year' => 2024, 'calendar_month' => 4, 'amount' => 1000, 'is_shared_split' => false, 'subtotal_group' => 1],
        ]);
        $this->seedImport(2025, 1, [
            ['department_key' => 'production', 'category' => '外注加工費', 'category_order' => 1, 'vendor_name' => 'A社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 1500, 'is_shared_split' => false, 'subtotal_group' => 1],
            ['department_key' => 'production', 'category' => '外注加工費', 'category_order' => 1, 'vendor_name' => 'C社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 500, 'is_shared_split' => false, 'subtotal_group' => 1],
            ['department_key' => 'production', 'category' => '消耗品費', 'category_order' => 2, 'vendor_name' => 'E社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 100, 'is_shared_split' => false, 'subtotal_group' => 1],
        ]);

        $sheet = $this->service()->departmentSheet(2025, 'production');

        $this->assertTrue($sheet['previous_has_data']);
        $this->assertSame(1000.0, $sheet['previous_total_amount']);

        $outsourcing = $sheet['categories'][0];
        $this->assertSame(1000.0, $outsourcing['previous_total_amount']);
        $this->assertSame(1000.0, $outsourcing['diff']);
        $this->assertSame(100.0, $outsourcing['diff_pct']);
        $this->assertFalse($outsourcing['is_new']);

        $vendors = collect($outsourcing['groups'][0]['vendors'])->keyBy('vendor_name');
        $this->assertSame(50.0, $vendors['A社']['diff_pct']);
        // 前年度データはあるが前年に無かった支払先・科目は「新規」
        $this->assertTrue($vendors['C社']['is_new']);
        $this->assertNull($vendors['C社']['previous_total_amount']);
        $this->assertTrue($sheet['categories'][1]['is_new']);
    }

    public function test_department_sheet_has_no_new_flags_when_previous_year_is_missing()
    {
        $this->markCompanyAsSunbrain();

        $this->seedImport(2025, 1, [
            ['department_key' => 'production', 'category' => '外注加工費', 'category_order' => 1, 'vendor_name' => 'A社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 1000, 'is_shared_split' => false, 'subtotal_group' => 1],
        ]);

        $sheet = $this->service()->departmentSheet(2025, 'production');

        $this->assertFalse($sheet['previous_has_data']);
        $this->assertNull($sheet['previous_total_amount']);
        $vendor = $sheet['categories'][0]['groups'][0]['vendors'][0];
        $this->assertFalse($vendor['is_new']);
        $this->assertNull($vendor['diff_pct']);
    }

    public function test_department_sheet_returns_no_data_for_unregistered_year_or_department()
    {
        $this->markCompanyAsSunbrain();

        $this->seedImport(2025, 1, [
            ['department_key' => 'production', 'category' => '外注加工費', 'category_order' => 1, 'vendor_name' => 'A社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 1000, 'is_shared_split' => false, 'subtotal_group' => 1],
        ]);

        $this->assertFalse($this->service()->departmentSheet(2030, 'production')['has_data']);
        $this->assertFalse($this->service()->departmentSheet(2025, 'ondemand')['has_data']);
        $this->assertCount(12, $this->service()->departmentSheet(2030, 'production')['months']);
    }

    public function test_actual_months_is_last_month_with_amount_across_all_departments()
    {
        $this->markCompanyAsSunbrain();

        // 企画は4〜5月、制作は7月にだけ金額あり（6月は全部署ゼロ）→ 最終実績月は7月=4か月目
        $this->seedImport(2025, 1, [
            ['department_key' => 'planning', 'category' => '外注加工費', 'category_order' => 1, 'vendor_name' => 'A社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 100, 'is_shared_split' => false, 'subtotal_group' => 1],
            ['department_key' => 'planning', 'category' => '外注加工費', 'category_order' => 1, 'vendor_name' => 'A社', 'calendar_year' => 2025, 'calendar_month' => 5, 'amount' => 100, 'is_shared_split' => false, 'subtotal_group' => 1],
            ['department_key' => 'production', 'category' => '外注加工費', 'category_order' => 1, 'vendor_name' => 'B社', 'calendar_year' => 2025, 'calendar_month' => 7, 'amount' => 100, 'is_shared_split' => false, 'subtotal_group' => 1],
            ['department_key' => 'production', 'category' => '外注加工費', 'category_order' => 1, 'vendor_name' => 'B社', 'calendar_year' => 2025, 'calendar_month' => 8, 'amount' => 0, 'is_shared_split' => false, 'subtotal_group' => 1],
        ]);

        $this->assertSame(4, $this->service()->actualMonths(2025));
        $this->assertSame(0, $this->service()->actualMonths(2030));

        // 企画シートでも、最終実績月はファイル全体（7月まで）で判定される
        $sheet = $this->service()->departmentSheet(2025, 'planning');
        $this->assertSame(4, $sheet['actual_months']);
        $this->assertTrue($sheet['is_partial']);
    }

    public function test_department_sheet_compares_same_period_when_fiscal_year_is_partial()
    {
        $this->markCompanyAsSunbrain();

        // 前年は通年で4月1000・12月9000。当年は4月だけ実績（期の途中）
        $this->seedImport(2024, 1, [
            ['department_key' => 'production', 'category' => '外注加工費', 'category_order' => 1, 'vendor_name' => 'A社', 'calendar_year' => 2024, 'calendar_month' => 4, 'amount' => 1000, 'is_shared_split' => false, 'subtotal_group' => 1],
            ['department_key' => 'production', 'category' => '外注加工費', 'category_order' => 1, 'vendor_name' => 'A社', 'calendar_year' => 2024, 'calendar_month' => 12, 'amount' => 9000, 'is_shared_split' => false, 'subtotal_group' => 1],
        ]);
        $this->seedImport(2025, 1, [
            ['department_key' => 'production', 'category' => '外注加工費', 'category_order' => 1, 'vendor_name' => 'A社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 1500, 'is_shared_split' => false, 'subtotal_group' => 1],
        ]);

        // 既定=YTD: 前年も4月までの累計（1000）で比較し、12月分は含めない
        $ytd = $this->service()->departmentSheet(2025, 'production');
        $this->assertSame('ytd', $ytd['compare_period']);
        $this->assertSame(1, $ytd['actual_months']);
        $this->assertSame(1000.0, $ytd['previous_total_amount']);
        $this->assertSame(50.0, $ytd['categories'][0]['diff_pct']);
        $vendor = $ytd['categories'][0]['groups'][0]['vendors'][0];
        $this->assertSame(1000.0, $vendor['previous_months'][0]);
        $this->assertSame(0.0, $vendor['previous_months'][8]); // 12月はYTD範囲外でゼロ

        // 通年を明示: 前年は10000
        $full = $this->service()->departmentSheet(2025, 'production', null, 'full');
        $this->assertSame('full', $full['compare_period']);
        $this->assertSame(10000.0, $full['previous_total_amount']);
        $this->assertSame(9000.0, $full['categories'][0]['groups'][0]['vendors'][0]['previous_months'][8]);
    }

    public function test_department_sheet_uses_full_period_when_fiscal_year_is_complete()
    {
        $this->markCompanyAsSunbrain();

        $this->seedImport(2024, 1, [
            ['department_key' => 'production', 'category' => '外注加工費', 'category_order' => 1, 'vendor_name' => 'A社', 'calendar_year' => 2024, 'calendar_month' => 4, 'amount' => 1000, 'is_shared_split' => false, 'subtotal_group' => 1],
        ]);
        // 3月に金額がある=12か月分そろった年度
        $this->seedImport(2025, 1, [
            ['department_key' => 'production', 'category' => '外注加工費', 'category_order' => 1, 'vendor_name' => 'A社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 1500, 'is_shared_split' => false, 'subtotal_group' => 1],
            ['department_key' => 'production', 'category' => '外注加工費', 'category_order' => 1, 'vendor_name' => 'A社', 'calendar_year' => 2026, 'calendar_month' => 3, 'amount' => 100, 'is_shared_split' => false, 'subtotal_group' => 1],
        ]);

        $sheet = $this->service()->departmentSheet(2025, 'production', null, 'ytd');
        $this->assertFalse($sheet['is_partial']);
        $this->assertSame('full', $sheet['compare_period']); // 通年の年度ではytd指定でも通年扱い
        $this->assertSame(12, $sheet['actual_months']);
    }

    public function test_department_sheet_supports_custom_compare_year_and_month_totals()
    {
        $this->markCompanyAsSunbrain();

        $this->seedImport(2023, 1, [
            ['department_key' => 'production', 'category' => '外注加工費', 'category_order' => 1, 'vendor_name' => 'A社', 'calendar_year' => 2023, 'calendar_month' => 4, 'amount' => 400, 'is_shared_split' => false, 'subtotal_group' => 1],
            ['department_key' => 'production', 'category' => '旧科目', 'category_order' => 2, 'vendor_name' => 'Z社', 'calendar_year' => 2023, 'calendar_month' => 4, 'amount' => 600, 'is_shared_split' => false, 'subtotal_group' => 1],
        ]);
        $this->seedImport(2025, 1, [
            ['department_key' => 'production', 'category' => '外注加工費', 'category_order' => 1, 'vendor_name' => 'A社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 800, 'is_shared_split' => false, 'subtotal_group' => 1],
        ]);

        // 2024年度は未取込 → 既定の前年度比較はデータ無し、2023年度を指定すれば比較できる
        $this->assertFalse($this->service()->departmentSheet(2025, 'production')['previous_has_data']);

        $sheet = $this->service()->departmentSheet(2025, 'production', 2023);
        $this->assertTrue($sheet['previous_has_data']);
        $this->assertSame(2023, $sheet['previous_fiscal_year']);
        $this->assertSame(100.0, $sheet['categories'][0]['diff_pct']);
        // 当年に無い科目（旧科目）も、比較年度の部署合計には含まれる
        $this->assertSame(1000.0, $sheet['previous_total_amount']);
        $this->assertSame(1000.0, $sheet['previous_month_totals'][0]);
    }

    public function test_department_sheet_lists_vanished_vendors_within_compared_period()
    {
        $this->markCompanyAsSunbrain();

        // 前年: A社は4月と12月、B社は4月のみ、Z社（科目ごと消えた）は4月。当年は4月まで実績でA社だけ
        $this->seedImport(2024, 1, [
            ['department_key' => 'production', 'category' => '外注加工費', 'category_order' => 1, 'vendor_name' => 'A社', 'calendar_year' => 2024, 'calendar_month' => 4, 'amount' => 100, 'is_shared_split' => false, 'subtotal_group' => 1],
            ['department_key' => 'production', 'category' => '外注加工費', 'category_order' => 1, 'vendor_name' => 'A社', 'calendar_year' => 2024, 'calendar_month' => 12, 'amount' => 9000, 'is_shared_split' => false, 'subtotal_group' => 1],
            ['department_key' => 'production', 'category' => '外注加工費', 'category_order' => 1, 'vendor_name' => 'B社', 'calendar_year' => 2024, 'calendar_month' => 4, 'amount' => 300, 'is_shared_split' => false, 'subtotal_group' => 1],
            ['department_key' => 'production', 'category' => '旧科目', 'category_order' => 2, 'vendor_name' => 'Z社', 'calendar_year' => 2024, 'calendar_month' => 4, 'amount' => 500, 'is_shared_split' => false, 'subtotal_group' => 1],
            ['department_key' => 'production', 'category' => '運賃', 'category_order' => 3, 'vendor_name' => 'Y社', 'calendar_year' => 2024, 'calendar_month' => 12, 'amount' => 800, 'is_shared_split' => false, 'subtotal_group' => 1],
        ]);
        $this->seedImport(2025, 1, [
            ['department_key' => 'production', 'category' => '外注加工費', 'category_order' => 1, 'vendor_name' => 'A社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 150, 'is_shared_split' => false, 'subtotal_group' => 1],
        ]);

        $ytd = $this->service()->departmentSheet(2025, 'production');
        $vanished = collect($ytd['vanished_vendors']);
        // 同期間（4月まで）に払っていたB社とZ社だけ。12月だけの運賃Y社は同期間外なので含めない
        $this->assertSame(['Z社', 'B社'], $vanished->pluck('vendor_name')->all()); // 金額の大きい順
        $this->assertTrue($vanished->firstWhere('vendor_name', 'Z社')['category_vanished']);
        $this->assertFalse($vanished->firstWhere('vendor_name', 'B社')['category_vanished']);
        $this->assertSame(300.0, $vanished->firstWhere('vendor_name', 'B社')['previous_total_amount']);

        // 通年比較ではY社も含まれる
        $full = collect($this->service()->departmentSheet(2025, 'production', null, 'full')['vanished_vendors']);
        $this->assertContains('Y社', $full->pluck('vendor_name')->all());
    }

    public function test_department_sheet_has_no_vanished_vendors_without_previous_data()
    {
        $this->markCompanyAsSunbrain();
        $this->seedImport(2025, 1, [
            ['department_key' => 'production', 'category' => '外注加工費', 'category_order' => 1, 'vendor_name' => 'A社', 'calendar_year' => 2025, 'calendar_month' => 4, 'amount' => 150, 'is_shared_split' => false, 'subtotal_group' => 1],
        ]);

        $this->assertSame([], $this->service()->departmentSheet(2025, 'production')['vanished_vendors']);
    }
}
