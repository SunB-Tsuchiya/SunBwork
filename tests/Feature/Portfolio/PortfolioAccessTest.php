<?php

namespace Tests\Feature\Portfolio;

use App\Models\Company;
use App\Models\Department;
use App\Models\PositionTitle;
use App\Models\Portfolio\PortfolioPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * ポートフォリオの権限・会社ガードのテスト。
 * 方針: Admin/SuperAdmin は許可なしで画面に入れる（Admin は閲覧専用）。それ以外は個人許可がオンの人だけ。サンエー印刷は誰も使えない。
 * テスト専用DB（sunbwork_testing）でトランザクション内に閉じる。作った行はロールバックされる。
 */
class PortfolioAccessTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(string $role, string $companyType = 'sunbrain'): User
    {
        $company = Company::create(['name' => 'T-' . uniqid(), 'code' => 'T' . uniqid(), 'company_type' => $companyType]);

        return User::factory()->create(['user_role' => $role, 'company_id' => $company->id]);
    }

    public function test_SBのAdminは許可なしで入れる(): void
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->get(route('admin.portfolio.manage'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Portfolio/Manage', false)->where('routePrefix', 'admin')->where('readOnly', true));
    }

    public function test_サンエー印刷のAdminは入れない(): void
    {
        $admin = $this->makeUser('admin', 'general');
        $this->actingAs($admin)->get(route('admin.portfolio.manage'))->assertForbidden();
    }

    public function test_許可なしのユーザーは403(): void
    {
        $user = $this->makeUser('user');
        $this->actingAs($user)->get(route('user.portfolio.manage'))->assertForbidden();
    }

    public function test_許可オンのユーザーはユーザーエリアで入れる(): void
    {
        $user = $this->makeUser('user');
        PortfolioPermission::create(['user_id' => $user->id, 'enabled' => true]);
        $this->actingAs($user)->get(route('user.portfolio.manage'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('routePrefix', 'user')->where('readOnly', false)->where('ownerId', $user->id)->where('canManagePermissions', false)->where('owners', []));
    }

    public function test_許可オフは403(): void
    {
        $user = $this->makeUser('user');
        PortfolioPermission::create(['user_id' => $user->id, 'enabled' => false]);
        $this->actingAs($user)->get(route('user.portfolio.manage'))->assertForbidden();
    }

    public function test_サンエー印刷のユーザーは許可があっても入れない(): void
    {
        $user = $this->makeUser('user', 'general');
        PortfolioPermission::create(['user_id' => $user->id, 'enabled' => true]);
        $this->actingAs($user)->get(route('user.portfolio.manage'))->assertForbidden();
    }

    public function test_許可オンでもユーザーは他メンバーを選べない(): void
    {
        $user = $this->makeUser('user');
        PortfolioPermission::create(['user_id' => $user->id, 'enabled' => true]);
        $other = $this->makeUser('user');

        $this->actingAs($user)->getJson(route('portfolio.api.data', ['owner_id' => $other->id]))->assertForbidden();
    }

    public function test_権限画面はSuperAdminだけ(): void
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->get(route('admin.portfolio.permissions.index'))->assertForbidden();

        $super = $this->makeUser('superadmin');
        $this->actingAs($super)->get(route('admin.portfolio.permissions.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Portfolio/Permissions', false)->where('routePrefix', 'admin'));
        // SuperAdmin が管理画面を開いたとき、権限画面のボタンが出る（ルートが存在するため）
        $this->actingAs($super)->get(route('admin.portfolio.manage'))
            ->assertInertia(fn ($page) => $page->where('canManagePermissions', true));
        // ユーザーエリアには権限画面のルートが無いので、ボタンは出さない（Ziggy エラー防止）
        $this->actingAs($super)->get(route('user.portfolio.manage'))
            ->assertInertia(fn ($page) => $page->where('canManagePermissions', false));
    }

    public function test_SuperAdminが許可をまとめて保存でき対象外ユーザーは無視される(): void
    {
        $super = $this->makeUser('superadmin');
        $sb = $this->makeUser('user', 'sunbrain');
        $sb2 = $this->makeUser('user', 'sunbrain');
        $suna = $this->makeUser('user', 'general');
        $admin = $this->makeUser('admin', 'sunbrain');

        $res = $this->actingAs($super)->putJson(route('admin.portfolio.permissions.update'), ['items' => [
            ['user_id' => $sb->id, 'enabled' => true],
            ['user_id' => $sb2->id, 'enabled' => false],   // 行が無く OFF のまま = 変更なし
            ['user_id' => $suna->id, 'enabled' => true],   // サンエー印刷は対象外
            ['user_id' => $admin->id, 'enabled' => true],  // Admin は常時利用可のため対象外
        ]])->assertOk();

        $res->assertJsonPath('ok', true)->assertJsonPath('changed', 1);
        $this->assertTrue(PortfolioPermission::isEnabledFor($sb->id));
        $this->assertFalse(PortfolioPermission::isEnabledFor($suna->id));
        $this->assertFalse(PortfolioPermission::isEnabledFor($admin->id));
        $this->assertDatabaseMissing('portfolio_permissions', ['user_id' => $sb2->id]); // 無駄な行を作らない
        // 誰が・いつ許可したかを残す
        $row = PortfolioPermission::where('user_id', $sb->id)->first();
        $this->assertSame($super->id, $row->granted_by);
        $this->assertNotNull($row->granted_at);
    }

    public function test_変更のない行は触らず許可した人と日時を残す(): void
    {
        $super = $this->makeUser('superadmin');
        $other = $this->makeUser('superadmin');
        $sb = $this->makeUser('user', 'sunbrain');
        $this->actingAs($super)->putJson(route('admin.portfolio.permissions.update'), ['items' => [['user_id' => $sb->id, 'enabled' => true]]])->assertOk();

        // 別の人が同じ内容で保存しても、許可した人は変わらない
        $res = $this->actingAs($other)->putJson(route('admin.portfolio.permissions.update'), ['items' => [['user_id' => $sb->id, 'enabled' => true]]])->assertOk();
        $res->assertJsonPath('changed', 0);
        $this->assertSame($super->id, PortfolioPermission::where('user_id', $sb->id)->value('granted_by'));

        // OFF にすると、許可日時は消える
        $this->actingAs($other)->putJson(route('admin.portfolio.permissions.update'), ['items' => [['user_id' => $sb->id, 'enabled' => false]]])->assertOk()->assertJsonPath('changed', 1);
        $this->assertNull(PortfolioPermission::where('user_id', $sb->id)->value('granted_at'));
    }

    public function test_Adminは許可をまとめて保存できない(): void
    {
        $admin = $this->makeUser('admin');
        $sb = $this->makeUser('user', 'sunbrain');

        $this->actingAs($admin)->putJson(route('admin.portfolio.permissions.update'), ['items' => [['user_id' => $sb->id, 'enabled' => true]]])->assertForbidden();
        $this->assertFalse(PortfolioPermission::isEnabledFor($sb->id));
    }

    private function memberIn(Company $company, string $name, string $employment, ?PositionTitle $title, ?Department $dept = null): User
    {
        return User::factory()->create([
            'user_role' => 'user', 'company_id' => $company->id, 'name' => $name, 'employment_type' => $employment,
            'position_title_id' => $title?->id, 'department_id' => $dept?->id,
        ]);
    }

    public function test_メンバーの並びは雇用区分_役職の設定順_氏名の順で部署も渡る(): void
    {
        $company = Company::create(['name' => 'T-' . uniqid(), 'code' => 'T' . uniqid(), 'company_type' => 'sunbrain']);
        $super = User::factory()->create(['user_role' => 'superadmin', 'company_id' => $company->id]);
        // 役職は「役員 → 次長 → 課長」の設定順（sort_order）。名前の五十音順とは無関係に、この順番になること
        $exec = PositionTitle::create(['name' => '役員', 'sort_order' => 1]);
        $deputy = PositionTitle::create(['name' => '次長', 'sort_order' => 2]);
        $chief = PositionTitle::create(['name' => '課長', 'sort_order' => 3]);
        $deptA = Department::create(['company_id' => $company->id, 'name' => '制作部', 'code' => 'A' . uniqid(), 'sort_order' => 2]);
        $deptB = Department::create(['company_id' => $company->id, 'name' => '営業部', 'code' => 'B' . uniqid(), 'sort_order' => 1]);

        $this->memberIn($company, 'あ課長', 'regular', $chief, $deptA);
        $this->memberIn($company, 'い役員', 'regular', $exec, $deptB);
        $this->memberIn($company, 'う次長', 'regular', $deputy, $deptA);
        $this->memberIn($company, 'え無役', 'regular', null, $deptA);          // 役職なしは最後
        $this->memberIn($company, 'お契約役員', 'contract', $exec, $deptA);     // 契約社員は、正社員の後
        $this->memberIn($company, 'か派遣', 'dispatch', null, $deptA);          // 派遣は、その後

        $this->actingAs($super)->get(route('admin.portfolio.permissions.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('users', 6)
                ->where('users.0.name', 'い役員')
                ->where('users.1.name', 'う次長')
                ->where('users.2.name', 'あ課長')
                ->where('users.3.name', 'え無役')
                ->where('users.4.name', 'お契約役員')
                ->where('users.5.name', 'か派遣')
                ->where('users.0.position_title', '役員')
                ->where('users.0.department', '営業部')
                // 部署の並びは、部署の設定順（営業部 → 制作部）
                ->where('departments.0.name', '営業部')
                ->where('departments.1.name', '制作部'));
    }

    public function test_閲覧するメンバーの選択肢も同じ並びで役職と部署を持つ(): void
    {
        $company = Company::create(['name' => 'T-' . uniqid(), 'code' => 'T' . uniqid(), 'company_type' => 'sunbrain']);
        $admin = User::factory()->create(['user_role' => 'admin', 'company_id' => $company->id]);
        $exec = PositionTitle::create(['name' => '役員', 'sort_order' => 1]);
        $chief = PositionTitle::create(['name' => '課長', 'sort_order' => 3]);
        $deptA = Department::create(['company_id' => $company->id, 'name' => '制作部', 'code' => 'A' . uniqid(), 'sort_order' => 2]);
        $deptB = Department::create(['company_id' => $company->id, 'name' => '営業部', 'code' => 'B' . uniqid(), 'sort_order' => 1]);
        foreach ([['あ課長', $chief, $deptA], ['い役員', $exec, $deptB]] as [$name, $title, $dept]) {
            $u = $this->memberIn($company, $name, 'regular', $title, $dept);
            PortfolioPermission::create(['user_id' => $u->id, 'enabled' => true]);
        }

        $this->actingAs($admin)->get(route('admin.portfolio.manage'))
            ->assertInertia(fn ($page) => $page
                ->has('owners', 2)
                ->where('owners.0.name', 'い役員')
                ->where('owners.0.label', 'い役員')
                ->where('owners.0.position_title', '役員')
                ->where('owners.0.department_id', $deptB->id)
                ->where('owners.1.name', 'あ課長')
                ->where('ownerDepartments.0.name', '営業部')
                ->where('ownerDepartments.1.name', '制作部')
                ->where('ownerId', fn ($id) => $id === User::where('name', 'い役員')->value('id')));
    }
}
