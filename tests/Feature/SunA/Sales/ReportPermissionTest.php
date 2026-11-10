<?php

namespace Tests\Feature\SunA\Sales;

use App\Models\Company;
use App\Models\SunA\Sales\AuditLog;
use App\Models\SunA\Sales\ReportPermission;
use App\Models\User;
use App\Services\SunA\Sales\SunaCompanyContext;
use App\Support\SunA\Sales\ReportAccess;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\Concerns\SetsUpSunaSales;
use Tests\TestCase;

/** 売上レポートの利用許可（管理者の「利用メンバー設定」・閲覧/取込の判定）。SUNASALES_PLAN1.md §7 */
class ReportPermissionTest extends TestCase
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

    private function url(string $name = 'index'): string
    {
        return route("admin.suna.sales.permissions.{$name}");
    }

    private function sunbrainAdmin(): User
    {
        $company = Company::create(['name' => 'SB_' . uniqid(), 'code' => 'SB_' . uniqid(), 'company_type' => 'sunbrain', 'active' => true]);

        return User::factory()->create(['user_role' => 'admin', 'company_id' => $company->id]);
    }

    public function test_admin_of_sanei_sees_only_their_company_users_with_everyone_off_by_default()
    {
        $admin = $this->saneiUser('admin', ['name' => '管理者']);
        $member = $this->saneiUser('user', ['name' => '社員A']);
        $other = User::factory()->create(['user_role' => 'user', 'company_id' => $this->otherSaneiCompanyId, 'name' => '他社の人']);

        $this->actingAs($admin)->get($this->url())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SunA/Sales/Permissions', false)
                ->where('routePrefix', 'admin')
                ->has('users', 2)
                ->where('users', fn ($users) => collect($users)->pluck('name')->sort()->values()->all() === ['社員A', '管理者']
                    && collect($users)->every(fn ($u) => $u['enabled'] === false && $u['can_import'] === false))
                ->where('users', fn ($users) => collect($users)->firstWhere('name', '管理者')['role_label'] === 'Admin'));
    }

    public function test_only_admin_and_superadmin_of_sanei_can_open_the_settings()
    {
        $this->actingAs($this->saneiUser('user'))->get($this->url())->assertForbidden();
        $this->actingAs($this->saneiUser('clerk'))->get($this->url())->assertForbidden();
        // サン・ブレーン所属の Admin は不可（会社分離）
        $this->actingAs($this->sunbrainAdmin())->get($this->url())->assertForbidden();

        // SuperAdmin は、会社切替がサンエー印刷のときだけ（setUp で切替済み）
        $super = User::factory()->create(['user_role' => 'superadmin']);
        $this->actingAs($super)->get($this->url())->assertOk();
        $sunbrain = Company::create(['name' => 'SB2_' . uniqid(), 'code' => 'SB2_' . uniqid(), 'company_type' => 'sunbrain', 'active' => true]);
        $this->withSession(['superadmin_context' => ['company_id' => $sunbrain->id]]);
        $this->actingAs($super)->get($this->url())->assertForbidden();
    }

    public function test_guests_are_redirected_to_login()
    {
        $this->get($this->url())->assertRedirect();
        $this->putJson($this->url('update'), ['items' => []])->assertUnauthorized();
    }

    public function test_update_saves_view_and_import_switches_and_normalizes_import_without_view()
    {
        $admin = $this->saneiUser('admin');
        $a = $this->saneiUser('user');
        $b = $this->saneiUser('user');
        $c = $this->saneiUser('user');

        $response = $this->actingAs($admin)->putJson($this->url('update'), ['items' => [
            ['user_id' => $a->id, 'enabled' => true, 'can_import' => true],   // 閲覧＋取込
            ['user_id' => $b->id, 'enabled' => true, 'can_import' => false],  // 閲覧のみ
            ['user_id' => $c->id, 'enabled' => false, 'can_import' => true],  // 閲覧OFFで取込ON → 両方OFFに正規化（行は作らない）
        ]]);

        $response->assertOk()->assertJson(['ok' => true, 'changed' => 2]);
        $this->assertTrue(ReportPermission::where('user_id', $a->id)->first()->can_import);
        $this->assertTrue(ReportPermission::where('user_id', $b->id)->first()->enabled);
        $this->assertFalse(ReportPermission::where('user_id', $b->id)->first()->can_import);
        $this->assertNull(ReportPermission::where('user_id', $c->id)->first());
        $this->assertSame($admin->id, ReportPermission::where('user_id', $a->id)->first()->granted_by);
    }

    public function test_unchanged_rows_are_not_touched_and_other_companies_users_are_ignored()
    {
        $admin = $this->saneiUser('admin');
        $a = $this->saneiUser('user');
        $other = User::factory()->create(['user_role' => 'user', 'company_id' => $this->otherSaneiCompanyId]);
        ReportPermission::create(['user_id' => $a->id, 'enabled' => true, 'can_import' => false, 'granted_by' => 999, 'granted_at' => now()->subDay()]);

        $response = $this->actingAs($admin)->putJson($this->url('update'), ['items' => [
            ['user_id' => $a->id, 'enabled' => true, 'can_import' => false],     // 変更なし
            ['user_id' => $other->id, 'enabled' => true, 'can_import' => true],  // 他社 → 無視
        ]]);

        $response->assertOk()->assertJson(['changed' => 0]);
        $this->assertSame(999, ReportPermission::where('user_id', $a->id)->first()->granted_by); // 触っていない
        $this->assertNull(ReportPermission::where('user_id', $other->id)->first());
        SunaCompanyContext::set($this->saneiCompanyId);
        $this->assertSame(0, AuditLog::query()->count()); // 変更0件なら監査ログも作らない
    }

    public function test_update_writes_an_audit_log_without_personal_details_and_validates_input()
    {
        $admin = $this->saneiUser('admin', ['name' => '秘密の名前']);
        $a = $this->saneiUser('user', ['name' => '山田太郎']);

        $this->actingAs($admin)->putJson($this->url('update'), ['items' => [['user_id' => $a->id, 'enabled' => true, 'can_import' => false]]])->assertOk();

        SunaCompanyContext::set($this->saneiCompanyId);
        $log = AuditLog::query()->firstOrFail();
        $this->assertSame('permission_update', $log->action);
        $this->assertSame(['changed' => 1], $log->context);
        $this->assertStringNotContainsString('山田', json_encode($log->toArray(), JSON_UNESCAPED_UNICODE));

        $this->actingAs($admin)->putJson($this->url('update'), [])->assertStatus(422);
        $this->actingAs($admin)->putJson($this->url('update'), ['items' => [['user_id' => 'x', 'enabled' => 'maybe']]])->assertStatus(422);
    }

    public function test_report_access_decisions()
    {
        $user = $this->saneiUser('user');
        $admin = $this->saneiUser('admin');
        $super = User::factory()->create(['user_role' => 'superadmin']);

        // 既定は全員不可（Admin も）。SuperAdmin だけは、サンエー印刷のコンテキストで常に可
        $this->assertFalse(ReportAccess::canView($user));
        $this->assertFalse(ReportAccess::canView($admin));
        $this->actingAs($super);
        $this->assertTrue(ReportAccess::canView($super));
        $this->assertTrue(ReportAccess::canImport($super));

        ReportPermission::create(['user_id' => $user->id, 'enabled' => true, 'can_import' => false]);
        $this->assertTrue(ReportAccess::canView($user));
        $this->assertFalse(ReportAccess::canImport($user));

        // 閲覧OFFで取込ONの行があっても、取込は不可（取込は閲覧が前提）
        ReportPermission::create(['user_id' => $admin->id, 'enabled' => false, 'can_import' => true]);
        $this->assertFalse(ReportAccess::canView($admin));
        $this->assertFalse(ReportAccess::canImport($admin));

        // 管理（許可設定）は Admin/SuperAdmin のみ
        $this->assertTrue(ReportAccess::canManage($admin));
        $this->assertFalse(ReportAccess::canManage($user));
        $this->assertTrue(ReportAccess::canManage($super));
        $this->assertFalse(ReportAccess::canView(null));
    }

    public function test_users_of_sunbrain_are_denied_even_if_a_permission_row_exists()
    {
        $sunbrainUser = $this->sunbrainAdmin();
        ReportPermission::create(['user_id' => $sunbrainUser->id, 'enabled' => true, 'can_import' => true]);

        $this->assertFalse(ReportAccess::canView($sunbrainUser));
        $this->assertFalse(ReportAccess::canImport($sunbrainUser));
        $this->assertFalse(ReportAccess::canManage($sunbrainUser));
    }

    public function test_sunbrains_existing_sales_analysis_permission_screen_is_unchanged()
    {
        // サン・ブレーン用の SuperAdmin「売上分析 利用許可設定」のルート・コントローラーは、そのまま残っている
        $this->assertTrue(app('router')->has('superadmin.sales_analysis_permissions.index'));
        $this->assertTrue(app('router')->has('superadmin.sales_analysis_permissions.update'));
    }
}
