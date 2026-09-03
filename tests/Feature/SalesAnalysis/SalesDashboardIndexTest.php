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
 * ホーム画面（`dashboard`ルート）は2026-09-03 Codexレビュー2回目により
 * 「データ登録状況」画面へ差し替えられた（旧Dashboard.vueの内容は`monthly_analysis`ルートへ移動、
 * SalesMonthlyAnalysisIndexTest.php参照）。
 */
class SalesDashboardIndexTest extends TestCase
{
    use RefreshesSalesDatabase;

    private function seedMonth(string $dept, int $year, int $month, float $amount, int $userId): SalesImport
    {
        $import = SalesImport::create([
            'department_key' => $dept,
            'source_type' => 'monthly',
            'source_year' => $year,
            'source_month' => $month,
            'version' => 1,
            'original_filename' => "{$dept}-{$year}-{$month}.xlsx",
            'file_sha256' => hash('sha256', "dashboard-test-{$dept}-{$year}-{$month}-" . uniqid()),
            'status' => 'completed',
            'imported_by' => $userId,
            'imported_at' => now(),
            'order_count' => $amount > 0 ? 1 : 0,
            'detail_count' => $amount > 0 ? 1 : 0,
            'total_amount' => $amount,
        ]);

        if ($amount > 0) {
            SalesOrder::create([
                'sales_import_id' => $import->id,
                'order_number' => "SEED-{$dept}-{$year}-{$month}",
                'client_name' => 'A社',
                'product_name' => '商品A',
                'plate_date' => sprintf('%04d-%02d-15', $year, $month),
                'sales_year' => $year,
                'sales_month' => $month,
                'order_amount' => $amount,
            ]);
        }

        // updateOrCreate: 同一部署・年月への再取込（SalesImportService::confirm()と同じ挙動）を
        // シミュレートできるようにする。create()だとユニーク制約違反で2回目が失敗する
        SalesActiveMonth::updateOrCreate(
            ['department_key' => $dept, 'sales_year' => $year, 'sales_month' => $month],
            ['sales_import_id' => $import->id, 'activated_by' => $userId, 'activated_at' => now()]
        );

        return $import;
    }

    public function test_dashboard_renders_registration_status_page()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SalesAnalysis/RegistrationStatus', false)
            ->has('enabledDepartmentKeys')
            ->has('departmentLabels')
        );
    }

    public function test_dashboard_requires_sales_analysis_access()
    {
        $leader = User::factory()->create(['user_role' => 'leader']);

        $this->actingAs($leader)
            ->get(route('superadmin.sales_analysis.dashboard'))
            ->assertForbidden();
    }

    public function test_registration_status_api_returns_empty_years_when_nothing_imported()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.registration_status', ['department_key' => 'planning']));

        $response->assertOk();
        $response->assertJson(['department_key' => 'planning', 'years' => []]);
    }

    public function test_registration_status_api_reports_month_states_and_year_summary()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->seedMonth('planning', 2025, 3, 1000.0, $superadmin->id);
        $this->seedMonth('planning', 2025, 4, 0.0, $superadmin->id); // 登録済みだが売上0円

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.registration_status', ['department_key' => 'planning']));

        $response->assertOk();
        $years = $response->json('years');
        $this->assertCount(1, $years);
        $this->assertSame(2025, $years[0]['year']);
        $this->assertFalse($years[0]['is_current_year']);
        $this->assertSame(2, $years[0]['registered_month_count']);
        $this->assertSame(12, $years[0]['total_due_month_count']);

        $months = collect($years[0]['months'])->keyBy('month');
        $this->assertSame('has_sales', $months[3]['state']);
        $this->assertSame('zero', $months[4]['state']);
        $this->assertSame('no_data', $months[1]['state']);
    }

    public function test_registration_status_api_flags_needs_review_when_month_reimported()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->seedMonth('planning', 2025, 5, 1000.0, $superadmin->id);
        // 同じ部署・年月を再取込（active pointerが切り替わる＝created_at !== updated_atになる）。
        // created_at/updated_atは秒精度のため、同一秒内の連続呼び出しでは差が出ない。1秒進めて確実に検証する
        $this->travel(1)->seconds();
        $this->seedMonth('planning', 2025, 5, 1500.0, $superadmin->id);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.registration_status', ['department_key' => 'planning']));

        $months = collect($response->json('years')[0]['months'])->keyBy('month');
        $this->assertTrue($months[5]['needs_review']);
    }

    public function test_registration_status_api_distinguishes_future_months_from_gaps_in_current_year()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $currentYear = (int) now()->format('Y');
        $currentMonth = (int) now()->format('n');

        // 過去月（今月より前）のうち1ヶ月だけ登録し、他の過去月は「欠落」、今月より後は「未来」になることを確認する
        $pastMonth = max(1, $currentMonth - 1);
        if ($pastMonth === $currentMonth) {
            $this->markTestSkipped('1月はテスト対象の過去月が作れないためスキップ');
        }
        $this->seedMonth('planning', $currentYear, $pastMonth, 1000.0, $superadmin->id);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.registration_status', ['department_key' => 'planning']));

        $year = collect($response->json('years'))->firstWhere('year', $currentYear);
        $this->assertNotNull($year);
        $this->assertTrue($year['is_current_year']);
        $this->assertSame($currentMonth, $year['total_due_month_count']);

        $months = collect($year['months'])->keyBy('month');
        if ($currentMonth < 12) {
            $this->assertSame('future', $months[12]['state']);
        }
        if ($pastMonth > 1) {
            $this->assertSame('no_data', $months[1]['state']);
        }
    }

    public function test_registration_status_api_flags_issue_when_order_has_unallocated_amount()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $import = SalesImport::create([
            'department_key' => 'planning',
            'source_type' => 'monthly',
            'source_year' => 2025,
            'source_month' => 7,
            'version' => 1,
            'original_filename' => 'issue.xlsx',
            'file_sha256' => hash('sha256', 'dashboard-issue-test'),
            'status' => 'completed',
            'imported_by' => $superadmin->id,
            'imported_at' => now(),
            'order_count' => 1,
            'detail_count' => 1,
            'total_amount' => 1000,
        ]);
        SalesOrder::create([
            'sales_import_id' => $import->id,
            'order_number' => 'ISSUE-1',
            'client_name' => 'A社',
            'product_name' => '商品A',
            'plate_date' => '2025-07-15',
            'sales_year' => 2025,
            'sales_month' => 7,
            'order_amount' => 1000,
            'unallocated_amount' => -500, // 明細合計と受注金額の差額
        ]);
        SalesActiveMonth::updateOrCreate(
            ['department_key' => 'planning', 'sales_year' => 2025, 'sales_month' => 7],
            ['sales_import_id' => $import->id, 'activated_by' => $superadmin->id, 'activated_at' => now()]
        );

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.registration_status', ['department_key' => 'planning']));

        $months = collect($response->json('years')[0]['months'])->keyBy('month');
        $this->assertTrue($months[7]['has_issue']);
        $this->assertEquals(-500.0, $months[7]['issue_amount']);
    }

    public function test_registration_status_files_endpoint_returns_files_for_year()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2025, 6, 1000.0, $superadmin->id);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.registration_status.files', [
            'department_key' => 'planning',
            'year' => 2025,
        ]));

        $response->assertOk();
        $files = $response->json('files');
        $this->assertCount(1, $files);
        $this->assertSame(1, $files[0]['active_month_count']);
        $this->assertSame(1, $files[0]['total_month_count']);
        $this->assertTrue($files[0]['is_fully_active']);
    }
}
