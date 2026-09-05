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
            'company_id' => $this->salesTestCompanyId(),
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
            ['company_id' => $this->salesTestCompanyId(), 'department_key' => $dept, 'sales_year' => $year, 'sales_month' => $month],
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

    public function test_index_reports_route_prefix_matching_actual_url_not_user_role()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $adminResponse = $this->actingAs($superadmin)->get(route('admin.sales_analysis.annual_analysis'));
        $adminResponse->assertOk();
        $adminResponse->assertInertia(fn (Assert $page) => $page->where('routePrefix', 'admin'));

        $clerkResponse = $this->actingAs($superadmin)->get(route('clerk.sales_analysis.annual_analysis'));
        $clerkResponse->assertOk();
        $clerkResponse->assertInertia(fn (Assert $page) => $page->where('routePrefix', 'clerk'));

        $superadminResponse = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.annual_analysis'));
        $superadminResponse->assertOk();
        $superadminResponse->assertInertia(fn (Assert $page) => $page->where('routePrefix', 'superadmin'));
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

    /**
     * 実機フィードバック対応（2026-09-04）: 未登録年のURLでリロードすると、以前は
     * 空のインポート案内画面へ戻ってしまっていた。部署に何か1件でも登録済みなら
     * 通常のインターフェースを表示する。
     */
    public function test_index_shows_interface_for_unregistered_year_in_query_params()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('production', 2024, 5, 1000.0);

        // 2024年は登録済みだが、URLは未登録の2030年を指している状態を再現する
        $response = $this->actingAs($superadmin)->get(
            route('superadmin.sales_analysis.annual_analysis') . '?department_key=production&year=2030'
        );

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SalesAnalysis/AnnualAnalysis', false)
            ->where('hasAnyData', true)
            ->where('initialYear', 2030)
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
        $response->assertJsonPath('consolidate_clients', false);
        $this->assertCount(12, $response->json('monthly'));
    }

    public function test_summary_endpoint_accepts_numeric_string_consolidate_clients()
    {
        // 実機バグ回帰（2026-09-04、同月比較/左右比較と同種）: axiosはbooleanを文字列"true"/"false"で
        // クエリへ渡すが、Laravelの'boolean'ルールは'0'/'1'のみ許可するため生クエリ文字列で検証する
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2025, 1, 1000.0);

        $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis.api.annual_summary') . '?department_key=planning&year=2025&consolidate_clients=1')
            ->assertOk();
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

    /** REVIEW3 13.1節対応（Phase 13）: 期間ナビゲーターの「最新年」ボタン */
    public function test_annual_latest_period_endpoint_returns_most_recent_registered_year()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2024, 3, 1000.0);
        $this->seedMonth('planning', 2026, 1, 500.0);

        $response = $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis.api.annual_latest_period', ['department_key' => 'planning']));

        $response->assertOk();
        $response->assertJsonPath('latest.year', 2026);
    }

    /** REVIEW3 14章Priority A対応（Phase 13）: 得意先パネルは常に前年同期間との差額を返す */
    public function test_annual_clients_panel_endpoint_returns_diff_vs_prior_year()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2025, 3, 1000.0);
        $this->seedMonth('planning', 2026, 3, 1500.0);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.annual_clients', [
            'department_key' => 'planning', 'year' => 2026, 'limit' => 10, 'page' => 1,
        ]));

        $response->assertOk();
        $this->assertSame('A社', $response->json('rows')[0]['label']);
        $this->assertEquals(500.0, $response->json('rows')[0]['diff']); // 1500 - 1000
    }

    /** REVIEW3 15.1節対応（Phase 13）: 分類パネルのTop10/20＋全件詳細ドロワー契約 */
    public function test_annual_categories_panel_endpoint_supports_paging()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2026, 3, 1000.0);
        $order = SalesOrder::where('order_number', 'AN-planning-2026-3')->first();
        \App\Models\Sales\SalesOrderDetail::create([
            'sales_order_id' => $order->id,
            'source_row_number' => 1,
            'client_name' => 'A社',
            'product_name' => '商品A',
            'category' => '印刷',
            'item_name' => '新規',
            'format_size' => 'A4',
            'color_count' => 1,
            'quantity' => 1,
            'unit_price' => 1000,
            'line_amount' => 1000,
            'order_amount_component' => 1000,
            'plate_date' => '2026-03-15',
        ]);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.annual_categories', [
            'department_key' => 'planning', 'year' => 2026, 'limit' => 10, 'page' => 1,
        ]));

        $response->assertOk();
        $this->assertSame(1, $response->json('total_count'));
        $this->assertSame('印刷', $response->json('rows')[0]['label']);
    }

    /** 実機フィードバック対応（2026-09-04）: 「月別売上」の3年/5年重ね表示 */
    public function test_multi_year_trend_endpoint_returns_requested_years()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2024, 3, 1000.0);
        $this->seedMonth('planning', 2026, 3, 1500.0);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.annual_multi_year_trend', [
            'department_key' => 'planning', 'year' => 2026, 'years' => 3,
        ]));

        $response->assertOk();
        $series = $response->json('series');
        $this->assertCount(3, $series);
        $this->assertSame([2024, 2025, 2026], collect($series)->pluck('year')->all());
        $this->assertEquals(1000.0, $series[0]['months'][2]);
        $this->assertNull($series[1]['months'][2]);
    }
}
