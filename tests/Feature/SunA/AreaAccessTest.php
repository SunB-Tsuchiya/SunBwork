<?php

namespace Tests\Feature\SunA;

use App\Models\Company;
use App\Models\Department;
use App\Models\PositionTitle;
use App\Models\SunA\Sales\ReportPermission;
use App\Models\User;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\Concerns\SetsUpSunaSales;
use Tests\TestCase;

/**
 * サンエー印刷の権限体系の変更（SUNAROLE_PLAN1.md）。ロールの上下ではなく、所属部署のエリア（総務・経理・営業）で見える範囲を決める。
 * サン・ブレーンの挙動が変わらないことも確かめる。
 */
class AreaAccessTest extends TestCase
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

    private function person(string $role, ?string $area): User
    {
        $user = $this->saneiUser($role);

        return $area === null ? $user : $this->inArea($user, $area);
    }

    // ── 総務・経理（Clerk 系の画面）の入口 ──────────────────────────────

    public function test_clerk_area_is_decided_by_department_area_not_by_role()
    {
        // 役割が「一般」でも、総務・経理の部署なら入れる
        foreach (['soumu', 'keiri'] as $area) {
            $this->actingAs($this->person('user', $area))->get(route('clerk.dashboard'))->assertOk();
        }
        // 古いロール（クラーク・リーダー・進行管理）でも、エリアが総務・経理でなければ入れない
        foreach (['clerk', 'leader', 'coordinator'] as $role) {
            $this->actingAs($this->person($role, null))->get(route('clerk.dashboard'))->assertForbidden();
            $this->actingAs($this->person($role, 'sales'))->get(route('clerk.dashboard'))->assertForbidden();
        }
        // 営業の部署の人・エリアなしの人は入れない
        $this->actingAs($this->person('user', 'sales'))->get(route('clerk.dashboard'))->assertForbidden();
        $this->actingAs($this->person('user', null))->get(route('clerk.dashboard'))->assertForbidden();
    }

    public function test_admin_enters_only_their_own_area()
    {
        $this->actingAs($this->person('admin', 'soumu'))->get(route('clerk.dashboard'))->assertOk();
        $this->actingAs($this->person('admin', 'keiri'))->get(route('clerk.dashboard'))->assertOk();
        $this->actingAs($this->person('admin', 'sales'))->get(route('clerk.dashboard'))->assertForbidden();
        $this->actingAs($this->person('admin', null))->get(route('clerk.dashboard'))->assertForbidden();
        // 管理者の画面は、管理者なら誰でも
        $this->actingAs($this->person('admin', 'sales'))->get(route('admin.dashboard'))->assertOk();
    }

    public function test_clerk_kind_follows_the_department_area_and_superadmin_switches()
    {
        $this->actingAs($this->person('user', 'keiri'))->get(route('clerk.dashboard'))
            ->assertInertia(fn ($p) => $p->where('auth.clerkKind', 'keiri')->where('auth.sunaCanEnterClerk', true)->where('auth.sunaCanEnterSales', false));
        $this->actingAs($this->person('user', 'soumu'))->get(route('clerk.dashboard'))
            ->assertInertia(fn ($p) => $p->where('auth.clerkKind', 'soumu'));

        // 管理者は ?kind= で切り替えられない（自分のエリアのまま）
        $this->actingAs($this->person('admin', 'soumu'))->get(route('clerk.dashboard', ['kind' => 'keiri']))
            ->assertInertia(fn ($p) => $p->where('auth.clerkKind', 'soumu'));

        // SuperAdmin は切り替えられる
        $super = User::factory()->create(['user_role' => 'superadmin', 'company_id' => null]);
        $this->actingAs($super)->get(route('clerk.dashboard', ['kind' => 'keiri']))
            ->assertInertia(fn ($p) => $p->where('auth.clerkKind', 'keiri'));
    }

    // ── 営業（売上レポート） ───────────────────────────────────────────

    public function test_sales_report_needs_both_the_sales_area_and_the_permission()
    {
        // 営業エリア＋許可 → 入れる
        $ok = $this->person('user', 'sales');
        ReportPermission::create(['user_id' => $ok->id, 'enabled' => true, 'can_import' => false]);
        $this->actingAs($ok)->get(route('suna.sales.report.index'))->assertOk();

        // 営業エリアだが許可なし → 「許可が必要」の案内（403）
        $noPermission = $this->person('user', 'sales');
        $res = $this->actingAs($noPermission)->get(route('suna.sales.report.index'));
        $res->assertForbidden();
        $this->assertStringContainsString('許可が必要', (string) $res->exception?->getMessage());
        // ただし「営業」ボタンは出る（営業エリアの人）
        $this->actingAs($noPermission)->get(route('user.dashboard'))->assertInertia(fn ($p) => $p->where('auth.sunaCanEnterSales', true)->where('auth.salesReportAccess', false));

        // 許可はあるが営業エリアではない → 入れない（管理者も同じ）
        foreach (['user', 'admin', 'leader'] as $role) {
            $other = $this->person($role, 'soumu');
            ReportPermission::create(['user_id' => $other->id, 'enabled' => true, 'can_import' => true]);
            $this->actingAs($other)->get(route('suna.sales.report.index'))->assertForbidden();
        }
        $this->actingAs($this->person('user', null))->get(route('user.dashboard'))->assertInertia(fn ($p) => $p->where('auth.sunaCanEnterSales', false));

        // 管理者でも、営業エリアで許可されていれば入れる
        $admin = $this->person('admin', 'sales');
        ReportPermission::create(['user_id' => $admin->id, 'enabled' => true, 'can_import' => false]);
        $this->actingAs($admin)->get(route('suna.sales.report.index'))->assertOk();
    }

    public function test_permission_screen_is_still_managed_by_admins_regardless_of_area()
    {
        $this->actingAs($this->person('admin', 'soumu'))->get(route('admin.suna.sales.permissions.index'))->assertOk();
    }

    // ── 部署の管理画面（エリアの設定） ───────────────────────────────────

    public function test_admin_sets_the_area_of_a_department_of_their_company()
    {
        $admin = $this->person('admin', null);
        $dept = Department::create(['company_id' => $this->saneiCompanyId, 'name' => '新部署', 'code' => 'D_' . uniqid(), 'active' => true]);

        $this->actingAs($admin)->get(route('admin.departments.index'))
            ->assertInertia(fn ($p) => $p->where('isGeneral', true)->has('areaOptions', 3));

        $this->actingAs($admin)->post(route('admin.departments.area.update', ['department' => $dept->id]), ['area' => 'keiri'])->assertRedirect();
        $this->assertSame('keiri', $dept->fresh()->area);
        $this->actingAs($admin)->post(route('admin.departments.area.update', ['department' => $dept->id]), ['area' => null])->assertRedirect();
        $this->assertNull($dept->fresh()->area);
        $this->actingAs($admin)->postJson(route('admin.departments.area.update', ['department' => $dept->id]), ['area' => 'bogus'])->assertStatus(422);

        // 他社の部署は変更できない
        $other = Department::create(['company_id' => $this->otherSaneiCompanyId, 'name' => '他社', 'code' => 'D_' . uniqid(), 'active' => true]);
        $this->actingAs($admin)->post(route('admin.departments.area.update', ['department' => $other->id]), ['area' => 'sales'])->assertForbidden();
    }

    public function test_area_is_not_settable_for_sunbrain_departments()
    {
        $sb = Company::create(['name' => 'SB_' . uniqid(), 'code' => 'SB_' . uniqid(), 'company_type' => 'sunbrain', 'active' => true]);
        $dept = Department::create(['company_id' => $sb->id, 'name' => '制作', 'code' => 'D_' . uniqid(), 'active' => true]);
        $admin = User::factory()->create(['user_role' => 'admin', 'company_id' => $sb->id]);

        $this->actingAs($admin)->get(route('admin.departments.index'))->assertInertia(fn ($p) => $p->where('isGeneral', false));
        $this->actingAs($admin)->postJson(route('admin.departments.area.update', ['department' => $dept->id]), ['area' => 'sales'])->assertStatus(422);
        $this->assertNull($dept->fresh()->area);
    }

    // ── 役職（本部長・副本部長） ─────────────────────────────────────────

    private function seedTitles(): array
    {
        $common = PositionTitle::create(['name' => '部長', 'applicable_role' => 'leader', 'sort_order' => 4]);
        $hq = PositionTitle::create(['name' => '本部長', 'applicable_role' => 'leader', 'company_type' => 'general', 'sort_order' => 3]);

        return [$common, $hq];
    }

    public function test_general_only_titles_are_hidden_by_default_and_listed_for_general()
    {
        [$common, $hq] = $this->seedTitles();

        // 既定（サン・ブレーン含む従来の画面・登録・取込）は、共通の役職だけ
        $this->assertSame([$common->id], PositionTitle::whereIn('id', [$common->id, $hq->id])->pluck('id')->all());
        $this->assertNull(PositionTitle::where('name', '本部長')->first());
        // サンエー印刷用の一覧は、共通＋専用。サン・ブレーン用（NULL）は共通だけ
        $this->assertContains($hq->id, PositionTitle::forCompanyType('general')->pluck('id')->all());
        $this->assertNotContains($hq->id, PositionTitle::forCompanyType(null)->pluck('id')->all());

        // 本部長のユーザーの役職は、取得できる
        $user = $this->saneiUser('user', ['position_title_id' => $hq->id]);
        $this->assertSame('本部長', $user->fresh()->positionTitle?->name);
    }

    public function test_user_form_lists_all_titles_with_company_type_for_the_screen_to_filter()
    {
        [$common, $hq] = $this->seedTitles();
        $super = User::factory()->create(['user_role' => 'superadmin', 'company_id' => null]);

        $titles = collect($this->actingAs($super)->get(route('admin.users.create'))->assertOk()->viewData('page')['props']['positionTitles']);
        // 共通の役職とサンエー印刷専用の役職の両方を渡し、画面が会社のタイプで絞る
        $this->assertContains($hq->id, $titles->pluck('id')->all());
        $this->assertContains($common->id, $titles->pluck('id')->all());
        $this->assertSame('general', $titles->firstWhere('id', $hq->id)['company_type']);
        $this->assertNull($titles->firstWhere('id', $common->id)['company_type']);
    }

    private function newUserPayload(int $companyId, string $role, ?int $titleId = null): array
    {
        $dept = Department::create(['company_id' => $companyId, 'name' => '部署' . uniqid(), 'code' => 'D_' . uniqid(), 'active' => true]);
        $assignment = \App\Models\Assignment::create(['department_id' => $dept->id, 'name' => '担当', 'code' => 'A_' . uniqid(), 'active' => true]);

        return [
            'name' => 'テスト太郎', 'email' => 'taro_' . uniqid() . '@example.com', 'password' => 'Password-12345', 'password_confirmation' => 'Password-12345',
            'company_id' => $companyId, 'department_id' => $dept->id, 'assignment_id' => $assignment->id,
            'user_role' => $role, 'employment_type' => 'regular', 'position_title_id' => $titleId,
        ];
    }

    public function test_user_registration_rules_for_the_general_company()
    {
        [, $hq] = $this->seedTitles();
        $super = User::factory()->create(['user_role' => 'superadmin', 'company_id' => null]);
        $this->actingAs($super);

        // サンエー印刷: 「一般（user）」＋本部長は登録できる
        $this->post(route('admin.users.store'), $this->newUserPayload($this->saneiCompanyId, 'user', $hq->id))->assertSessionHasNoErrors();
        $this->assertSame(1, User::where('company_id', $this->saneiCompanyId)->where('position_title_id', $hq->id)->count());

        // サンエー印刷: 旧ロール（leader / clerk）では新規登録できない
        foreach (['leader', 'clerk', 'coordinator'] as $role) {
            $this->post(route('admin.users.store'), $this->newUserPayload($this->saneiCompanyId, $role))->assertSessionHasErrors('user_role');
        }

        // サン・ブレーン: 従来どおり leader / clerk を登録でき、サンエー印刷専用の役職は付けられない
        $sb = Company::create(['name' => 'SB_' . uniqid(), 'code' => 'SB_' . uniqid(), 'company_type' => 'sunbrain', 'active' => true]);
        $this->post(route('admin.users.store'), $this->newUserPayload($sb->id, 'leader'))->assertSessionHasNoErrors();
        $this->post(route('admin.users.store'), $this->newUserPayload($sb->id, 'clerk'))->assertSessionHasNoErrors();
        $this->post(route('admin.users.store'), $this->newUserPayload($sb->id, 'user', $hq->id))->assertSessionHasErrors('position_title_id');
    }

    public function test_general_company_users_do_not_need_an_assignment()
    {
        $super = User::factory()->create(['user_role' => 'superadmin', 'company_id' => null]);
        $this->actingAs($super);

        // サンエー印刷: 担当なしで登録できる
        $payload = $this->newUserPayload($this->saneiCompanyId, 'user');
        unset($payload['assignment_id']);
        $this->post(route('admin.users.store'), $payload)->assertSessionHasNoErrors();
        $this->assertNull(User::where('email', $payload['email'])->first()->assignment_id);

        // サン・ブレーン: 従来どおり担当は必須
        $sb = Company::create(['name' => 'SB_' . uniqid(), 'code' => 'SB_' . uniqid(), 'company_type' => 'sunbrain', 'active' => true]);
        $payload = $this->newUserPayload($sb->id, 'user');
        unset($payload['assignment_id']);
        $this->post(route('admin.users.store'), $payload)->assertSessionHasErrors('assignment_id');
    }

    public function test_assignments_and_departments_can_be_added_from_the_company_screen()
    {
        $admin = $this->person('admin', 'soumu');
        $dept = Department::find($this->areaDepartmentId('sales'));
        $company = Company::find($this->saneiCompanyId);

        // 後から担当を足せる（code は自動で付く）。部署の追加も同じ
        $this->actingAs($admin)->put(route('admin.companies.update', ['company' => $company->id]), [
            'name' => $company->name,
            'departments' => [
                ['id' => $dept->id, 'name' => $dept->name, 'assignments' => [['name' => '営業']]],
                ['name' => '新しい部署', 'assignments' => [['name' => '担当A'], ['name' => '担当B']]],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(['営業'], $dept->assignments()->pluck('name')->all());
        $new = Department::where('company_id', $company->id)->where('name', '新しい部署')->first();
        $this->assertSame(['担当A', '担当B'], $new->assignments()->orderBy('id')->pluck('name')->all());
        $this->assertNotEmpty($new->code);
    }

    public function test_updating_an_old_role_user_keeps_the_role_unless_changed()
    {
        $super = User::factory()->create(['user_role' => 'superadmin', 'company_id' => null]);
        $old = $this->saneiUser('clerk');
        $payload = fn (string $role) => [
            'name' => $old->name, 'email' => $old->email, 'user_role' => $role, 'company_id' => $this->saneiCompanyId,
            'department_id' => $this->areaDepartmentId('soumu'), 'assignment_id' => \App\Models\Assignment::create(['department_id' => $this->areaDepartmentId('soumu'), 'name' => '担当', 'code' => 'A_' . uniqid(), 'active' => true])->id,
            'employment_type' => 'regular',
        ];

        // 元のロール（clerk）のまま保存 → 許可（ロールは変わらない）
        $this->actingAs($super)->put(route('admin.users.update', ['user' => $old->id]), $payload('clerk'))->assertSessionHasNoErrors();
        $this->assertSame('clerk', $old->fresh()->user_role);
        // 別の旧ロール（leader）への変更 → 不可。一般（user）へは可
        $this->actingAs($super)->put(route('admin.users.update', ['user' => $old->id]), $payload('leader'))->assertSessionHasErrors('user_role');
        $this->actingAs($super)->put(route('admin.users.update', ['user' => $old->id]), $payload('user'))->assertSessionHasNoErrors();
        $this->assertSame('user', $old->fresh()->user_role);
    }

    // ── ログイン直後の振り分け・サン・ブレーンが変わらないこと ─────────────

    public function test_login_redirect_goes_to_the_users_area()
    {
        $this->actingAs($this->person('user', 'soumu'))->get(route('dashboard'))->assertRedirect(route('clerk.dashboard'));
        $this->actingAs($this->person('leader', 'keiri'))->get(route('dashboard'))->assertRedirect(route('clerk.dashboard'));
        $this->actingAs($this->person('leader', 'sales'))->get(route('dashboard'))->assertRedirect(route('user.dashboard'));
        $this->actingAs($this->person('admin', 'sales'))->get(route('dashboard'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_sunbrain_clerk_area_is_unchanged()
    {
        $sb = Company::create(['name' => 'SB_' . uniqid(), 'code' => 'SB_' . uniqid(), 'company_type' => 'sunbrain', 'active' => true]);
        // サン・ブレーンは、ロールで従来どおり（部署のエリアは関係ない）
        $this->actingAs(User::factory()->create(['user_role' => 'clerk', 'company_id' => $sb->id]))->get(route('clerk.dashboard'))->assertOk();
        $this->actingAs(User::factory()->create(['user_role' => 'admin', 'company_id' => $sb->id]))->get(route('clerk.dashboard'))->assertOk();
        $this->actingAs(User::factory()->create(['user_role' => 'user', 'company_id' => $sb->id]))->get(route('clerk.dashboard'))->assertForbidden();
        $this->actingAs(User::factory()->create(['user_role' => 'clerk', 'company_id' => $sb->id]))->get(route('user.dashboard'))
            ->assertInertia(fn ($p) => $p->where('auth.sunaCanEnterClerk', false)->where('auth.sunaCanEnterSales', false)->where('auth.clerkKind', null));
    }

    public function test_area_report_command_lists_users_who_lose_access()
    {
        $this->inArea($this->saneiUser('clerk'), 'sales');            // 旧: 総務 → 新: 営業（総務に入れなくなる）
        $this->saneiUser('admin');                                    // 旧: 総務・経理 → 新: なし
        Department::create(['company_id' => $this->saneiCompanyId, 'name' => '未設定部', 'code' => 'D_' . uniqid(), 'active' => true]);

        $this->artisan('suna:area-report')
            ->expectsOutputToContain('未設定部')
            ->expectsOutputToContain('入れなくなる')
            ->assertSuccessful();
    }
}
