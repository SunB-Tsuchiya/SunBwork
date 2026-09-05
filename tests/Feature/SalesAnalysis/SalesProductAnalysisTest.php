<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesImport;
use App\Models\Sales\SalesOrder;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

class SalesProductAnalysisTest extends TestCase
{
    use RefreshesSalesDatabase;

    private function seedMonth(string $dept, int $year, int $month, string $clientName, string $productName, float $amount): void
    {
        $import = SalesImport::create([
            'company_id' => $this->salesTestCompanyId(),
            'department_key' => $dept,
            'source_type' => 'monthly',
            'source_year' => $year,
            'source_month' => $month,
            'version' => 1,
            'original_filename' => "{$dept}-{$year}-{$month}.xlsx",
            'file_sha256' => hash('sha256', "pa-test-{$dept}-{$year}-{$month}-" . uniqid()),
            'status' => 'completed',
            'imported_by' => 1,
            'imported_at' => now(),
            'order_count' => 1,
            'detail_count' => 1,
            'total_amount' => $amount,
        ]);

        SalesOrder::create([
            'sales_import_id' => $import->id,
            'order_number' => "PA-{$dept}-{$year}-{$month}-" . uniqid(),
            'client_name' => $clientName,
            'product_name' => $productName,
            'plate_date' => sprintf('%04d-%02d-15', $year, $month),
            'sales_year' => $year,
            'sales_month' => $month,
            'order_amount' => $amount,
        ]);

        SalesActiveMonth::updateOrCreate(
            ['company_id' => $this->salesTestCompanyId(), 'department_key' => $dept, 'sales_year' => $year, 'sales_month' => $month],
            ['sales_import_id' => $import->id, 'activated_by' => 1, 'activated_at' => now()]
        );
    }

    public function test_index_shows_empty_state_when_nothing_imported()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.product_analysis'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SalesAnalysis/ProductAnalysis', false)
            ->where('hasAnyData', false)
        );
    }

    public function test_index_preselects_product_from_query_params()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2025, 6, 'A社', '名刺', 1000.0);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.product_analysis', [
            'department_key' => 'planning', 'product_name' => '名刺',
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('initialDepartmentKey', 'planning')
            ->where('initialProductName', '名刺')
        );
    }

    public function test_index_requires_sales_analysis_access()
    {
        $leader = User::factory()->create(['user_role' => 'leader']);

        $this->actingAs($leader)
            ->get(route('superadmin.sales_analysis.product_analysis'))
            ->assertForbidden();
    }

    public function test_ranking_panel_endpoint_returns_expected_shape()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2025, 6, 'A社', '名刺', 1000.0);
        $this->seedMonth('planning', 2025, 7, 'B社', '封筒', 500.0);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.product_analysis.ranking_panel', [
            'department_key' => 'planning', 'start_year' => 2025, 'start_month' => 1, 'end_year' => 2025, 'end_month' => 12,
            'limit' => 10, 'page' => 1,
        ]));

        $response->assertOk();
        $this->assertSame(2, $response->json('total_count'));
        $this->assertSame('名刺', $response->json('rows')[0]['label']);
        $this->assertEquals(1500.0, $response->json('total_amount'));
    }

    public function test_ranking_panel_endpoint_requires_access()
    {
        $leader = User::factory()->create(['user_role' => 'leader']);

        $this->actingAs($leader)
            ->get(route('superadmin.sales_analysis.api.product_analysis.ranking_panel', [
                'department_key' => 'planning', 'start_year' => 2025, 'start_month' => 1, 'end_year' => 2025, 'end_month' => 12,
            ]))
            ->assertForbidden();
    }

    public function test_detail_endpoint_returns_yearly_orders_and_client_ranking()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2025, 6, 'A社', '名刺', 1000.0);
        $this->seedMonth('planning', 2026, 6, 'B社', '名刺', 500.0);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.product_analysis.detail', [
            'department_key' => 'planning',
            'product_name' => '名刺',
            'start_year' => 2025,
            'start_month' => 1,
            'end_year' => 2026,
            'end_month' => 12,
        ]));

        $response->assertOk();
        $this->assertCount(2, $response->json('yearly'));
        $this->assertCount(2, $response->json('orders'));
        // client_ranking は金額降順（A社1000 > B社500）
        $this->assertSame('A社', $response->json('client_ranking')[0]['client_name']);
    }

    public function test_detail_endpoint_rejects_end_year_before_start_year()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis.api.product_analysis.detail', [
                'department_key' => 'planning', 'product_name' => '名刺',
                'start_year' => 2026, 'start_month' => 1, 'end_year' => 2025, 'end_month' => 12,
            ]), ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_year_over_year_endpoint_returns_new_and_discontinued_products()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2025, 1, 'A社', '封筒', 300.0);
        $this->seedMonth('planning', 2026, 1, 'A社', 'チラシ', 900.0);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.product_analysis.year_over_year', [
            'department_key' => 'planning',
        ]));

        $response->assertOk();
        $response->assertJsonPath('has_comparison_pair', true);
        $response->assertJsonPath('latest_year', 2026);
        $response->assertJsonPath('prior_year', 2025);
        $this->assertSame('チラシ', $response->json('new_products')[0]['product_name']);
        $this->assertSame('封筒', $response->json('discontinued_products')[0]['product_name']);
    }

    public function test_year_over_year_endpoint_requires_access()
    {
        $leader = User::factory()->create(['user_role' => 'leader']);

        $this->actingAs($leader)
            ->get(route('superadmin.sales_analysis.api.product_analysis.year_over_year', ['department_key' => 'planning']))
            ->assertForbidden();
    }
}
