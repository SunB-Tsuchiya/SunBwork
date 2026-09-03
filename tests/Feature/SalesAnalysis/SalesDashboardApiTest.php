<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesImport;
use App\Models\Sales\SalesOrder;
use App\Models\User;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

class SalesDashboardApiTest extends TestCase
{
    use RefreshesSalesDatabase;

    private function seedOneOrder(int $year, int $month): void
    {
        $import = SalesImport::create([
            'department_key' => 'planning',
            'source_type' => 'monthly',
            'source_year' => $year,
            'source_month' => $month,
            'version' => 1,
            'original_filename' => 'seed.xlsx',
            'file_sha256' => hash('sha256', "api-test-{$year}-{$month}"),
            'status' => 'completed',
            'imported_by' => 1,
            'imported_at' => now(),
            'order_count' => 1,
            'detail_count' => 1,
            'total_amount' => 1000,
        ]);
        SalesOrder::create([
            'sales_import_id' => $import->id,
            'order_number' => 'K1',
            'client_name' => 'X社',
            'product_name' => '商品',
            'plate_date' => sprintf('%04d-%02d-10', $year, $month),
            'sales_year' => $year,
            'sales_month' => $month,
            'order_amount' => 1000,
        ]);
        SalesActiveMonth::create([
            'department_key' => 'planning',
            'sales_year' => $year,
            'sales_month' => $month,
            'sales_import_id' => $import->id,
            'activated_by' => 1,
            'activated_at' => now(),
        ]);
    }

    public function test_summary_endpoint_requires_access()
    {
        $leader = User::factory()->create(['user_role' => 'leader']);

        $this->actingAs($leader)
            ->get(route('superadmin.sales_analysis.api.summary', ['department_key' => 'planning', 'year' => 2026, 'month' => 9]))
            ->assertForbidden();
    }

    public function test_summary_endpoint_returns_monthly_and_fiscal_data()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedOneOrder(2026, 9);

        $response = $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis.api.summary', ['department_key' => 'planning', 'year' => 2026, 'month' => 9]));

        $response->assertOk();
        $response->assertJsonPath('monthly.current.total_amount', 1000);
        $response->assertJsonPath('fiscal_calendar.fiscal_year', 2026);
        $response->assertJsonPath('fiscal_april.fiscal_year', 2026);
    }

    public function test_summary_endpoint_rejects_unknown_department()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis.api.summary', ['department_key' => 'unknown_dept', 'year' => 2026, 'month' => 9]), ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_trend_endpoint_returns_requested_years_of_months()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedOneOrder(2026, 9);

        $response = $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis.api.trend', ['department_key' => 'planning', 'year' => 2026, 'month' => 9, 'years' => 2]));

        $response->assertOk();
        $this->assertCount(24, $response->json('trend'));
    }

    public function test_products_endpoint_requires_keyword()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis.api.products', ['department_key' => 'planning', 'year' => 2026, 'month' => 9]), ['Accept' => 'application/json'])
            ->assertStatus(422);
    }
}
