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
}
