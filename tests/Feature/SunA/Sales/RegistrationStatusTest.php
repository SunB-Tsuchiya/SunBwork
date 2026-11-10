<?php

namespace Tests\Feature\SunA\Sales;

use App\Models\Company;
use App\Models\SunA\Sales\Fact;
use App\Models\SunA\Sales\Import;
use App\Models\SunA\Sales\ReportPermission;
use App\Models\User;
use App\Services\SunA\Sales\DepartmentInitializer;
use App\Services\SunA\Sales\RegistrationStatusService;
use App\Services\SunA\Sales\SunaCompanyContext;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\Concerns\SetsUpSunaSales;
use Tests\TestCase;

/** 売上レポートのトップ「データ登録状況」と、ルートの権限・会社コンテキスト（SUNASALES_PLAN1.md §7・§9 段階1） */
class RegistrationStatusTest extends TestCase
{
    use RefreshesSalesDatabase;
    use SetsUpSunaSales;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSunaSales();
    }

    protected function tearDown(): void
    {
        $this->tearDownSunaSales();
        parent::tearDown();
    }

    private function seedFacts(int $companyId, array $facts): void
    {
        SunaCompanyContext::run($companyId, function () use ($facts) {
            (new DepartmentInitializer())->ensureDefaults();
            $import = Import::create(['format' => 'client_sales_v1', 'original_filename' => '得意先別.xlsx', 'file_sha256' => hash('sha256', uniqid()), 'status' => 'completed', 'imported_by' => 1, 'imported_at' => now()]);
            foreach ($facts as [$client, $year, $month, $amount, $orders, $dept, $section]) {
                Fact::create(['client_key' => $client, 'client_name' => $client, 'sales_year' => $year, 'sales_month' => $month, 'department_key' => $dept, 'section_key' => $section, 'amount' => $amount, 'order_count' => $orders, 'import_id' => $import->id]);
            }
        });
    }

    private function permittedUser(): User
    {
        $user = $this->saneiUser('user');
        ReportPermission::create(['user_id' => $user->id, 'enabled' => true, 'can_import' => false]);

        return $user;
    }

    public function test_status_reports_registered_months_last_month_and_department_breakdown()
    {
        $this->seedFacts($this->saneiCompanyId, [
            ['A社', 2025, 1, 1000000, 10, 'dept1', null],
            ['A社', 2025, 4, 0, 0, 'dept1', null],            // 0円は登録済み（4月は0円だけ）
            ['B社', 2025, 2, 500000, 5, 'dept3', 'dept3_sec1'],
            ['A社', 2026, 1, 2000000, 20, 'dept1', null],
            ['B社', 2026, 3, 300000, 3, 'dept3', 'dept3_direct'],
        ]);
        // 別会社のデータは混ざらない
        $this->seedFacts($this->otherSaneiCompanyId, [['X社', 2030, 12, 99999999, 99, 'dept1', null]]);

        $status = SunaCompanyContext::run($this->saneiCompanyId, fn () => (new RegistrationStatusService())->status());

        $this->assertTrue($status['has_data']);
        $this->assertSame([2025, 2026], array_column($status['years'], 'year'));
        $this->assertSame(['year' => 2026, 'month' => 3], $status['last_registered']);

        $y2025 = $status['years'][0];
        $registered = array_values(array_filter($y2025['months'], fn ($m) => $m['registered']));
        $this->assertSame([1, 2, 4], array_column($registered, 'month'));
        $this->assertSame(0.0, $y2025['months'][3]['amount']);     // 4月は0円で登録済み
        $this->assertNull($y2025['months'][2]['amount']);          // 3月は未登録（0円ではない）
        $this->assertSame(2, $y2025['client_count']);
        $this->assertSame(1500000.0, $y2025['total_amount']);
        $this->assertSame(4, $y2025['last_month']);

        // 部・課別（部→課の並び。課のラベルは「営業第三部 第一課」）
        $labels = array_column($y2025['by_department'], 'label');
        $this->assertSame(['営業第一部', '営業第三部 第一課'], $labels);
        $this->assertSame(500000.0, $y2025['by_department'][1]['amount']);
        $this->assertSame('得意先別.xlsx', $status['last_import']['original_filename']);
    }

    public function test_status_without_data_is_empty()
    {
        $status = SunaCompanyContext::run($this->saneiCompanyId, fn () => (new RegistrationStatusService())->status());

        $this->assertFalse($status['has_data']);
        $this->assertSame([], $status['years']);
        $this->assertNull($status['last_registered']);
        $this->assertNull($status['last_import']);
    }

    public function test_permitted_user_can_open_the_page_and_gets_the_menu_flags()
    {
        $this->seedFacts($this->saneiCompanyId, [['A社', 2025, 1, 1000, 1, 'dept1', null]]);
        $user = $this->permittedUser();

        $this->actingAs($user)->get(route('suna.sales.report.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SunA/Sales/RegistrationStatus', false)
                ->where('status.has_data', true)
                ->where('auth.salesReportAccess', true)
                ->where('auth.salesReportCanImport', false));

        $this->actingAs($user)->getJson(route('suna.sales.report.api.registration_status'))
            ->assertOk()->assertJsonPath('years.0.year', 2025);
    }

    public function test_users_without_permission_or_from_other_companies_are_denied_and_have_no_menu_flag()
    {
        $this->seedFacts($this->saneiCompanyId, [['A社', 2025, 1, 1000, 1, 'dept1', null]]);

        // 許可なし（サンエー印刷のユーザー・Admin）
        $this->actingAs($this->saneiUser('user'))->get(route('suna.sales.report.index'))->assertForbidden();
        $this->actingAs($this->saneiUser('admin'))->get(route('suna.sales.report.index'))->assertForbidden();

        // 許可の行があっても、サン・ブレーン所属は不可
        $sunbrain = Company::create(['name' => 'SB_' . uniqid(), 'code' => 'SB_' . uniqid(), 'company_type' => 'sunbrain', 'active' => true]);
        $sbUser = User::factory()->create(['user_role' => 'user', 'company_id' => $sunbrain->id]);
        ReportPermission::create(['user_id' => $sbUser->id, 'enabled' => true, 'can_import' => true]);
        $this->actingAs($sbUser)->get(route('suna.sales.report.index'))->assertForbidden();
        $this->actingAs($sbUser)->getJson(route('suna.sales.report.api.registration_status'))->assertForbidden();
    }

    public function test_superadmin_sees_only_the_company_selected_by_the_company_switcher()
    {
        $this->seedFacts($this->saneiCompanyId, [['A社', 2025, 1, 1000, 1, 'dept1', null]]);
        $this->seedFacts($this->otherSaneiCompanyId, [['B社', 2024, 5, 7, 1, 'dept2', null]]);
        $super = User::factory()->create(['user_role' => 'superadmin']);

        // 会社切替=サンエー印刷 → サンエー印刷の年だけ
        $this->actingAs($super)->getJson(route('suna.sales.report.api.registration_status'))
            ->assertOk()->assertJsonPath('years.0.year', 2025)->assertJsonCount(1, 'years');

        // 会社切替=別の一般会社 → その会社の年だけ（混ざらない）
        $this->withSession(['superadmin_context' => ['company_id' => $this->otherSaneiCompanyId]]);
        $this->actingAs($super)->getJson(route('suna.sales.report.api.registration_status'))
            ->assertOk()->assertJsonPath('years.0.year', 2024)->assertJsonCount(1, 'years');

        // 会社切替=サン・ブレーン → 不可
        $sunbrain = Company::create(['name' => 'SB_' . uniqid(), 'code' => 'SB_' . uniqid(), 'company_type' => 'sunbrain', 'active' => true]);
        $this->withSession(['superadmin_context' => ['company_id' => $sunbrain->id]]);
        $this->actingAs($super)->getJson(route('suna.sales.report.api.registration_status'))->assertForbidden();
    }

    public function test_company_context_is_cleared_after_the_request()
    {
        $user = $this->permittedUser();

        $this->actingAs($user)->get(route('suna.sales.report.index'))->assertOk();

        $this->assertNull(SunaCompanyContext::id());
    }
}
