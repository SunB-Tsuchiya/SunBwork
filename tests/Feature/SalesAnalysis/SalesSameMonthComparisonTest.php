<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesImport;
use App\Models\Sales\SalesOrder;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

class SalesSameMonthComparisonTest extends TestCase
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
            'file_sha256' => hash('sha256', "smc-test-{$dept}-{$year}-{$month}-" . uniqid()),
            'status' => 'completed',
            'imported_by' => 1,
            'imported_at' => now(),
            'order_count' => 1,
            'detail_count' => 1,
            'total_amount' => $amount,
        ]);

        SalesOrder::create([
            'sales_import_id' => $import->id,
            'order_number' => "SMC-{$dept}-{$year}-{$month}",
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

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.same_month_comparison'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SalesAnalysis/SameMonthComparison', false)
            ->where('hasAnyData', false)
        );
    }

    public function test_index_uses_query_params_for_deep_link()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('production', 2024, 5, 1000.0);

        $response = $this->actingAs($superadmin)->get(
            route('superadmin.sales_analysis.same_month_comparison') . '?department_key=production&month=5'
        );

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SalesAnalysis/SameMonthComparison', false)
            ->where('hasAnyData', true)
            ->where('initialDepartmentKey', 'production')
            ->where('initialMonth', 5)
        );
    }

    /**
     * 実機フィードバック対応（2026-09-04）: 未登録月のURLでリロードすると、以前は
     * 空のインポート案内画面へ戻ってしまっていた。部署に何か1件でも登録済みなら
     * 通常のインターフェースを表示する。
     */
    public function test_index_shows_interface_for_unregistered_month_in_query_params()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('production', 2024, 5, 1000.0);

        // 5月は登録済みだが、URLは（どの年にも）登録の無い11月を指している状態を再現する
        $response = $this->actingAs($superadmin)->get(
            route('superadmin.sales_analysis.same_month_comparison') . '?department_key=production&month=11'
        );

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SalesAnalysis/SameMonthComparison', false)
            ->where('hasAnyData', true)
            ->where('initialMonth', 11)
        );
    }

    public function test_index_requires_sales_analysis_access()
    {
        $leader = User::factory()->create(['user_role' => 'leader']);

        $this->actingAs($leader)
            ->get(route('superadmin.sales_analysis.same_month_comparison'))
            ->assertForbidden();
    }

    public function test_summary_endpoint_returns_expected_shape()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $currentYear = (int) now()->format('Y');
        $this->seedMonth('planning', $currentYear, 3, 1000.0);
        $this->seedMonth('planning', $currentYear - 1, 3, 800.0);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.same_month_comparison', [
            'department_key' => 'planning',
            'month' => 3,
            'years' => 5,
        ]));

        $response->assertOk();
        $response->assertJsonPath('month', 3);
        $response->assertJsonPath('years_requested', 5);
        $this->assertCount(5, $response->json('years'));
        $this->assertCount(5, $response->json('yearly'));
        $this->assertArrayHasKey('client_matrix', $response->json());
        $this->assertArrayHasKey('category_item_comparison', $response->json());
    }

    public function test_summary_endpoint_accepts_all_departments()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $currentYear = (int) now()->format('Y');
        $this->seedMonth('planning', $currentYear, 4, 1000.0);
        $this->seedMonth('production', $currentYear, 4, 500.0);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.same_month_comparison', [
            'department_key' => 'all',
            'month' => 4,
        ]));

        $response->assertOk();
        $current = collect($response->json('yearly'))->firstWhere('year', $currentYear);
        $this->assertEquals(1500.0, $current['amount']);
    }

    public function test_summary_endpoint_rejects_unsupported_years_value()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis.api.same_month_comparison', [
                'department_key' => 'planning',
                'month' => 3,
                'years' => 7,
            ]), ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_summary_endpoint_rejects_unknown_department()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis.api.same_month_comparison', ['department_key' => 'unknown', 'month' => 3]), ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_summary_endpoint_requires_access()
    {
        $leader = User::factory()->create(['user_role' => 'leader']);

        $this->actingAs($leader)
            ->get(route('superadmin.sales_analysis.api.same_month_comparison', ['department_key' => 'planning', 'month' => 3]))
            ->assertForbidden();
    }

    public function test_summary_endpoint_accepts_consolidate_clients_flag()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $currentYear = (int) now()->format('Y');
        $this->seedMonth('planning', $currentYear, 2, 1000.0);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.same_month_comparison', [
            'department_key' => 'planning',
            'month' => 2,
            'consolidate_clients' => true,
        ]));

        $response->assertOk();
        $response->assertJsonPath('consolidate_clients', true);
    }

    /**
     * 実機バグ回帰テスト（2026-09-04）: axiosがJSのbooleanをそのままクエリ文字列へ渡すと
     * "consolidate_clients=false"という**文字列**"false"になるが、Laravelの'boolean'ルールは
     * '0'/'1'（および真偽値そのもの）のみを許可し文字列"true"/"false"を拒否するため422になっていた。
     * Vue側を`? 1 : 0`に変換する修正をしたので、'0'/'1'文字列での送信を回帰テストとして固定する。
     */
    public function test_summary_endpoint_accepts_numeric_string_consolidate_clients()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis.api.same_month_comparison') . '?department_key=planning&month=2&consolidate_clients=0')
            ->assertOk();
    }

    public function test_summary_endpoint_rejects_literal_true_false_string_for_consolidate_clients()
    {
        // Laravelの'boolean'ルールは文字列"true"/"false"を受け付けない既知の挙動（axiosが素のbooleanを
        // クエリへ渡すとこの形になり422を招く）。この挙動そのものをドキュメント化する回帰テスト
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis.api.same_month_comparison') . '?department_key=planning&month=2&consolidate_clients=false', ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    /** REVIEW3 13.1節対応（Phase 13）: 期間ナビゲーターの「最新登録月」ボタン（年を持たない画面向け） */
    public function test_latest_period_endpoint_returns_most_recent_registered_month()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2024, 3, 1000.0);
        $this->seedMonth('planning', 2026, 7, 500.0);

        $response = $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis.api.same_month_comparison.latest_period', ['department_key' => 'planning']));

        $response->assertOk();
        $response->assertJsonPath('latest.month', 7);
    }
}
