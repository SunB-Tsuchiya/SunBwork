<?php

namespace Tests\Feature\Billing;

use App\Models\Company;
use App\Models\TransportExpense;
use App\Models\TransportFare;
use App\Models\User;
use App\Services\Billing\Transport\TransportCompanionMatcher;
use App\Services\Billing\Transport\TransportFareChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 交通費: 同行者の突き合わせと、経由地つきの運賃の確認 */
class TransportCompanionTest extends TestCase
{
    use RefreshDatabase;

    private Company $sb;
    private Company $sanei;

    protected function setUp(): void
    {
        parent::setUp();
        config(['transport.enforce_deadline' => false, 'applications.enforce_access' => false]);
        $this->sb = Company::create(['name' => '株式会社サン・ブレーン', 'code' => 'SB' . random_int(100, 999), 'company_type' => 'sunbrain', 'active' => true]);
        $this->sanei = Company::create(['name' => '株式会社サンエー印刷', 'code' => 'SA' . random_int(100, 999), 'company_type' => 'general', 'active' => true]);
    }

    private function user(string $role = 'user', ?Company $c = null, string $name = ''): User
    {
        return User::factory()->create(array_filter(['user_role' => $role, 'company_id' => ($c ?? $this->sb)->id, 'name' => $name ?: null]));
    }

    private function fare(string $a, string $b, ?int $ic, string $status = 'verified'): TransportFare
    {
        return TransportFare::create([
            'company_id' => $this->sb->id, 'route_key' => TransportFare::routeKey($a, $b), 'station_a' => $a, 'station_b' => $b,
            'fare_ic' => $ic, 'status' => $status, 'checked_at' => $status === 'verified' ? now() : null,
        ]);
    }

    private function row(array $over = []): array
    {
        return array_merge([
            'occurrence_date' => '2026-08-05', 'destination' => '国際交流基金', 'purpose' => 'outbound', 'purpose_text' => '',
            'station_from' => '田端', 'station_to' => '市ヶ谷', 'fare_type' => 'ic', 'amount' => 200, 'base_amount' => 200, 'auto_double' => false,
        ], $over);
    }

    private function save(User $u, array $items, string $month = '2026-08')
    {
        return $this->actingAs($u)->post(route('user.billing.transport.store'), ['billing_month' => $month, 'charge_department_id' => null, 'items' => $items]);
    }

    private function submit(User $u, array $items): TransportExpense
    {
        $this->save($u, $items)->assertSessionHasNoErrors();
        $e = TransportExpense::where('user_id', $u->id)->firstOrFail();
        $this->actingAs($u)->post(route('user.billing.transport.submit', ['expense' => $e->id]))->assertSessionHasNoErrors();
        return $e->fresh();
    }

    /** @return array{companions: array, named_by: array} 最初の明細の突き合わせ */
    private function review(TransportExpense $e): array
    {
        $r = app(TransportCompanionMatcher::class)->forExpense($e->fresh());
        return $r[$e->items()->orderBy('sort_order')->first()->id];
    }

    // ── 保存・復元・検証 ───────────────────────────

    public function test_同行者と経由地を保存でき_開き直しても同じ状態で返る(): void
    {
        $a = $this->user('user', null, '私'); $b = $this->user('user', null, '山田');
        $this->save($a, [$this->row(['via_stations' => ['池袋', '新宿'], 'companion_ids' => [$b->id]])])->assertSessionHasNoErrors();

        $item = TransportExpense::first()->items->first();
        $this->assertSame(['池袋', '新宿'], $item->via_stations);
        $this->assertSame([$b->id], $item->companions->pluck('user_id')->all());

        $this->actingAs($a)->get(route('user.billing.transport.index', ['month' => '2026-08']))
            ->assertInertia(fn ($p) => $p->where('expense.items.0.via_stations', ['池袋', '新宿'])
                ->where('expense.items.0.companions.0.id', $b->id)->where('expense.items.0.companions.0.name', '山田')
                ->has('colleagues', 1)->where('colleagues.0.id', $b->id), false);
    }

    public function test_経由地と同行者は入れなくてもよく_再保存で入れ替わる(): void
    {
        $a = $this->user(); $b = $this->user();
        $this->save($a, [$this->row()])->assertSessionHasNoErrors();
        $this->assertNull(TransportExpense::first()->items->first()->via_stations);
        $this->save($a, [$this->row(['via_stations' => ['池袋'], 'companion_ids' => [$b->id]])])->assertSessionHasNoErrors();
        $this->save($a, [$this->row(['via_stations' => [], 'companion_ids' => []])])->assertSessionHasNoErrors();
        $item = TransportExpense::first()->items->first();
        $this->assertNull($item->via_stations);
        $this->assertCount(0, $item->companions);
    }

