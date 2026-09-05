<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesImport;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderDetail;
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

    /**
     * REVIEW3 13.2-B対応（Phase 12）: 「月の推移グラフ」は5年通しの1本線から
     * 「直近$monthsヶ月＋3ヶ月移動平均」へ変更した。paramも`years`から`months`へ変更。
     */
    public function test_trend_endpoint_returns_requested_months_with_moving_average()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        for ($m = 7; $m <= 9; $m++) {
            $this->seedOneOrder(2026, $m);
        }

        $response = $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis.api.trend', ['department_key' => 'planning', 'year' => 2026, 'month' => 9, 'months' => 6]));

        $response->assertOk();
        $trend = $response->json('trend');
        $this->assertCount(6, $trend);
        $sepEntry = collect($trend)->firstWhere(fn ($m) => $m['year'] === 2026 && $m['month'] === 9);
        // 7・8・9月がすべて登録済みなので、9月時点の3ヶ月移動平均が算出できる
        $this->assertEquals(1000.0, $sepEntry['moving_avg_3m']);
    }

    /** REVIEW3 13.2-C対応（Phase 12）: 選択月だけを対象とした複数年推移 */
    public function test_same_month_history_endpoint_returns_requested_years()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedOneOrder(2026, 9);

        $response = $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis.api.same_month_history', ['department_key' => 'planning', 'year' => 2026, 'month' => 9, 'years' => 3]));

        $response->assertOk();
        $history = $response->json('history');
        $this->assertCount(3, $history);
        $this->assertSame([2024, 2025, 2026], collect($history)->pluck('year')->all());
        $this->assertEquals(1000.0, collect($history)->firstWhere('year', 2026)['amount']);
        $this->assertNull(collect($history)->firstWhere('year', 2025)['amount']);
    }

    /** REVIEW3 13.2-D対応（Phase 12）: 得意先パネルのTop10/20＋全件詳細ドロワー・モード切替 */
    public function test_clients_panel_endpoint_supports_mode_and_paging()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedOneOrder(2026, 9);

        $current = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.clients', [
            'department_key' => 'planning', 'year' => 2026, 'month' => 9, 'mode' => 'current', 'limit' => 10, 'page' => 1,
        ]));
        $current->assertOk();
        $this->assertSame('X社', $current->json('rows')[0]['label']);
        $this->assertNull($current->json('rows')[0]['diff']);

        $this->seedOneOrder(2026, 8);
        $vsPrevious = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.clients', [
            'department_key' => 'planning', 'year' => 2026, 'month' => 9, 'mode' => 'vs_previous', 'limit' => 10, 'page' => 1,
        ]));
        $vsPrevious->assertOk();
        $this->assertEquals(0.0, $vsPrevious->json('rows')[0]['diff']); // 前月・当月とも同額なので差額0
    }

    /** REVIEW3 13.1節対応（Phase 12）: 期間ナビゲーターの未登録月案内・「最新登録月」ボタン */
    public function test_summary_endpoint_reports_period_status_for_unregistered_month()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedOneOrder(2026, 6);
        $this->seedOneOrder(2026, 9);

        $response = $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis.api.summary', ['department_key' => 'planning', 'year' => 2026, 'month' => 8]));

        $response->assertOk();
        $response->assertJsonPath('period_status.has_data', false);
        $response->assertJsonPath('period_status.nearest_before.month', 6);
        $response->assertJsonPath('period_status.nearest_after.month', 9);
    }

    public function test_latest_period_endpoint_returns_most_recent_registered_month()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedOneOrder(2025, 12);
        $this->seedOneOrder(2026, 3);

        $response = $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis.api.latest_period', ['department_key' => 'planning']));

        $response->assertOk();
        $response->assertJsonPath('latest.year', 2026);
        $response->assertJsonPath('latest.month', 3);
    }

    /** REVIEW3 13.2-E対応（Phase 12）: 分類/項目パネルもTop10/20＋全件詳細ドロワーの契約に合わせる */
    public function test_categories_panel_endpoint_supports_paging_and_keyword()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedOneOrder(2026, 9);
        $order = SalesOrder::where('order_number', 'K1')->first();
        SalesOrderDetail::create([
            'sales_order_id' => $order->id,
            'source_row_number' => 1,
            'client_name' => 'X社',
            'product_name' => '商品',
            'category' => '組版',
            'item_name' => '新規',
            'format_size' => 'A4',
            'color_count' => 1,
            'quantity' => 1,
            'unit_price' => 1000,
            'line_amount' => 1000,
            'order_amount_component' => 1000,
            'plate_date' => '2026-09-10',
        ]);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.categories', [
            'department_key' => 'planning', 'year' => 2026, 'month' => 9, 'limit' => 10, 'page' => 1,
        ]));

        $response->assertOk();
        $this->assertSame(1, $response->json('total_count'));
        $this->assertNull($response->json('rows')[0]['diff']);
    }

    public function test_products_endpoint_requires_keyword()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis.api.products', ['department_key' => 'planning', 'year' => 2026, 'month' => 9]), ['Accept' => 'application/json'])
            ->assertStatus(422);
    }
}
