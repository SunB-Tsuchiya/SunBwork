<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Company;
use App\Models\Sales\SalesExpenseImport;
use App\Models\Sales\SalesExpenseLineItem;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

/** 経費比較画面（Phase22）のHTTPフロー */
class ExpenseComparisonTest extends TestCase
{
    use RefreshesSalesDatabase;

    private function markCompanyAsSunbrain(): void
    {
        Company::find($this->salesTestCompanyId())->update(['code' => 'SUNBRAIN']);
    }

    private function seedImport(int $fiscalYear): void
    {
        $import = SalesExpenseImport::create([
            'company_id' => $this->salesTestCompanyId(),
            'fiscal_year' => $fiscalYear,
            'version' => 1,
            'original_filename' => 'seed.xlsx',
            'file_sha256' => hash('sha256', "comparison-seed-{$fiscalYear}-" . uniqid()),
            'status' => 'completed',
            'imported_by' => 1,
            'imported_at' => now(),
            'category_count' => 1,
            'line_item_count' => 1,
            'total_amount' => 10000,
        ]);

        SalesExpenseLineItem::create([
            'sales_expense_import_id' => $import->id,
            'department_key' => 'planning',
            'category' => '外注加工費',
            'vendor_name' => 'A社',
            'calendar_year' => $fiscalYear,
            'calendar_month' => 4,
            'amount' => 10000,
            'is_shared_split' => false,
        ]);
    }

