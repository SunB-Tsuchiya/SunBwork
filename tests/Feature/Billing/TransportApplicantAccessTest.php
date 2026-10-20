<?php

namespace Tests\Feature\Billing;

use App\Models\Company;
use App\Models\User;
use App\Models\UserApplicationAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 申請者管理: ユーザーごとのスイッチで、交通費の画面に入れるかを決める（サーバー側で強制） */
class TransportApplicantAccessTest extends TestCase
{
    use RefreshDatabase;

    private Company $sb;
    private Company $sanei;

    protected function setUp(): void
    {
        parent::setUp();
        config(['applications.enforce_access' => true, 'transport.enforce_deadline' => false]);
        $this->sb = Company::create(['name' => '株式会社サン・ブレーン', 'code' => 'SB' . random_int(100, 999), 'company_type' => 'sunbrain', 'active' => true]);
        $this->sanei = Company::create(['name' => '株式会社サンエー印刷', 'code' => 'SA' . random_int(100, 999), 'company_type' => 'general', 'active' => true]);
    }

    private function user(string $role, ?Company $c = null): User
    {
        return User::factory()->create(['user_role' => $role, 'company_id' => ($c ?? $this->sb)->id]);
    }

    private function grant(User $u, bool $on = true): void
    {
        UserApplicationAccess::updateOrCreate(['user_id' => $u->id, 'feature_key' => 'transport'], ['enabled' => $on]);
    }

    public function test_off_user_is_blocked_from_all_transport_screens(): void
    {
        $u = $this->user('user');
        $this->actingAs($u)->get(route('user.applications.index'))->assertForbidden();
        $this->actingAs($u)->get(route('user.billing.transport.index'))->assertForbidden();
    }

    public function test_on_user_can_open_screens(): void
    {
        $u = $this->user('user');
        $this->grant($u);
        $this->actingAs($u)->get(route('user.applications.index'))->assertOk();
        $this->actingAs($u)->get(route('user.billing.transport.index'))->assertOk();
    }

    public function test_switching_off_blocks_again(): void
    {
        $u = $this->user('user');
        $this->grant($u);
        $this->grant($u, false);
        $this->actingAs($u)->get(route('user.billing.transport.index'))->assertForbidden();
    }

    public function test_shared_prop_lists_enabled_features(): void
    {
        $u = $this->user('user');
        $this->grant($u);
        $this->actingAs($u)->get(route('user.applications.index'))
            ->assertInertia(fn ($p) => $p->where('auth.applicationAccess', ['transport']), false);
    }

    public function test_admin_can_toggle_own_company_users_only(): void
    {
        $admin = $this->user('admin');
        $mine = $this->user('user');
        $other = $this->user('user', $this->sanei);

        $this->actingAs($admin)->postJson(route('admin.applicants.update', ['feature' => 'transport']), [
            'items' => [['user_id' => $mine->id, 'enabled' => true], ['user_id' => $other->id, 'enabled' => true]],
        ])->assertOk()->assertJson(['changed' => 1]);

        $this->assertTrue(UserApplicationAccess::allows($mine, 'transport'));
        $this->assertFalse(UserApplicationAccess::allows($other, 'transport'));
        $this->assertDatabaseHas('user_application_access', ['user_id' => $mine->id, 'changed_by' => $admin->id]);
    }

    public function test_non_admin_cannot_manage(): void
    {
        $this->actingAs($this->user('clerk'))->get(route('admin.applicants.index'))->assertForbidden();
        $this->actingAs($this->user('user'))->postJson(route('admin.applicants.update', ['feature' => 'transport']), ['items' => []])->assertForbidden();
    }

    public function test_index_and_show_render_and_unknown_feature_is_404(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->get(route('admin.applicants.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.applicants.show', ['feature' => 'transport']))->assertOk();
        $this->actingAs($admin)->get(route('admin.applicants.show', ['feature' => 'nothing']))->assertNotFound();
    }
}
