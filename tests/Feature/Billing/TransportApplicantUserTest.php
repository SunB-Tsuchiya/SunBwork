<?php

namespace Tests\Feature\Billing;

use App\Models\Company;
use App\Models\TransportExpense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 交通費: ユーザー用の入口（/user/billing/transport）。一般ユーザー（user ロール）が、自分の分を申請できる */
class TransportApplicantUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['transport.enforce_deadline' => false]);   // 既存のテストは、過去の月で入力するため、期限の強制を切る（期限の検証は TransportDeadlineTest）
        config(['applications.enforce_access' => false]);   // 申請者管理の制限は、TransportApplicantAccessTest で検証
    }

    private function user(string $role = 'user', ?Company $c = null): User
    {
        $c ??= Company::create(['name' => '株式会社サン・ブレーン', 'code' => 'SB' . random_int(1000, 9999), 'company_type' => 'sunbrain', 'active' => true]);
        return User::factory()->create(['user_role' => $role, 'company_id' => $c->id]);
    }

    private function body(array $over = []): array
    {
        return ['billing_month' => '2026-08', 'charge_department_id' => null, 'items' => [[
            'occurrence_date' => '2026-08-05', 'destination' => '小台工場', 'purpose' => 'round_trip', 'purpose_text' => '',
            'station_from' => '田端', 'station_to' => '宮城都営住宅前', 'fare_type' => 'ic', 'amount' => 840, 'base_amount' => 420, 'auto_double' => true,
        ]]] + $over;
    }

    public function test_一般ユーザーが入力_自動保存_申請_取り下げ_削除をできる(): void
    {
        $u = $this->user('user');

        $this->actingAs($u)->get(route('user.billing.transport.index'))
            ->assertOk()->assertInertia(fn ($p) => $p->where('routeBase', 'user.billing.transport.')->where('month', now()->format('Y-m')));

        // 自動保存（JSON）
        $id = $this->actingAs($u)->postJson(route('user.billing.transport.autosave'), $this->body())->assertOk()->json('id');
        $this->assertSame($u->id, TransportExpense::find($id)->user_id);

        // 保存（リダイレクト先も、ユーザー用の画面）
        $this->actingAs($u)->post(route('user.billing.transport.store'), $this->body())
            ->assertRedirect(route('user.billing.transport.index', ['month' => '2026-08']));

        // 申請 → 取り下げ
        $this->actingAs($u)->post(route('user.billing.transport.submit', ['expense' => $id]))
            ->assertRedirect(route('user.billing.transport.index', ['month' => '2026-08']));
        $this->assertSame('submitted', TransportExpense::find($id)->status);
        $this->actingAs($u)->post(route('user.billing.transport.withdraw', ['expense' => $id]));
        $this->assertSame('draft', TransportExpense::find($id)->status);

        // PDF・Excel（本人の分）と、登録した行先
        $this->actingAs($u)->get(route('user.billing.transport.excel', ['expense' => $id]))->assertOk();
        $this->actingAs($u)->get(route('user.billing.transport.pdf', ['expense' => $id]))->assertOk();
        $this->actingAs($u)->post(route('user.billing.transport.presets.store'), [
            'destination' => '小台工場', 'purpose' => 'round_trip', 'station_from' => '田端', 'station_to' => '宮城都営住宅前', 'fare_type' => 'ic', 'base_amount' => 420, 'auto_double' => true,
        ])->assertSessionHasNoErrors();

        $this->actingAs($u)->delete(route('user.billing.transport.destroy', ['expense' => $id]));
        $this->assertNull(TransportExpense::find($id));
    }

    public function test_他人の申請は見られず_触れない(): void
    {
        $owner = $this->user('user');
        $other = $this->user('user');
        $id = $this->actingAs($owner)->postJson(route('user.billing.transport.autosave'), $this->body())->json('id');

        $this->actingAs($other)->post(route('user.billing.transport.submit', ['expense' => $id]))->assertStatus(403);
        $this->actingAs($other)->delete(route('user.billing.transport.destroy', ['expense' => $id]))->assertStatus(403);
        $this->actingAs($other)->get(route('user.billing.transport.pdf', ['expense' => $id]))->assertStatus(403);
        // 他人の画面に、自分の申請は出ない
        $this->actingAs($other)->get(route('user.billing.transport.index', ['month' => '2026-08']))->assertInertia(fn ($p) => $p->where('expense', null));
    }

    public function test_申請ハブは全ロールで開け_差戻しの月を知らせる(): void
    {
        $u = $this->user('user');
        $id = $this->actingAs($u)->postJson(route('user.billing.transport.autosave'), $this->body())->json('id');
        TransportExpense::find($id)->update(['status' => 'returned']);

        $this->actingAs($u)->get(route('user.applications.index'))
            ->assertOk()->assertInertia(fn ($p) => $p->component('Billing/Applications/UserIndex', false)
                ->where('returned', ['2026-08'])->where('month', now()->format('Y-m')));

        foreach (['leader', 'coordinator', 'clerk', 'admin'] as $role) {
            $this->actingAs($this->user($role))->get(route('user.applications.index'))->assertOk();
        }
    }
}
