<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesImport;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

class SalesMonthlyAnalysisIndexTest extends TestCase
{
    use RefreshesSalesDatabase;

    public function test_monthly_analysis_shows_empty_state_when_nothing_imported()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.monthly_analysis'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SalesAnalysis/MonthlyAnalysis', false)
            ->where('hasAnyData', false)
        );
    }

    public function test_monthly_analysis_initializes_with_latest_active_month()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $import = SalesImport::create([
            'department_key' => 'planning',
            'source_type' => 'monthly',
            'source_year' => 2026,
            'source_month' => 7,
            'version' => 1,
            'original_filename' => 'seed.xlsx',
            'file_sha256' => hash('sha256', 'dashboard-index-test-old'),
            'status' => 'completed',
            'imported_by' => $superadmin->id,
            'imported_at' => now(),
            'order_count' => 0,
            'detail_count' => 0,
            'total_amount' => 0,
        ]);
        SalesActiveMonth::create([
            'department_key' => 'planning',
            'sales_year' => 2026,
            'sales_month' => 7,
            'sales_import_id' => $import->id,
            'activated_by' => $superadmin->id,
            'activated_at' => now(),
        ]);

        // より新しい月（9月）も取り込み、最新月として初期表示されることを確認
        $newerImport = SalesImport::create([
            'department_key' => 'planning',
            'source_type' => 'monthly',
            'source_year' => 2026,
            'source_month' => 9,
            'version' => 1,
            'original_filename' => 'seed2.xlsx',
            'file_sha256' => hash('sha256', 'dashboard-index-test-new'),
            'status' => 'completed',
            'imported_by' => $superadmin->id,
            'imported_at' => now(),
            'order_count' => 0,
            'detail_count' => 0,
            'total_amount' => 0,
        ]);
        SalesActiveMonth::create([
            'department_key' => 'planning',
            'sales_year' => 2026,
            'sales_month' => 9,
            'sales_import_id' => $newerImport->id,
            'activated_by' => $superadmin->id,
            'activated_at' => now(),
        ]);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.monthly_analysis'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SalesAnalysis/MonthlyAnalysis', false)
            ->where('hasAnyData', true)
            ->where('initialYear', 2026)
            ->where('initialMonth', 9)
        );
    }

    public function test_monthly_analysis_shows_data_when_only_a_non_first_department_has_imports()
    {
        // 部署を1つ目（企画）に固定していると、制作・オンデマンドしか取込のない状態で
        // 「データなし」の空表示になってしまうバグの回帰テスト（2026-09-03実機検証で発覚）
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $import = SalesImport::create([
            'department_key' => 'ondemand',
            'source_type' => 'monthly',
            'source_year' => 2026,
            'source_month' => 8,
            'version' => 1,
            'original_filename' => 'ondemand.xlsx',
            'file_sha256' => hash('sha256', 'dashboard-index-test-ondemand'),
            'status' => 'completed',
            'imported_by' => $superadmin->id,
            'imported_at' => now(),
            'order_count' => 0,
            'detail_count' => 0,
            'total_amount' => 0,
        ]);
        SalesActiveMonth::create([
            'department_key' => 'ondemand',
            'sales_year' => 2026,
            'sales_month' => 8,
            'sales_import_id' => $import->id,
            'activated_by' => $superadmin->id,
            'activated_at' => now(),
        ]);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.monthly_analysis'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SalesAnalysis/MonthlyAnalysis', false)
            ->where('hasAnyData', true)
            ->where('initialDepartmentKey', 'ondemand')
            ->where('initialYear', 2026)
            ->where('initialMonth', 8)
        );
    }

    public function test_monthly_analysis_initializes_with_latest_sales_period_even_when_older_period_was_registered_more_recently()
    {
        // 2026年9月分を先に取り込んだ後、2020年分を後から追加登録しても、
        // 初期表示は「対象年月が新しい2026年9月」のままにする。
        // activated_at（取込操作時刻）を優先すると、直近の取込操作である2020年が
        // 「最新」として開いてしまう（Codexレビュー2回目 8.1 Medium-1の回帰テスト）
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $recentPeriodImport = SalesImport::create([
            'department_key' => 'planning',
            'source_type' => 'monthly',
            'source_year' => 2026,
            'source_month' => 9,
            'version' => 1,
            'original_filename' => 'recent_period.xlsx',
            'file_sha256' => hash('sha256', 'dashboard-index-test-recent-period'),
            'status' => 'completed',
            'imported_by' => $superadmin->id,
            'imported_at' => now(),
            'order_count' => 0,
            'detail_count' => 0,
            'total_amount' => 0,
        ]);
        SalesActiveMonth::create([
            'department_key' => 'planning',
            'sales_year' => 2026,
            'sales_month' => 9,
            'sales_import_id' => $recentPeriodImport->id,
            'activated_by' => $superadmin->id,
            'activated_at' => now(),
        ]);

        // その後（=activated_atはこちらの方が新しい）、古い期間（2020年1月）を追加登録する
        $olderPeriodImport = SalesImport::create([
            'department_key' => 'planning',
            'source_type' => 'monthly',
            'source_year' => 2020,
            'source_month' => 1,
            'version' => 1,
            'original_filename' => 'older_period.xlsx',
            'file_sha256' => hash('sha256', 'dashboard-index-test-older-period'),
            'status' => 'completed',
            'imported_by' => $superadmin->id,
            'imported_at' => now()->addSecond(),
            'order_count' => 0,
            'detail_count' => 0,
            'total_amount' => 0,
        ]);
        SalesActiveMonth::create([
            'department_key' => 'planning',
            'sales_year' => 2020,
            'sales_month' => 1,
            'sales_import_id' => $olderPeriodImport->id,
            'activated_by' => $superadmin->id,
            'activated_at' => now()->addSecond(),
        ]);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.monthly_analysis'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SalesAnalysis/MonthlyAnalysis', false)
            ->where('hasAnyData', true)
            ->where('initialYear', 2026)
            ->where('initialMonth', 9)
        );
    }

    /**
     * 実機フィードバック対応（2026-09-04）: 期間ナビゲーターで未登録月へ移動しURLを
     * リロードすると、以前は「その年月」に登録が無いだけで空のインポート案内画面へ
     * 戻ってしまっていた。部署に何か1件でも登録済みなら通常のインターフェースを表示する。
     */
    public function test_monthly_analysis_shows_interface_for_unregistered_month_in_query_params()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $import = SalesImport::create([
            'department_key' => 'planning',
            'source_type' => 'monthly',
            'source_year' => 2026,
            'source_month' => 9,
            'version' => 1,
            'original_filename' => 'seed.xlsx',
            'file_sha256' => hash('sha256', 'monthly-unregistered-reload-test'),
            'status' => 'completed',
            'imported_by' => $superadmin->id,
            'imported_at' => now(),
            'order_count' => 0,
            'detail_count' => 0,
            'total_amount' => 0,
        ]);
        SalesActiveMonth::create([
            'department_key' => 'planning',
            'sales_year' => 2026,
            'sales_month' => 9,
            'sales_import_id' => $import->id,
            'activated_by' => $superadmin->id,
            'activated_at' => now(),
        ]);

        // 9月は登録済みだが、URLは未登録の1月を指している状態を再現する
        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.monthly_analysis', [
            'department_key' => 'planning', 'year' => 2026, 'month' => 1,
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SalesAnalysis/MonthlyAnalysis', false)
            ->where('hasAnyData', true)
            ->where('initialYear', 2026)
            ->where('initialMonth', 1)
        );
    }

    public function test_monthly_analysis_requires_sales_analysis_access()
    {
        $leader = User::factory()->create(['user_role' => 'leader']);

        $this->actingAs($leader)
            ->get(route('superadmin.sales_analysis.monthly_analysis'))
            ->assertForbidden();
    }
}
