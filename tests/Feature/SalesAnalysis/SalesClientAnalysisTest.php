<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesImport;
use App\Models\Sales\SalesOrder;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

class SalesClientAnalysisTest extends TestCase
{
    use RefreshesSalesDatabase;

    private function seedMonth(string $dept, int $year, int $month, string $clientName, float $amount): void
    {
        $import = SalesImport::create([
            'department_key' => $dept,
            'source_type' => 'monthly',
            'source_year' => $year,
            'source_month' => $month,
            'version' => 1,
            'original_filename' => "{$dept}-{$year}-{$month}.xlsx",
            'file_sha256' => hash('sha256', "ca-test-{$dept}-{$year}-{$month}-" . uniqid()),
            'status' => 'completed',
            'imported_by' => 1,
            'imported_at' => now(),
            'order_count' => 1,
            'detail_count' => 1,
            'total_amount' => $amount,
        ]);

        SalesOrder::create([
            'sales_import_id' => $import->id,
            'order_number' => "CA-{$dept}-{$year}-{$month}",
            'client_name' => $clientName,
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

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.client_analysis'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SalesAnalysis/ClientAnalysis', false)
            ->where('hasAnyData', false)
        );
    }

    public function test_index_computes_registered_period_bounds()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2024, 3, 'A社', 1000.0);
        $this->seedMonth('production', 2026, 8, 'B社', 500.0);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.client_analysis'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('hasAnyData', true)
            ->where('initialStartYear', 2024)
            ->where('initialStartMonth', 3)
            ->where('initialEndYear', 2026)
            ->where('initialEndMonth', 8)
        );
    }

    /** REVIEW3 13.2-D対応（Phase 15）: 月次/年次分析の得意先クリックからの深いリンク */
    public function test_index_preselects_client_from_query_params()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2025, 6, 'A社', 1000.0);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.client_analysis', [
            'department_key' => 'planning', 'client_name' => 'A社',
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('initialDepartmentKey', 'planning')
            ->where('initialClientName', 'A社')
        );
    }

    public function test_index_defaults_to_all_departments_without_query_params()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2025, 6, 'A社', 1000.0);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.client_analysis'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('initialDepartmentKey', 'all')
            ->where('initialClientName', null)
        );
    }

    public function test_index_requires_sales_analysis_access()
    {
        $leader = User::factory()->create(['user_role' => 'leader']);

        $this->actingAs($leader)
            ->get(route('superadmin.sales_analysis.client_analysis'))
            ->assertForbidden();
    }

    public function test_ranking_endpoint_returns_expected_shape()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2025, 6, 'A社', 1000.0);
        $this->seedMonth('planning', 2025, 7, 'B社', 500.0);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.client_analysis.ranking', [
            'department_key' => 'planning',
            'start_year' => 2025,
            'start_month' => 1,
            'end_year' => 2025,
            'end_month' => 12,
        ]));

        $response->assertOk();
        $response->assertJsonPath('total_amount', 1500);
        $this->assertCount(2, $response->json('ranking'));
    }

    public function test_ranking_endpoint_accepts_all_departments()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2025, 6, 'A社', 1000.0);
        $this->seedMonth('production', 2025, 6, 'A社', 500.0);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.client_analysis.ranking', [
            'department_key' => 'all',
            'start_year' => 2025,
            'start_month' => 1,
            'end_year' => 2025,
            'end_month' => 12,
        ]));

        $response->assertOk();
        $this->assertEquals(1500.0, $response->json('ranking')[0]['amount']);
    }

    public function test_ranking_endpoint_requires_access()
    {
        $leader = User::factory()->create(['user_role' => 'leader']);

        $this->actingAs($leader)
            ->get(route('superadmin.sales_analysis.api.client_analysis.ranking', [
                'department_key' => 'planning', 'start_year' => 2025, 'start_month' => 1, 'end_year' => 2025, 'end_month' => 12,
            ]))
            ->assertForbidden();
    }

    /** REVIEW3 14章Priority A対応（Phase 15）: Top10/20＋全件詳細ドロワー契約 */
    public function test_ranking_panel_endpoint_supports_paging_and_keyword()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2025, 6, 'A社', 1000.0);
        $this->seedMonth('planning', 2025, 7, 'B社', 500.0);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.client_analysis.ranking_panel', [
            'department_key' => 'planning', 'start_year' => 2025, 'start_month' => 1, 'end_year' => 2025, 'end_month' => 12,
            'limit' => 10, 'page' => 1,
        ]));

        $response->assertOk();
        $this->assertSame(2, $response->json('total_count'));
        $this->assertSame('A社', $response->json('rows')[0]['label']);
        $this->assertEquals(1500.0, $response->json('total_amount'));
    }

    public function test_detail_endpoint_returns_yearly_and_orders()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2025, 6, 'A社', 1000.0);
        $this->seedMonth('planning', 2026, 6, 'A社', 1500.0);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.client_analysis.detail', [
            'department_key' => 'planning',
            'client_name' => 'A社',
            'start_year' => 2025,
            'start_month' => 1,
            'end_year' => 2026,
            'end_month' => 12,
        ]));

        $response->assertOk();
        $this->assertCount(2, $response->json('yearly'));
        $this->assertCount(2, $response->json('orders'));
    }

    public function test_detail_endpoint_rejects_end_year_before_start_year()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis.api.client_analysis.detail', [
                'department_key' => 'planning', 'client_name' => 'A社',
                'start_year' => 2026, 'start_month' => 1, 'end_year' => 2025, 'end_month' => 12,
            ]), ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    /**
     * REVIEW3 16.1節回帰テスト: 2025年6月〜2026年3月を選択した得意先詳細に、範囲外の
     * 2025年1〜5月・2026年4〜12月の受注が入らないこと（境界年月の絞り込み）。
     */
    public function test_detail_endpoint_restricts_boundary_years_to_selected_month_range()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2025, 5, 'A社', 100.0); // 範囲外（開始月より前）
        $this->seedMonth('planning', 2025, 6, 'A社', 200.0); // 範囲内（境界）
        $this->seedMonth('planning', 2025, 12, 'A社', 300.0); // 範囲内
        $this->seedMonth('planning', 2026, 3, 'A社', 400.0); // 範囲内（境界）
        $this->seedMonth('planning', 2026, 4, 'A社', 500.0); // 範囲外（終了月より後）

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.client_analysis.detail', [
            'department_key' => 'planning',
            'client_name' => 'A社',
            'start_year' => 2025,
            'start_month' => 6,
            'end_year' => 2026,
            'end_month' => 3,
        ]));

        $response->assertOk();
        $yearly = collect($response->json('yearly'))->keyBy('year');
        $this->assertEquals(500.0, $yearly[2025]['amount']); // 6月+12月のみ（1〜5月除外）
        $this->assertEquals(400.0, $yearly[2026]['amount']); // 3月のみ（4月以降除外）
        $this->assertCount(3, $response->json('orders'));
        $orderMonths = collect($response->json('orders'))->map(fn ($o) => [$o['sales_year'], $o['sales_month']])->all();
        $this->assertNotContains([2025, 5], $orderMonths);
        $this->assertNotContains([2026, 4], $orderMonths);
    }

    /**
     * 実機フィードバック対応（2026-09-04）: 得意先詳細のグラフで「全体に対する割合」を
     * 表示できるよう、年別推移に部署合計（company_amount）と構成比（share_pct）を追加した。
     */
    public function test_detail_endpoint_returns_company_amount_and_share_pct()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2026, 6, 'A社', 300.0);
        // 同一年月へseedMonth()を2回呼ぶとactive版が上書きされるため（既知の落とし穴、判断ログ参照）、
        // 同じimportへ2件目の受注を直接追加する
        $import = SalesImport::where('department_key', 'planning')->where('source_year', 2026)->where('source_month', 6)->first();
        SalesOrder::create([
            'sales_import_id' => $import->id,
            'order_number' => 'CA-planning-2026-6-B',
            'client_name' => 'B社',
            'product_name' => '商品A',
            'plate_date' => '2026-06-15',
            'sales_year' => 2026,
            'sales_month' => 6,
            'order_amount' => 700.0,
        ]);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.client_analysis.detail', [
            'department_key' => 'planning',
            'client_name' => 'A社',
            'start_year' => 2026,
            'start_month' => 1,
            'end_year' => 2026,
            'end_month' => 12,
        ]));

        $response->assertOk();
        $year2026 = collect($response->json('yearly'))->firstWhere('year', 2026);
        $this->assertEquals(1000.0, $year2026['company_amount']); // 300(A社)+700(B社)
        $this->assertEquals(30.0, $year2026['share_pct']); // 300/1000
    }

    /**
     * 実機バグ回帰テスト（2026-09-04）: axiosがJSのbooleanをそのままクエリ文字列へ渡すと
     * 文字列"false"になりLaravelの'boolean'ルールに拒否され422になっていた。Vue側を`? 1 : 0`に
     * 変換する修正をしたので、'0'/'1'文字列での送信を回帰テストとして固定する。
     */
    public function test_ranking_endpoint_accepts_numeric_string_consolidate_clients()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis.api.client_analysis.ranking') . '?department_key=planning&start_year=2025&start_month=1&end_year=2025&end_month=12&consolidate_clients=0')
            ->assertOk();
    }

    public function test_ranking_endpoint_rejects_literal_true_false_string_for_consolidate_clients()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis.api.client_analysis.ranking') . '?department_key=planning&start_year=2025&start_month=1&end_year=2025&end_month=12&consolidate_clients=false', ['Accept' => 'application/json'])
            ->assertStatus(422);
    }
}
