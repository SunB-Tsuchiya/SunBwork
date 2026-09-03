<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesImport;
use App\Models\Sales\SalesOrder;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

class SalesAnnualAnalysisTest extends TestCase
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
            'file_sha256' => hash('sha256', "annual-test-{$dept}-{$year}-{$month}-" . uniqid()),
            'status' => 'completed',
            'imported_by' => 1,
            'imported_at' => now(),
            'order_count' => 1,
            'detail_count' => 1,
            'total_amount' => $amount,
        ]);

        SalesOrder::create([
            'sales_import_id' => $import->id,
            'order_number' => "AN-{$dept}-{$year}-{$month}",
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

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.annual_analysis'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SalesAnalysis/AnnualAnalysis', false)
            ->where('hasAnyData', false)
        );
    }

    public function test_index_uses_query_params_for_deep_link_from_registration_status()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('production', 2024, 5, 1000.0);

        $response = $this->actingAs($superadmin)->get(
            route('superadmin.sales_analysis.annual_analysis') . '?department_key=production&year=2024'
        );

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SalesAnalysis/AnnualAnalysis', false)
            ->where('hasAnyData', true)
            ->where('initialDepartmentKey', 'production')
            ->where('initialYear', 2024)
        );
    }

    public function test_index_requires_sales_analysis_access()
    {
        $leader = User::factory()->create(['user_role' => 'leader']);

        $this->actingAs($leader)
            ->get(route('superadmin.sales_analysis.annual_analysis'))
            ->assertForbidden();
    }

    public function test_summary_endpoint_returns_annual_summary_shape()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2025, 1, 1000.0);
        $this->seedMonth('planning', 2024, 1, 800.0);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.annual_summary', [
            'department_key' => 'planning',
            'year' => 2025,
        ]));

        $response->assertOk();
        $response->assertJsonPath('kpi.period_amount', 1000);
        $response->assertJsonPath('kpi.prior_period_amount', 800);
        $response->assertJsonPath('comparison_mode', 'partial');
        $this->assertCount(12, $response->json('monthly'));
    }

    public function test_summary_endpoint_accepts_all_departments()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2025, 1, 1000.0);
        $this->seedMonth('production', 2025, 1, 500.0);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.annual_summary', [
            'department_key' => 'all',
            'year' => 2025,
        ]));

        $response->assertOk();
        $response->assertJsonPath('kpi.period_amount', 1500);
    }

    public function test_summary_endpoint_rejects_unknown_department()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis.api.annual_summary', ['department_key' => 'unknown', 'year' => 2025]), ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_summary_endpoint_requires_access()
    {
        $leader = User::factory()->create(['user_role' => 'leader']);

        $this->actingAs($leader)
            ->get(route('superadmin.sales_analysis.api.annual_summary', ['department_key' => 'planning', 'year' => 2025]))
            ->assertForbidden();
    }

    public function test_products_endpoint_requires_keyword()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis.api.annual_products', ['department_key' => 'planning', 'year' => 2025]), ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_products_endpoint_returns_matches_for_year()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2025, 3, 1000.0);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.annual_products', [
            'department_key' => 'planning',
            'year' => 2025,
            'keyword' => '商品',
        ]));

        $response->assertOk();
        $this->assertCount(1, $response->json('orders'));
    }
}