    public function test_同行者に選べるのは同じ会社の社員だけ_本人と他社は選べず_上限もある(): void
    {
        $a = $this->user(); $other = $this->user('user', $this->sanei);
        $this->save($a, [$this->row(['companion_ids' => [$other->id]])])->assertSessionHasErrors(['items.0.companion_ids']);
        $this->save($a, [$this->row(['companion_ids' => [$a->id]])])->assertSessionHasErrors(['items.0.companion_ids']);
        $many = collect(range(1, 6))->map(fn () => $this->user()->id)->all();
        $this->save($a, [$this->row(['companion_ids' => $many])])->assertSessionHasErrors(['items.0.companion_ids']);
        $this->save($a, [$this->row(['via_stations' => ['A', 'B', 'C', 'D', 'E', 'F']])])->assertSessionHasErrors(['items.0.via_stations']);
        $this->assertSame(0, TransportExpense::count());
    }

    public function test_経由地は全角にそろい_空は除かれる(): void
    {
        $a = $this->user();
        $this->save($a, [$this->row(['via_stations' => ['ikebukuro', '', '  ', 'ｼﾝｼﾞｭｸ']])])->assertSessionHasNoErrors();
        $this->assertSame(['ｉｋｅｂｕｋｕｒｏ', 'シンジュク'], TransportExpense::first()->items->first()->via_stations);
    }

    // ── 突き合わせ ─────────────────────────────

    public function test_同じ日付と行先で区間も金額も同じなら_一致(): void
    {
        $a = $this->user('user', null, '私'); $b = $this->user('user', null, '山田');
        $ea = $this->submit($a, [$this->row(['companion_ids' => [$b->id]])]);
        $this->submit($b, [$this->row(['destination' => '国際交流基金　'])]);   // 表記のゆれ（空白）があっても同じ行先
        $c = $this->review($ea)['companions'][0];
        $this->assertSame(['match', '山田'], [$c['verdict'], $c['name']]);
        $this->assertSame(200, $c['rows'][0]['amount']);
    }

    public function test_区間が同じで金額が違えば_金額の違い_区間が違えば_区間の違い(): void
    {
        $a = $this->user(); $b = $this->user();
        $ea = $this->submit($a, [$this->row(['companion_ids' => [$b->id]])]);
        $eb = $this->submit($b, [$this->row(['amount' => 260, 'base_amount' => 260])]);
        $this->assertSame('amount_diff', $this->review($ea)['companions'][0]['verdict']);

        $this->actingAs($this->user('clerk'))->post(route('clerk.billing.transport.return', ['expense' => $eb->id]), ['comment' => '修正'])->assertSessionHasNoErrors();
        $this->save($b, [$this->row(['station_from' => '巣鴨', 'amount' => 200])])->assertSessionHasNoErrors();
        $this->actingAs($b)->post(route('user.billing.transport.submit', ['expense' => $eb->id]))->assertSessionHasNoErrors();
        $this->assertSame('route_diff', $this->review($ea)['companions'][0]['verdict']);
    }

    public function test_日付だけ同じなら候補_同じ日の行がなければ_なし_申請がなければ_申請なし(): void
    {
        $a = $this->user(); $b = $this->user(); $c = $this->user();
        $ea = $this->submit($a, [$this->row(['companion_ids' => [$b->id, $c->id]])]);
        $this->submit($b, [$this->row(['destination' => '全然ちがう行先'])]);
        $v = collect($this->review($ea)['companions'])->keyBy('user_id');
        $this->assertSame('candidate', $v[$b->id]['verdict']);
        $this->assertSame('全然ちがう行先', $v[$b->id]['rows'][0]['destination']);
        $this->assertSame('no_expense', $v[$c->id]['verdict']);

        $this->submit($c, [$this->row(['occurrence_date' => '2026-08-06'])]);
        $this->assertSame('none', collect($this->review($ea)['companions'])->keyBy('user_id')[$c->id]['verdict']);
    }

    public function test_同行者の申請が下書きのあいだは_中身を見せない(): void
    {
        $a = $this->user(); $b = $this->user();
        $ea = $this->submit($a, [$this->row(['companion_ids' => [$b->id]])]);
        $this->save($b, [$this->row()])->assertSessionHasNoErrors();   // 下書きのまま
        $c = $this->review($ea)['companions'][0];
        $this->assertSame(['draft', []], [$c['verdict'], $c['rows']]);
        $this->assertNull($c['expense_id']);
    }

