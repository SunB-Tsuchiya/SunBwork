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
