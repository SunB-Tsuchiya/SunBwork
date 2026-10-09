<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Company;
use App\Models\Sales\SalesExpenseImport;
use App\Models\Sales\SalesExpenseLineItem;
use App\Models\User;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

/** Phase 21: 期別分析画面の経費API（FiscalYearAnalysisController::expenses/expenseVendors） */
class SalesExpenseApiTest extends TestCase
{
    use RefreshesSalesDatabase;

    private function markCompanyAsSunbrain(): void
    {
        Company::find($this->salesTestCompanyId())->update(['code' => 'SUNBRAIN']);
    }

    private function seedImport(): void
    {
        $import = SalesExpenseImport::create([
            'company_id' => $this->salesTestCompanyId(),
            'fiscal_year' => 2025,
            'version' => 1,
            'original_filename' => 'seed.xlsx',
            'file_sha256' => hash('sha256', 'expense-api-test-' . uniqid()),
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
            'category' => '主材料費',
            'vendor_name' => 'A社',
            'calendar_year' => 2025,
            'calendar_month' => 4,
            'amount' => 10000,
            'is_shared_split' => false,
        ]);
    }

    public function test_expenses_endpoint_returns_department_monthly_totals()
    {
        $this->markCompanyAsSunbrain();
        $this->seedImport();
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.api.fiscal_year_expenses', [
            'department_key' => 'planning',
            'fiscal_year' => 2025,
        ]));

        $response->assertOk();
        $response->assertJson(['has_data' => true, 'total_amount' => 10000]);
    }

    public function test_expenses_endpoint_accepts_shared_department_key()
    {
        $this->markCompanyAsSunbrain();
        $this->seedImport();
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.api.fiscal_year_expenses', [
            'department_key' => 'shared',
            'fiscal_year' => 2025,
        ]));

        $response->assertOk();
        $response->assertJson(['has_data' => false]);
    }

    public function test_expenses_endpoint_rejects_unknown_department_key()
    {
        $this->markCompanyAsSunbrain();
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.api.fiscal_year_expenses', [
            'department_key' => 'all',
            'fiscal_year' => 2025,
        ]));

        $response->assertStatus(422);
    }

    public function test_expenses_endpoint_forbidden_for_non_sunbrain_company()
    {
        // 会社コードをSUNBRAIN以外のまま（既定）
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.api.fiscal_year_expenses', [
            'department_key' => 'planning',
            'fiscal_year' => 2025,
        ]));

        $response->assertForbidden();
    }

    public function test_expense_vendors_endpoint_returns_breakdown()
    {
        $this->markCompanyAsSunbrain();
        $this->seedImport();
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.api.fiscal_year_expenses.vendors', [
            'department_key' => 'planning',
            'fiscal_year' => 2025,
            'category' => '主材料費',
        ]));

        $response->assertOk();
        $response->assertJsonPath('groups.0.vendors.0.vendor_name', 'A社');
        $response->assertJsonPath('groups.0.vendors.0.amount', 10000);
    }
}