    public function test_相手を選んでいない山田さんの画面にも_選ばれている情報が出る(): void
    {
        $a = $this->user('user', null, '私'); $b = $this->user('user', null, '山田');
        $this->submit($a, [$this->row(['companion_ids' => [$b->id]])]);
        $eb = $this->submit($b, [$this->row()]);   // 山田さんは、私を選んでいない
        $named = $this->review($eb)['named_by'];
        $this->assertCount(1, $named);
        $this->assertSame(['私', 'match'], [$named[0]['applicant_name'], $named[0]['verdict']]);

        // 総務・経理の画面にも渡る
        $this->actingAs($this->user('clerk'))->get(route('clerk.billing.transport.show', ['expense' => $eb->id]))->assertOk()
            ->assertInertia(fn ($p) => $p->where('expense.items.0.companion_review.named_by.0.applicant_name', '私'), false);
    }

    public function test_選ばれた側の情報は_他社と下書きには出ない(): void
    {
        $a = $this->user('user', $this->sb); $b = $this->user('user', $this->sb);
        $this->save($a, [$this->row(['companion_ids' => [$b->id]])])->assertSessionHasNoErrors();   // 私は下書き
        $eb = $this->submit($b, [$this->row()]);
        $this->assertCount(0, $this->review($eb)['named_by']);
    }

    // ── 経由地つきの運賃 ──────────────────────────

    private function checkFare(TransportExpense $e): array
    {
        return app(TransportFareChecker::class)->check($e->fresh(), false)['items'][0]['fare'];
    }

    public function test_経由地があれば_区間ごとの運賃を足して照合する(): void
    {
        $a = $this->user();
        $this->fare('田端', '池袋', 170);
        $this->fare('池袋', '新宿', 160);
        $e = $this->submit($a, [$this->row(['station_to' => '新宿', 'via_stations' => ['池袋'], 'amount' => 330, 'base_amount' => 330])]);
        $f = $this->checkFare($e);
        $this->assertSame(['match', 330, true], [$f['state'], $f['expected'], $f['via_used']]);
        $this->assertCount(2, $f['legs']);

        $e2 = TransportExpense::first();
        $e2->items()->update(['amount' => 300, 'base_amount' => 300]);
        $f = $this->checkFare($e2);
        $this->assertSame(['diff', 330, -30], [$f['state'], $f['expected'], $f['diff']]);
    }

    public function test_通しの区間が台帳にあれば_そちらを優先し_区間ごとの合計は使わない(): void
    {
        $a = $this->user();
        $this->fare('田端', '池袋', 170);
        $this->fare('池袋', '新宿', 160);
        $this->fare('田端', '新宿', 300);   // 通しは、足し算より安い
        $e = $this->submit($a, [$this->row(['station_to' => '新宿', 'via_stations' => ['池袋'], 'amount' => 300, 'base_amount' => 300])]);
        $f = $this->checkFare($e);
        $this->assertSame(['match', 300, false], [$f['state'], $f['expected'], $f['via_used']]);
    }

    public function test_区間の運賃が台帳に足りなければ_計算せず_申請すると区間の候補が台帳に溜まり_未検査になる(): void
    {
        $a = $this->user();
        $this->fare('田端', '池袋', 170);   // 池袋－新宿が無い
        $this->save($a, [$this->row(['station_to' => '新宿', 'via_stations' => ['池袋'], 'amount' => 330, 'base_amount' => 330])])->assertSessionHasNoErrors();
        $e = TransportExpense::first();

        // 申請前（下書き）: 台帳に無いので、計算しない
        $f = $this->checkFare($e);
        $this->assertSame(['none', null], [$f['state'], $f['expected']]);
        $this->assertFalse(TransportFare::where('route_key', TransportFare::routeKey('池袋', '新宿'))->exists());

        // 申請すると、区間ごとの候補が、台帳に未検査で溜まる。そのあとの確認は「未検査」
        $this->actingAs($a)->post(route('user.billing.transport.submit', ['expense' => $e->id]))->assertSessionHasNoErrors();
        $this->assertTrue(TransportFare::where('route_key', TransportFare::routeKey('池袋', '新宿'))->where('status', 'unverified')->exists());
        $f = $this->checkFare($e);
        $this->assertSame(['unverified', null], [$f['state'], $f['expected']]);
    }
}
