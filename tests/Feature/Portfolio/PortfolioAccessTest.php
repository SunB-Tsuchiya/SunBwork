<?php

namespace Tests\Feature\Portfolio;

use App\Models\Company;
use App\Models\Department;
use App\Models\PositionTitle;
use App\Models\Portfolio\PortfolioPermission;
use App\Models\User;
use App\Services\Portfolio\PortfolioAccess;
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
            // admin エリアのポートフォリオ管理は、タブ1「メンバーのポートフォリオ」（登録者のカード一覧）
            ->assertInertia(fn ($page) => $page->component('Portfolio/Overview', false)->where('routePrefix', 'admin')->where('canManagePermissions', false));
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
            ->assertInertia(fn ($page) => $page->where('routePrefix', 'user')->where('readOnly', false)->where('ownerId', $user->id)->where('ownerName', $user->name));
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
        // タブ「利用メンバー設定」は SuperAdmin だけに出る（タブ1の画面が受け取るフラグ）
        $this->actingAs($super)->get(route('admin.portfolio.manage'))
            ->assertInertia(fn ($page) => $page->where('canManagePermissions', true));
        $this->actingAs($admin)->get(route('admin.portfolio.manage'))
            ->assertInertia(fn ($page) => $page->where('canManagePermissions', false));
    }

    public function test_SuperAdminが許可をまとめて保存でき対象外ユーザーは無視される(): void
    {
        $super = $this->makeUser('superadmin');
        $sb = $this->makeUser('user', 'sunbrain');
        $sb2 = $this->makeUser('user', 'sunbrain');
        $suna = $this->makeUser('user', 'general');
        $admin = $this->makeUser('admin', 'sunbrain');
        $other = $this->makeUser('superadmin', 'sunbrain');

        $res = $this->actingAs($super)->putJson(route('admin.portfolio.permissions.update'), ['items' => [
            ['user_id' => $sb->id, 'enabled' => true],
            ['user_id' => $sb2->id, 'enabled' => false],   // 行が無く OFF のまま = 変更なし
            ['user_id' => $suna->id, 'enabled' => true],   // サンエー印刷は対象外
            ['user_id' => $admin->id, 'enabled' => true],  // Admin は、行がなければ自動で ON。すでに ON なので、変更なし
            ['user_id' => $other->id, 'enabled' => false], // SuperAdmin は常に使えるので、スイッチは対象外
        ]])->assertOk();

        $res->assertJsonPath('ok', true)->assertJsonPath('changed', 1);
        $this->assertTrue(PortfolioPermission::isEnabledFor($sb->id));
        $this->assertDatabaseMissing('portfolio_permissions', ['user_id' => $admin->id]); // 無駄な行を作らない
        $this->assertTrue(PortfolioAccess::switchedOn($admin));
        $this->assertFalse(PortfolioPermission::isEnabledFor($suna->id));
        $this->assertDatabaseMissing('portfolio_permissions', ['user_id' => $sb2->id]); // 無駄な行を作らない
        $this->assertDatabaseMissing('portfolio_permissions', ['user_id' => $other->id]);
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

    /** 完成済み（ready）の作品を、ファイルなしで直接作る（一覧のカードのテスト用） */
    private function readyItem(User $owner, array $attrs = []): \App\Models\Portfolio\PortfolioItem
    {
        return \App\Models\Portfolio\PortfolioItem::create($attrs + [
            'user_id' => $owner->id, 'type' => 'image', 'title' => '作品', 'original_name' => 'a.jpg', 'mime' => 'image/jpeg',
            'size_bytes' => 1, 'file_path' => 'x.jpg', 'thumb_path' => 'x_t.jpg', 'status' => 'ready',
        ]);
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

    public function test_メンバーのカードは同じ並びで役職と部署を持ち部署の絞り込みも渡る(): void
    {
        $company = Company::create(['name' => 'T-' . uniqid(), 'code' => 'T' . uniqid(), 'company_type' => 'sunbrain']);
        $admin = User::factory()->create(['user_role' => 'admin', 'company_id' => $company->id]);
        PortfolioPermission::create(['user_id' => $admin->id, 'enabled' => false]); // 閲覧する Admin 自身は、一覧に出さない
        $exec = PositionTitle::create(['name' => '役員', 'sort_order' => 1]);
        $chief = PositionTitle::create(['name' => '課長', 'sort_order' => 3]);
        $deptA = Department::create(['company_id' => $company->id, 'name' => '制作部', 'code' => 'A' . uniqid(), 'sort_order' => 2]);
        $deptB = Department::create(['company_id' => $company->id, 'name' => '営業部', 'code' => 'B' . uniqid(), 'sort_order' => 1]);
        foreach ([['あ課長', $chief, $deptA], ['い役員', $exec, $deptB]] as [$name, $title, $dept]) {
            $u = $this->memberIn($company, $name, 'regular', $title, $dept);
            PortfolioPermission::create(['user_id' => $u->id, 'enabled' => true]);
            $this->readyItem($u);
        }

        $this->actingAs($admin)->get(route('admin.portfolio.manage'))
            ->assertInertia(fn ($page) => $page
                ->has('members', 2)
                // 役職の設定順（役員 → 課長）。氏名順ではない
                ->where('members.0.name', 'い役員')->where('members.0.position_title', '役員')->where('members.0.department', '営業部')
                ->where('members.1.name', 'あ課長')
                ->where('departments.0.name', '営業部')
                ->where('departments.1.name', '制作部'));
    }

    public function test_Adminは部署が未設定でも一覧に出てスイッチを操作できSuperAdminはON固定(): void
    {
        $company = Company::create(['name' => 'T-' . uniqid(), 'code' => 'T' . uniqid(), 'company_type' => 'sunbrain']);
        $dept = Department::create(['company_id' => $company->id, 'name' => '情報出版', 'code' => 'D' . uniqid(), 'sort_order' => 1]);
        $exec = PositionTitle::create(['name' => '役員', 'sort_order' => 1]);
        $viewer = User::factory()->create(['user_role' => 'superadmin', 'company_id' => $company->id]); // 画面を開く人（部署なし＝一覧に出ない）

        // Admin: 部署ありでも未設定でも出る（スイッチで決めるため）
        $adminWith = User::factory()->create(['user_role' => 'admin', 'company_id' => $company->id, 'department_id' => $dept->id, 'name' => '白須', 'position_title_id' => $exec->id]);
        $adminWithout = User::factory()->create(['user_role' => 'admin', 'company_id' => $company->id, 'name' => '伊藤', 'position_title_id' => $exec->id]);
        PortfolioPermission::create(['user_id' => $adminWith->id, 'enabled' => true]);
        // SuperAdmin: 会社と部署があれば出て、常に ON（固定）。部署が無い管理用アカウントは出ない
        $super = User::factory()->create(['user_role' => 'superadmin', 'company_id' => $company->id, 'department_id' => $dept->id, 'name' => '土屋', 'position_title_id' => $exec->id]);
        // サンエー印刷の Admin（部署あり）は出ない
        $suna = Company::create(['name' => 'S-' . uniqid(), 'code' => 'S' . uniqid(), 'company_type' => 'general']);
        $sunaDept = Department::create(['company_id' => $suna->id, 'name' => '総務', 'code' => 'G' . uniqid(), 'sort_order' => 1]);
        User::factory()->create(['user_role' => 'admin', 'company_id' => $suna->id, 'department_id' => $sunaDept->id, 'name' => 'サンエーAdmin']);
        $member = $this->memberIn($company, 'あ一般', 'regular', null, $dept);

        $this->actingAs($viewer)->get(route('admin.portfolio.permissions.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('users', 4) // 役員の 3 人（氏名は申請者管理と同じ文字コード順＝伊藤・土屋・白須）、役職なしの あ一般
                ->where('users.0.name', '伊藤')->where('users.0.role_label', 'Admin')->where('users.0.always_on', false)->where('users.0.enabled', true)->where('users.0.department', '未所属') // 行がなくても Admin は自動で ON
                ->where('users.1.name', '土屋')->where('users.1.role_label', 'SuperAdmin')->where('users.1.always_on', true)->where('users.1.enabled', true)
                ->where('users.2.name', '白須')->where('users.2.role_label', 'Admin')->where('users.2.always_on', false)->where('users.2.enabled', true)
                ->where('users.3.name', 'あ一般')->where('users.3.always_on', false)->where('users.3.role_label', null));
    }

    public function test_保存でAdminをOFFにでき戻せてSuperAdminの許可は行も作られない(): void
    {
        $company = Company::create(['name' => 'T-' . uniqid(), 'code' => 'T' . uniqid(), 'company_type' => 'sunbrain']);
        $dept = Department::create(['company_id' => $company->id, 'name' => '情報出版', 'code' => 'D' . uniqid(), 'sort_order' => 1]);
        $viewer = User::factory()->create(['user_role' => 'superadmin', 'company_id' => $company->id]);
        $super = User::factory()->create(['user_role' => 'superadmin', 'company_id' => $company->id, 'department_id' => $dept->id]);
        $admin = User::factory()->create(['user_role' => 'admin', 'company_id' => $company->id, 'department_id' => $dept->id]);
        $put = fn (array $items) => $this->actingAs($viewer)->putJson(route('admin.portfolio.permissions.update'), ['items' => $items])->assertOk();

        // 何も設定しなくても、Admin は自動で ON（自分のポートフォリオを作れる）
        $this->assertTrue(PortfolioAccess::switchedOn($admin));
        $this->assertTrue(PortfolioAccess::canEdit($admin, $admin->id));

        // OFF にすると、閲覧専用になる（画面には引き続き入れる）。SuperAdmin を OFF にしようとしても無視される（常に使える）
        $put([['user_id' => $super->id, 'enabled' => false], ['user_id' => $admin->id, 'enabled' => false]])->assertJsonPath('changed', 1);
        $this->assertDatabaseMissing('portfolio_permissions', ['user_id' => $super->id]);
        $this->assertFalse(PortfolioAccess::switchedOn($admin));
        $this->assertFalse(PortfolioAccess::canEdit($admin, $admin->id));
        $this->actingAs($admin)->get(route('admin.portfolio.manage'))->assertOk();

        // ON に戻すと、また作れる。すでに ON の状態で ON を送っても変更なし
        $put([['user_id' => $admin->id, 'enabled' => true]])->assertJsonPath('changed', 1);
        $this->assertTrue(PortfolioAccess::canEdit($admin, $admin->id));
        $put([['user_id' => $admin->id, 'enabled' => true]])->assertJsonPath('changed', 0);
    }

    public function test_Adminは自動でONで編集画面を開けOFFにしたAdminは閲覧一覧へ案内される(): void
    {
        $company = Company::create(['name' => 'T-' . uniqid(), 'code' => 'T' . uniqid(), 'company_type' => 'sunbrain']);
        $on = User::factory()->create(['user_role' => 'admin', 'company_id' => $company->id]);   // スイッチ未設定＝自動で ON
        $off = User::factory()->create(['user_role' => 'admin', 'company_id' => $company->id]);
        PortfolioPermission::create(['user_id' => $off->id, 'enabled' => false]);

        // ON: user エリア（自分の編集画面）は編集可。admin エリアの一覧は、自分のカードから編集画面へ案内できる
        $this->actingAs($on)->get(route('user.portfolio.manage'))
            ->assertOk()->assertInertia(fn ($page) => $page->where('readOnly', false)->where('ownerId', $on->id));
        $this->actingAs($on)->get(route('admin.portfolio.manage'))
            ->assertOk()->assertInertia(fn ($page) => $page->where('canEditOwn', true));

        // OFF: 自分の編集画面は開けず、メンバーを閲覧する画面（admin エリア）へ案内される。編集への案内は出ない
        $this->actingAs($off)->get(route('user.portfolio.manage'))->assertRedirect(route('admin.portfolio.manage'));
        $this->actingAs($off)->get(route('admin.portfolio.manage'))
            ->assertOk()->assertInertia(fn ($page) => $page->where('canEditOwn', false));
    }
}
