<?php

namespace Tests\Feature\Portfolio;

use App\Models\Company;
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

    public function test_SuperAdminが許可を切り替えられSB以外とAdminは対象外(): void
    {
        $super = $this->makeUser('superadmin');
        $sb = $this->makeUser('user', 'sunbrain');
        $suna = $this->makeUser('user', 'general');
        $admin = $this->makeUser('admin', 'sunbrain');

        $this->actingAs($super)
            ->put(route('admin.portfolio.permissions.update', ['user' => $sb->id]), ['enabled' => true])
            ->assertRedirect();
        $this->assertTrue(PortfolioPermission::isEnabledFor($sb->id));

        foreach ([$suna, $admin] as $target) {
            $this->actingAs($super)
                ->put(route('admin.portfolio.permissions.update', ['user' => $target->id]), ['enabled' => true])
                ->assertStatus(422);
            $this->assertFalse(PortfolioPermission::isEnabledFor($target->id));
        }
    }

    public function test_一括操作は対象外ユーザーを無視する(): void
    {
        $super = $this->makeUser('superadmin');
        $sb = $this->makeUser('user', 'sunbrain');
        $suna = $this->makeUser('user', 'general');

        $this->actingAs($super)
            ->put(route('admin.portfolio.permissions.bulk'), ['user_ids' => [$sb->id, $suna->id], 'enabled' => true])
            ->assertRedirect();

        $this->assertTrue(PortfolioPermission::isEnabledFor($sb->id));
        $this->assertFalse(PortfolioPermission::isEnabledFor($suna->id));
    }
}
