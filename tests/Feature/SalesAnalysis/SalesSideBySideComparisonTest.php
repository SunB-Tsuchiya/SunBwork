<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesImport;
use App\Models\Sales\SalesOrder;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

class SalesSideBySideComparisonTest extends TestCase
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
            'file_sha256' => hash('sha256', "sbs-test-{$dept}-{$year}-{$month}-" . uniqid()),
            'status' => 'completed',
            'imported_by' => 1,
            'imported_at' => now(),
            'order_count' => 1,
            'detail_count' => 1,
            'total_amount' => $amount,
        ]);

        SalesOrder::create([
            'sales_import_id' => $import->id,
            'order_number' => "SBS-{$dept}-{$year}-{$month}",
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

    public function test_index_renders()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.side_by_side_comparison'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('SalesAnalysis/SideBySideComparison', false));
    }

    public function test_index_requires_sales_analysis_access()
    {
        $leader = User::factory()->create(['user_role' => 'leader']);

        $this->actingAs($leader)
            ->get(route('superadmin.sales_analysis.side_by_side_comparison'))
            ->assertForbidden();
    }

    public function test_summary_endpoint_returns_year_vs_year_shape()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2024, 3, 1000.0);
        $this->seedMonth('planning', 2025, 3, 1500.0);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.side_by_side_comparison', [
            'department_key' => 'planning',
            'period_a' => ['type' => 'year', 'year' => 2024],
            'period_b' => ['type' => 'year', 'year' => 2025],
        ]));

        $response->assertOk();
        $response->assertJsonPath('period_a.amount', 1000);
        $response->assertJsonPath('period_b.amount', 1500);
        $response->assertJsonPath('diff.amount', 500);
        $this->assertArrayHasKey('clients', $response->json());
        $this->assertArrayHasKey('categories', $response->json());
        $this->assertArrayHasKey('items', $response->json());
    }

    public function test_summary_endpoint_accepts_month_vs_month()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2025, 8, 1000.0);
        $this->seedMonth('planning', 2025, 9, 1200.0);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.side_by_side_comparison', [
            'department_key' => 'planning',
            'period_a' => ['type' => 'month', 'year' => 2025, 'month' => 8],
            'period_b' => ['type' => 'month', 'year' => 2025, 'month' => 9],
        ]));

        $response->assertOk();
        $response->assertJsonPath('period_a.amount', 1000);
        $response->assertJsonPath('period_b.amount', 1200);
    }

    public function test_summary_endpoint_requires_month_when_type_is_month()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis.api.side_by_side_comparison', [
                'department_key' => 'planning',
                'period_a' => ['type' => 'month', 'year' => 2025],
                'period_b' => ['type' => 'year', 'year' => 2025],
            ]), ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_summary_endpoint_rejects_unknown_department()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis.api.side_by_side_comparison', [
                'department_key' => 'unknown',
                'period_a' => ['type' => 'year', 'year' => 2025],
                'period_b' => ['type' => 'year', 'year' => 2026],
            ]), ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_summary_endpoint_requires_access()
    {
        $leader = User::factory()->create(['user_role' => 'leader']);

        $this->actingAs($leader)
            ->get(route('superadmin.sales_analysis.api.side_by_side_comparison', [
                'department_key' => 'planning',
                'period_a' => ['type' => 'year', 'year' => 2025],
                'period_b' => ['type' => 'year', 'year' => 2026],
            ]))
            ->assertForbidden();
    }

    public function test_summary_endpoint_accepts_all_departments()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2025, 4, 1000.0);
        $this->seedMonth('production', 2025, 4, 500.0);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.side_by_side_comparison', [
            'department_key' => 'all',
            'period_a' => ['type' => 'month', 'year' => 2025, 'month' => 4],
            'period_b' => ['type' => 'month', 'year' => 2025, 'month' => 4],
        ]));

        $response->assertOk();
        $response->assertJsonPath('period_a.amount', 1500);
    }

    /**
     * 実機バグ回帰テスト（2026-09-04）: axiosがJSのbooleanをそのままクエリ文字列へ渡すと
     * 文字列"false"になりLaravelの'boolean'ルールに拒否され422になっていた。Vue側を`? 1 : 0`に
     * 変換する修正をしたので、'0'/'1'文字列での送信を回帰テストとして固定する。
     */
    public function test_summary_endpoint_accepts_numeric_string_consolidate_clients()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis.api.side_by_side_comparison') . '?department_key=planning&period_a[type]=year&period_a[year]=2025&period_b[type]=year&period_b[year]=2026&consolidate_clients=0')
            ->assertOk();
    }

    public function test_summary_endpoint_rejects_literal_true_false_string_for_consolidate_clients()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis.api.side_by_side_comparison') . '?department_key=planning&period_a[type]=year&period_a[year]=2025&period_b[type]=year&period_b[year]=2026&consolidate_clients=false', ['Accept' => 'application/json'])
            ->assertStatus(422);
    }
}
