<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesImport;
use App\Models\Sales\SalesOrder;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

/**
 * 期別分析（4月始まり〜翌3月終わりの会計年度）画面。実機フィードバック対応で新設
 * （「年次分析」の暦年/年度トグルが分かりにくいため、独立した専用画面にした、2026-09-04）。
 */
class SalesFiscalYearAnalysisTest extends TestCase
{
    use RefreshesSalesDatabase;

    private function seedMonth(string $dept, int $year, int $month, float $amount): void
    {
        $import = SalesImport::create([
            'department_key' => $dept,
            'source_type' => 'monthly',
            'source_year' => $year,
            'source_month' => $month,
            'version' => 1,
            'original_filename' => "{$dept}-{$year}-{$month}.xlsx",
            'file_sha256' => hash('sha256', "fiscal-test-{$dept}-{$year}-{$month}-" . uniqid()),
            'status' => 'completed',
            'imported_by' => 1,
            'imported_at' => now(),
            'order_count' => 1,
            'detail_count' => 1,
            'total_amount' => $amount,
        ]);

        SalesOrder::create([
            'sales_import_id' => $import->id,
            'order_number' => "FY-{$dept}-{$year}-{$month}",
            'client_name' => 'A社',
            'product_name' => '商品A',
            'plate_date' => sprintf('%04d-%02d-15', $year, $month),
            'sales_year' => $year,
            'sales_month' => $month,
            'order_amount' => $amount,
        ]);

        SalesActiveMonth::updateOrCreate(
            ['department_key' => $dept, 'sales_year' => $year, 'sales_month' => $month],
            ['sales_import_id' => $import->id, 'activated_by' => 1, 'activated_at' => now()]
        );
    }

    public function test_index_shows_empty_state_when_nothing_imported()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.fiscal_year_analysis'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SalesAnalysis/FiscalYearAnalysis', false)
            ->where('hasAnyData', false)
        );
    }

    public function test_index_uses_query_params_for_deep_link()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('production', 2025, 5, 1000.0);

        $response = $this->actingAs($superadmin)->get(
            route('superadmin.sales_analysis.fiscal_year_analysis') . '?department_key=production&fiscal_year=2025'
        );

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SalesAnalysis/FiscalYearAnalysis', false)
            ->where('hasAnyData', true)
            ->where('initialDepartmentKey', 'production')
            ->where('initialFiscalYear', 2025)
        );
    }

    public function test_index_shows_interface_for_unregistered_fiscal_year_in_query_params()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('production', 2025, 5, 1000.0);

        $response = $this->actingAs($superadmin)->get(
            route('superadmin.sales_analysis.fiscal_year_analysis') . '?department_key=production&fiscal_year=2030'
        );

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SalesAnalysis/FiscalYearAnalysis', false)
            ->where('hasAnyData', true)
            ->where('initialFiscalYear', 2030)
        );
    }

    public function test_index_requires_sales_analysis_access()
    {
        $leader = User::factory()->create(['user_role' => 'leader']);

        $this->actingAs($leader)
            ->get(route('superadmin.sales_analysis.fiscal_year_analysis'))
            ->assertForbidden();
    }

    public function test_summary_endpoint_returns_fiscal_year_summary_shape()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2025, 4, 1000.0);
        $this->seedMonth('planning', 2024, 4, 800.0);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.fiscal_year_summary', [
            'department_key' => 'planning',
            'fiscal_year' => 2025,
        ]));

        $response->assertOk();
        $response->assertJsonPath('kpi.period_amount', 1000);
        $response->assertJsonPath('kpi.prior_period_amount', 800);
        $response->assertJsonPath('fiscal_year', 2025);
        $this->assertCount(12, $response->json('monthly'));
        $this->assertSame(4, $response->json('monthly.0.calendar_month'));
    }

    public function test_summary_endpoint_requires_access()
    {
        $leader = User::factory()->create(['user_role' => 'leader']);

        $this->actingAs($leader)
            ->get(route('superadmin.sales_analysis.api.fiscal_year_summary', ['department_key' => 'planning', 'fiscal_year' => 2025]))
            ->assertForbidden();
    }

    public function test_latest_period_endpoint_returns_most_recent_registered_fiscal_year()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2024, 4, 1000.0);
        $this->seedMonth('planning', 2026, 2, 500.0); // 2026年2月は2025年度に属する

        $response = $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis.api.fiscal_year_latest_period', ['department_key' => 'planning']));

        $response->assertOk();
        $response->assertJsonPath('latest.fiscal_year', 2025);
    }

    public function test_clients_panel_endpoint_returns_diff_vs_prior_fiscal_year()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2024, 4, 1000.0);
        $this->seedMonth('planning', 2025, 4, 1500.0);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.fiscal_year_clients', [
            'department_key' => 'planning', 'fiscal_year' => 2025, 'limit' => 10, 'page' => 1,
        ]));

        $response->assertOk();
        $this->assertSame('A社', $response->json('rows')[0]['label']);
        $this->assertEquals(500.0, $response->json('rows')[0]['diff']);
    }

    public function test_multi_year_trend_endpoint_returns_requested_fiscal_years()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2024, 4, 1000.0);
        $this->seedMonth('planning', 2026, 4, 1500.0);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.fiscal_year_multi_year_trend', [
            'department_key' => 'planning', 'fiscal_year' => 2026, 'years' => 3,
        ]));

        $response->assertOk();
        $series = $response->json('series');
        $this->assertCount(3, $series);
        $this->assertSame([2024, 2025, 2026], collect($series)->pluck('fiscal_year')->all());
    }

    public function test_products_endpoint_returns_matches_for_fiscal_year()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2025, 4, 1000.0);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.fiscal_year_products', [
            'department_key' => 'planning',
            'fiscal_year' => 2025,
            'keyword' => '商品',
        ]));

        $response->assertOk();
        $this->assertCount(1, $response->json('orders'));
    }
}
