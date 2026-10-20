<?php

namespace Tests\Feature\Billing;

use App\Models\TransportExpense;
use App\Models\TransportPreset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 交通費: よく使う行き先の登録と、入力候補（登録＋過去の履歴） */
class TransportPresetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['transport.enforce_deadline' => false]);   // 既存のテストは、過去の月で入力するため、期限の強制を切る（期限の検証は TransportDeadlineTest）
        config(['applications.enforce_access' => false]);   // 申請者管理の制限は、TransportApplicantAccessTest で検証
    }

    private function actor(): User
    {
        return User::factory()->create(['user_role' => 'superadmin']);
    }

    private function payload(array $over = []): array
    {
        return array_merge([
            'destination' => '国際交流基金', 'purpose' => 'round_trip', 'purpose_text' => '',
            'station_from' => '田端', 'station_to' => '市ヶ谷', 'fare_type' => 'ic',
            'base_amount' => 364, 'auto_double' => true,
        ], $over);
    }

    private function item(array $over = []): array
    {
        return array_merge([
            'occurrence_date' => '2026-08-05', 'destination' => '小台工場', 'purpose' => 'round_trip', 'purpose_text' => '',
            'station_from' => '田端', 'station_to' => '宮城都営住宅前', 'fare_type' => 'ic',
            'amount' => 420, 'base_amount' => 210, 'auto_double' => true,
        ], $over);
    }

    public function test_登録でき_同じ行先と区間は上書きされ_全角と金額が整う(): void
    {
        $u = $this->actor();
        $this->actingAs($u)->post(route('user.billing.transport.presets.store'), $this->payload(['destination' => 'JR 本社', 'base_amount' => '１,２００円']))
            ->assertSessionHasNoErrors();
        $p = TransportPreset::first();
        $this->assertSame('ＪＲ　本社', $p->destination);
        $this->assertSame(1200, $p->base_amount);
        $this->assertSame(2400, $p->amount);   // 往復 ×2

        // 同じ行先・用件・区間で登録し直すと、1 件のまま金額が更新される
        $this->actingAs($u)->post(route('user.billing.transport.presets.store'), $this->payload(['destination' => 'JR 本社', 'base_amount' => 1300]));
        $this->assertSame(1, TransportPreset::count());
        $this->assertSame(1300, TransportPreset::first()->base_amount);
    }

    public function test_行先が無い_不正な用件は登録できず_往復以外の2倍は無効(): void
    {
        $u = $this->actor();
        $this->actingAs($u)->post(route('user.billing.transport.presets.store'), $this->payload(['destination' => '']))->assertSessionHasErrors('destination');
        $this->actingAs($u)->post(route('user.billing.transport.presets.store'), $this->payload(['purpose' => 'nope']))->assertSessionHasErrors('purpose');
        $this->actingAs($u)->post(route('user.billing.transport.presets.store'), $this->payload(['purpose' => 'outbound', 'auto_double' => true]));
        $this->assertFalse(TransportPreset::first()->auto_double);
    }

    public function test_登録数の上限(): void
    {
        $u = $this->actor();
        for ($i = 0; $i < TransportPreset::MAX_PER_USER; $i++) {
            TransportPreset::create(['user_id' => $u->id, 'destination' => "行先{$i}", 'purpose' => 'round_trip', 'station_from' => '', 'station_to' => '', 'fare_type' => 'ic', 'base_amount' => 1]);
        }
        $this->actingAs($u)->post(route('user.billing.transport.presets.store'), $this->payload())->assertSessionHasErrors('preset');
        $this->assertSame(TransportPreset::MAX_PER_USER, TransportPreset::count());
    }

    public function test_修正と削除は本人だけ(): void
    {
        $owner = $this->actor();
        $other = $this->actor();
        $this->actingAs($owner)->post(route('user.billing.transport.presets.store'), $this->payload());
        $p = TransportPreset::first();

        $this->actingAs($other)->put(route('user.billing.transport.presets.update', ['preset' => $p->id]), $this->payload())->assertStatus(403);
        $this->actingAs($other)->delete(route('user.billing.transport.presets.destroy', ['preset' => $p->id]))->assertStatus(403);

        $this->actingAs($owner)->put(route('user.billing.transport.presets.update', ['preset' => $p->id]), $this->payload(['base_amount' => 400, 'auto_double' => false]))->assertSessionHasNoErrors();
        $this->assertSame(400, $p->fresh()->base_amount);
        $this->actingAs($owner)->delete(route('user.billing.transport.presets.destroy', ['preset' => $p->id]));
        $this->assertSame(0, TransportPreset::count());
    }

    public function test_候補は登録済みが先で_過去の履歴も入り_本人の分だけ(): void
    {
        $u = $this->actor();
        $other = $this->actor();

        // 過去の入力（履歴）: 小台工場を 2 回、本社を 1 回
        $this->actingAs($u)->post(route('user.billing.transport.store'), [
            'billing_month' => '2026-08', 'charge_department_id' => null,
            'items' => [
                $this->item(['occurrence_date' => '2026-08-03']),
                $this->item(['occurrence_date' => '2026-08-04']),
                $this->item(['occurrence_date' => '2026-08-05', 'destination' => '本社', 'station_to' => '東京', 'amount' => 300, 'base_amount' => 150]),
            ],
        ])->assertSessionHasNoErrors();
        // 他人の履歴・登録は候補に出ない
        $this->actingAs($other)->post(route('user.billing.transport.presets.store'), $this->payload(['destination' => '他人の行先']));

        // 登録: 履歴に無い「国際交流基金」と、履歴にもある「本社」
        $this->actingAs($u)->post(route('user.billing.transport.presets.store'), $this->payload());
        $this->actingAs($u)->post(route('user.billing.transport.presets.store'), $this->payload(['destination' => '本社', 'station_to' => '東京', 'base_amount' => 150, 'auto_double' => true]));

        $this->actingAs($u)->get(route('user.billing.transport.index', ['month' => '2026-09']))
            ->assertInertia(fn ($p) => $p
                ->has('suggestions', 3)
                ->where('suggestions.0.registered', true)->where('suggestions.0.destination', '本社')->where('suggestions.0.count', 1)   // 登録済みで履歴 1 回
                ->where('suggestions.1.registered', true)->where('suggestions.1.destination', '国際交流基金')->where('suggestions.1.count', 0)
                ->where('suggestions.2.registered', false)->where('suggestions.2.destination', '小台工場')->where('suggestions.2.count', 2)
                ->where('suggestions.2.amount', 420));
    }
}