    public function test_index_renders()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.expense_comparison'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('SalesAnalysis/ExpenseComparison', false));
    }

    public function test_meta_endpoint_returns_fiscal_years_and_categories()
    {
        $this->markCompanyAsSunbrain();
        $this->seedImport(2025);
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.api.expense_comparison.meta', [
            'department_key' => 'planning',
        ]));

        $response->assertOk();
        $response->assertJson(['fiscal_years' => [2025], 'categories' => ['外注加工費']]);
    }

    public function test_trend_endpoint_returns_multi_year_series()
    {
        $this->markCompanyAsSunbrain();
        $this->seedImport(2025);
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.api.expense_comparison.trend', [
            'department_key' => 'planning',
            'category' => '外注加工費',
        ]));

        $response->assertOk();
        $response->assertJsonPath('years.0.fiscal_year', 2025);
        $response->assertJsonPath('years.0.amount', 10000);
    }

    public function test_vendors_endpoint_returns_comparison()
    {
        $this->markCompanyAsSunbrain();
        $this->seedImport(2025);
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.api.expense_comparison.vendors', [
            'department_key' => 'planning',
            'category' => '外注加工費',
            'fiscal_year' => 2025,
        ]));

        $response->assertOk();
        $response->assertJsonPath('vendors.0.vendor_name', 'A社');
        $response->assertJsonPath('previous_has_data', false);
    }

    public function test_sheet_endpoint_returns_department_sheet()
    {
        $this->markCompanyAsSunbrain();
        $this->seedImport(2025);
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.api.expense_comparison.sheet', [
            'department_key' => 'planning',
            'fiscal_year' => 2025,
        ]));

        $response->assertOk();
        $response->assertJsonPath('has_data', true);
        $response->assertJsonPath('categories.0.category', '外注加工費');
        $response->assertJsonPath('categories.0.groups.0.vendors.0.vendor_name', 'A社');
        $response->assertJsonPath('previous_has_data', false);
    }

    public function test_sheet_endpoint_accepts_compare_options()
    {
        $this->markCompanyAsSunbrain();
        $this->seedImport(2025);
        $this->seedImport(2023);
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.api.expense_comparison.sheet', [
            'department_key' => 'planning',
            'fiscal_year' => 2025,
            'compare_fiscal_year' => 2023,
            'compare_period' => 'full',
        ]));

        $response->assertOk();
        $response->assertJsonPath('previous_fiscal_year', 2023);
        $response->assertJsonPath('previous_has_data', true);
        $response->assertJsonPath('compare_period', 'full');

        $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.api.expense_comparison.sheet', [
            'department_key' => 'planning',
            'fiscal_year' => 2025,
            'compare_period' => 'invalid',
        ]))->assertStatus(422);
    }

    public function test_departments_endpoint_returns_overview_and_validates()
    {
        $this->markCompanyAsSunbrain();
        $this->seedImport(2025);
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.api.expense_comparison.departments', ['fiscal_year' => 2025]));
        $response->assertOk();
        $response->assertJsonPath('has_data', true);
        $response->assertJsonPath('departments.0.department_key', 'planning');
        $response->assertJsonPath('departments.0.total_amount', 10000);
        $response->assertJsonPath('cross.0.category', '外注加工費');
        // 有効な部署に加えて共通費も末尾に含まれる
        $this->assertSame('shared', collect($response->json('departments'))->last()['department_key']);

        $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.api.expense_comparison.departments'))->assertStatus(422);
        $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.api.expense_comparison.departments', ['fiscal_year' => 2025, 'compare_period' => 'bad']))->assertStatus(422);
    }

    public function test_export_returns_xlsx_with_overview_and_department_sheets_and_logs()
    {
        $this->markCompanyAsSunbrain();
        $this->seedImport(2025);
        // 数式として解釈されうる支払先名（formula injection対策の確認用）
        $import = SalesExpenseImport::first();
        SalesExpenseLineItem::create([
            'sales_expense_import_id' => $import->id, 'department_key' => 'planning', 'category' => '外注加工費',
            'vendor_name' => '=SUM(1+1)', 'calendar_year' => 2025, 'calendar_month' => 5, 'amount' => 500, 'is_shared_split' => false,
        ]);
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.expense_comparison.export', ['fiscal_year' => 2025]));
        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString(rawurlencode('経費_55期_2025年度.xlsx'), $response->headers->get('Content-Disposition'));

        $tmp = tempnam(sys_get_temp_dir(), 'exp') . '.xlsx';
        file_put_contents($tmp, $response->getContent());
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
        @unlink($tmp);

        $names = $book->getSheetNames();
        $this->assertSame('部署比較', $names[0]);
        $this->assertCount(2, $names); // 部署比較＋データのある部署（企画）だけ

        $overview = $book->getSheet(0);
        $this->assertSame('部署', $overview->getCell('A4')->getValue());
        $this->assertSame(10500.0, (float) $overview->getCell('B5')->getValue()); // 企画の合計

        $dept = $book->getSheet(1);
        $this->assertSame('科目', $dept->getCell('A4')->getValue());
        $this->assertSame('4月', $dept->getCell('C4')->getValue());
        $this->assertSame('外注加工費', $dept->getCell('A5')->getValue());
        $this->assertSame(10500.0, (float) $dept->getCell('O5')->getValue()); // 科目行の累計
        $this->assertSame('A社', $dept->getCell('B6')->getValue());
        // 数式として解釈されず、文字列（アポストロフィ付き）になっている
        $this->assertSame("'=SUM(1+1)", $dept->getCell('B7')->getValue());
        $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING, $dept->getCell('B7')->getDataType());

        $this->assertSame(1, \App\Models\Sales\SalesAuditLog::where('action', 'export')->where('target_type', 'expense_comparison')->count());
    }

    public function test_export_validates_and_forbids()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.expense_comparison.export', ['fiscal_year' => 2025]))->assertForbidden();
    }

    public function test_export_requires_fiscal_year()
    {
        $this->markCompanyAsSunbrain();
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.expense_comparison.export'))->assertStatus(422);
    }

    public function test_sheet_endpoint_validates_parameters()
    {
        $this->markCompanyAsSunbrain();
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.api.expense_comparison.sheet', [
            'department_key' => 'unknown',
            'fiscal_year' => 2025,
        ]))->assertStatus(422);

        $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.api.expense_comparison.sheet', [
            'department_key' => 'planning',
        ]))->assertStatus(422);
    }

    public function test_endpoints_forbidden_for_non_sunbrain_company()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.api.expense_comparison.meta', [
            'department_key' => 'planning',
        ]))->assertForbidden();

        $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.api.expense_comparison.sheet', [
            'department_key' => 'planning',
            'fiscal_year' => 2025,
        ]))->assertForbidden();
    }

    public function test_leader_cannot_access_expense_comparison()
    {
        $this->markCompanyAsSunbrain();
        $leader = User::factory()->create(['user_role' => 'leader']);

        $response = $this->actingAs($leader)->get(route('superadmin.sales_analysis.expense_comparison'));

        $response->assertForbidden();
    }
}
