<?php

namespace Tests\Feature\Billing;

use App\Models\Company;
use App\Models\TransportExpense;
use App\Models\TransportFare;
use App\Models\User;
use App\Services\Billing\Transport\TransportFareChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 交通費: 運賃マスタ（台帳）・検算（照合と各種チェック）・行ごとの確認 */
class TransportFareTest extends TestCase
{
    use RefreshDatabase;

    private Company $sb;
    private Company $sanei;

    protected function setUp(): void
    {
        parent::setUp();
        config(['transport.enforce_deadline' => false]);   // 既存のテストは、過去の月で入力するため、期限の強制を切る（期限の検証は TransportDeadlineTest）
        config(['applications.enforce_access' => false]);   // 申請者管理の制限は、TransportApplicantAccessTest で検証
        $this->sb = Company::create(['name' => '株式会社サン・ブレーン', 'code' => 'SB' . random_int(100, 999), 'company_type' => 'sunbrain', 'active' => true]);
        $this->sanei = Company::create(['name' => '株式会社サンエー印刷', 'code' => 'SA' . random_int(100, 999), 'company_type' => 'general', 'active' => true]);
    }

    private function user(string $role, Company $c, string $name = ''): User
    {
        return User::factory()->create(array_filter(['user_role' => $role, 'company_id' => $c->id, 'name' => $name ?: null]));
    }

    private function fare(Company $c, string $a, string $b, ?int $ic, ?int $ticket = null, string $status = 'verified', array $over = []): TransportFare
    {
        return TransportFare::create(array_merge([
            'company_id' => $c->id, 'route_key' => TransportFare::routeKey($a, $b), 'station_a' => $a, 'station_b' => $b,
            'fare_ic' => $ic, 'fare_ticket' => $ticket, 'status' => $status,
            'checked_at' => $status === 'verified' ? now() : null,
        ], $over));
    }

    private function row(array $over = []): array
    {
        return array_merge([
            'occurrence_date' => '2026-08-05', 'destination' => '国際交流基金', 'purpose' => 'round_trip', 'purpose_text' => '',
            'station_from' => '田端', 'station_to' => '市ヶ谷', 'fare_type' => 'ic',
            'amount' => 728, 'base_amount' => 364, 'auto_double' => true,
        ], $over);
    }

    /** 申請者（現状の申請画面は SuperAdmin のルートなので SuperAdmin）が、指定の会社で、明細つきの下書きを作る */
    private function expense(Company $c, array $items, string $month = '2026-08', ?User $u = null): TransportExpense
    {
        $u ??= $this->user('superadmin', $c);
        $this->actingAs($u)->post(route('user.billing.transport.store'), ['billing_month' => $month, 'charge_department_id' => null, 'items' => $items])
            ->assertSessionHasNoErrors();
        return TransportExpense::where('user_id', $u->id)->where('billing_month', $month)->firstOrFail();
    }

    private function check(TransportExpense $e, bool $full = true): array
    {
        return app(TransportFareChecker::class)->check($e->fresh(), $full);
    }

    // ── 駅名の正規化 ───────────────────────────────

    public function test_経路のキーは_方向_駅の有無_ヶとケ_全角半角のゆれを問わない(): void
    {
        $k = TransportFare::routeKey('田端', '恵比寿');
        $this->assertSame($k, TransportFare::routeKey('恵比寿', '田端'));
        $this->assertSame($k, TransportFare::routeKey('田端駅', '恵比寿駅'));
        $this->assertSame(TransportFare::routeKey('市ヶ谷', '田端'), TransportFare::routeKey('田端', '市ケ谷'));
        $this->assertSame(TransportFare::routeKey('ＪＲ品川', '田端'), TransportFare::routeKey('JR品川', '田端'));
        $this->assertSame(TransportFare::routeKey('ﾀﾊﾞﾀ', '新宿'), TransportFare::routeKey('タバタ', '新宿'));
        $this->assertNotSame($k, TransportFare::routeKey('田端', '新宿'));
    }

    // ── 台帳の登録・修正・削除と、会社の分離 ───────────────

    public function test_台帳の登録_検査済みには運賃が要り_検査した人と日付が残る(): void
    {
        $clerk = $this->user('clerk', $this->sb);
        $store = fn (array $d) => $this->actingAs($clerk)->post(route('clerk.billing.transport.fares.store'), $d);

        $store(['station_a' => '田端', 'station_b' => '恵比寿'])->assertSessionHasErrors('fare_ic');   // 運賃がなければ登録できない（登録の画面では運賃が必要）
        $store(['station_a' => '田端', 'station_b' => '田端', 'fare_ic' => 100])->assertSessionHasErrors('station_b');      // 同じ駅
        $this->assertSame(0, TransportFare::count());

        $store(['station_a' => 'JR 田端', 'station_b' => '恵比寿', 'fare_ic' => '１９４円', 'fare_ticket' => 200])->assertSessionHasNoErrors();   // 状態は送らない: 運賃があれば登録済み（検査済み）
        $f = TransportFare::first();
        $this->assertSame(['ＪＲ　田端', 194, 200, 'verified', $clerk->id, null], [$f->station_a, $f->fare_ic, $f->fare_ticket, $f->status, $f->checked_by, $f->note]);
        $this->assertNotNull($f->checked_at);

        // 同じ経路（方向を入れ替え）で登録すると、1 件のまま更新される
        $store(['station_a' => '恵比寿', 'station_b' => 'JR 田端', 'fare_ic' => 198])->assertSessionHasNoErrors();
        $this->assertSame(1, TransportFare::count());
        $this->assertSame(198, TransportFare::first()->fare_ic);
    }

    public function test_台帳は会社ごとに分かれ_他社の総務経理は触れない(): void
    {
        $clerkSb = $this->user('clerk', $this->sb);
        $clerkSanei = $this->user('clerk', $this->sanei);
        $f = $this->fare($this->sb, '田端', '新宿', 200);

        $this->actingAs($clerkSanei)->put(route('clerk.billing.transport.fares.update', ['fare' => $f->id]), ['station_a' => '田端', 'station_b' => '新宿', 'fare_ic' => 1, 'status' => 'verified'])->assertStatus(403);
        $this->actingAs($clerkSanei)->delete(route('clerk.billing.transport.fares.destroy', ['fare' => $f->id]))->assertStatus(403);
        $this->actingAs($clerkSanei)->get(route('clerk.billing.transport.fares.index'))->assertInertia(fn ($p) => $p->where('counts.all', 0)->has('fares', 0));
        $this->actingAs($clerkSb)->get(route('clerk.billing.transport.fares.index'))->assertInertia(fn ($p) => $p->where('counts.all', 1)->where('counts.verified', 1)->has('fares', 1));

        $this->actingAs($this->user('user', $this->sb))->get(route('clerk.billing.transport.fares.index'))->assertStatus(403);   // 権限のない人

        $this->actingAs($clerkSb)->put(route('clerk.billing.transport.fares.update', ['fare' => $f->id]), ['station_a' => '田端', 'station_b' => '新宿', 'fare_ic' => 210])->assertSessionHasNoErrors();
        $this->assertSame(210, $f->fresh()->fare_ic);
        $this->actingAs($clerkSb)->delete(route('clerk.billing.transport.fares.destroy', ['fare' => $f->id]));
        $this->assertSame(0, TransportFare::count());
    }

    // ── 申請時の自動ストック ───────────────────────

    public function test_申請すると_台帳に無い経路が未検査で溜まり_回数と参考の金額が残る(): void
    {
        $this->fare($this->sb, '田端', '新宿', 200);   // 検査済みは、参考値で上書きしない
        $e = $this->expense($this->sb, [
            $this->row(),
            $this->row(['occurrence_date' => '2026-08-06']),
            $this->row(['occurrence_date' => '2026-08-07', 'station_from' => '田端', 'station_to' => '新宿', 'amount' => 999, 'base_amount' => 999, 'auto_double' => false, 'purpose' => 'outbound']),
        ]);
        $this->actingAs(User::find($e->user_id))->post(route('user.billing.transport.submit', ['expense' => $e->id]))->assertSessionHasNoErrors();

        $f = TransportFare::where('route_key', TransportFare::routeKey('田端', '市ヶ谷'))->first();
        $this->assertSame(['unverified', 2, 364, 'ic', 'stock'], [$f->status, $f->use_count, $f->reported_fare, $f->reported_fare_type, $f->source]);
        $shinjuku = TransportFare::where('route_key', TransportFare::routeKey('田端', '新宿'))->first();
        $this->assertSame([200, 'verified', 1, null], [$shinjuku->fare_ic, $shinjuku->status, $shinjuku->use_count, $shinjuku->reported_fare]);
    }

    // ── 照合 ────────────────────────────────

    public function test_照合_一致_相違_未検査_登録なし_往復と片道(): void
    {
        $this->fare($this->sb, '田端', '市ヶ谷', 364, 380);                 // 検査済み
        $this->fare($this->sb, '田端', '渋谷', 200, null, 'unverified');   // 未検査
        $this->fare($this->sb, '田端', '品川', 300, null);                 // 検査済みだが IC のみ
        $e = $this->expense($this->sb, [
            $this->row(['occurrence_date' => '2026-08-03', 'amount' => 728, 'base_amount' => 364]),                                                                  // 往復 364×2 = 728 → 一致
            $this->row(['occurrence_date' => '2026-08-04', 'amount' => 700, 'base_amount' => 350]),                                                                  // 700 ≠ 728 → 相違 -28
            $this->row(['occurrence_date' => '2026-08-05', 'purpose' => 'outbound', 'amount' => 364, 'base_amount' => 364, 'auto_double' => false]),                   // 往路は片道 → 一致
            $this->row(['occurrence_date' => '2026-08-06', 'amount' => 760, 'base_amount' => 380, 'fare_type' => 'ticket']),                                          // 切符 380×2
            $this->row(['occurrence_date' => '2026-08-07', 'station_to' => '渋谷', 'amount' => 400, 'base_amount' => 200]),                                           // 未検査
            $this->row(['occurrence_date' => '2026-08-10', 'station_to' => '横浜', 'amount' => 800, 'base_amount' => 400]),                                           // 登録なし
            $this->row(['occurrence_date' => '2026-08-11', 'station_to' => '品川', 'amount' => 600, 'base_amount' => 300, 'fare_type' => 'ticket']),                  // 切符の運賃が無い → 登録なし扱い
        ]);
        $r = $this->check($e);
        $states = array_map(fn ($i) => $i['fare']['state'], $r['items']);
        $this->assertSame(['match', 'diff', 'match', 'match', 'unverified', 'none', 'none'], $states);
        $this->assertSame(-28, $r['items'][1]['fare']['diff']);
        $this->assertSame(728, $r['items'][1]['fare']['expected']);
        $this->assertSame(['total' => 7, 'diff' => 1, 'none' => 2, 'unverified' => 1, 'match' => 3], array_intersect_key($r['summary'], array_flip(['total', 'diff', 'none', 'unverified', 'match'])));
    }

    public function test_照合は_申請者の金額が往復で奇数など_片道が決められなくても動く(): void
    {
        $this->fare($this->sb, '田端', '市ヶ谷', 364);
        // ×2 を使わず、合計だけ 727（奇数）で入力された往復: 片道は決められないが、期待の合計 728 とは比べられる
        $e = $this->expense($this->sb, [$this->row(['amount' => 727, 'base_amount' => 727, 'auto_double' => false])]);
        $r = $this->check($e);
        $this->assertSame('diff', $r['items'][0]['fare']['state']);
        $this->assertSame(-1, $r['items'][0]['fare']['diff']);
    }

    // ── 重複・休日・金額・区間・前回 ──────────────────

    public function test_重複_休日_金額の異常_区間の誤り_前回との違いに注意が付く(): void
    {
        $u = $this->user('superadmin', $this->sb);
        // 前月（7月）の同じ経路: 片道 360
        $this->expense($this->sb, [$this->row(['occurrence_date' => '2026-07-10', 'amount' => 720, 'base_amount' => 360])], '2026-07', $u);

        $e = $this->expense($this->sb, [
            $this->row(['occurrence_date' => '2026-08-03']),                                                                                 // 0: 前回(360)と違う・重複(次の行と)
            $this->row(['occurrence_date' => '2026-08-03']),                                                                                 // 1: 重複
            $this->row(['occurrence_date' => '2026-08-04', 'station_to' => '新宿', 'purpose' => 'round_trip']),                               // 2: 往復
            $this->row(['occurrence_date' => '2026-08-04', 'station_to' => '新宿', 'purpose' => 'outbound', 'amount' => 194, 'base_amount' => 194, 'auto_double' => false]), // 3: 往復と往路が重複
            $this->row(['occurrence_date' => '2026-08-08', 'station_to' => '品川', 'amount' => 300, 'base_amount' => 300, 'auto_double' => false]),                          // 4: 土曜
            $this->row(['occurrence_date' => '2026-08-12', 'station_to' => '遠方', 'amount' => 12000, 'base_amount' => 6000]),                                                // 5: 金額が大きい
            $this->row(['occurrence_date' => '2026-08-13', 'station_to' => '田端', 'amount' => 100, 'base_amount' => 100, 'auto_double' => false]),                           // 6: 同じ駅
            $this->row(['occurrence_date' => '2026-08-14', 'station_from' => '', 'amount' => 0, 'base_amount' => 0, 'auto_double' => false]),                                  // 7: 区間なし・0 円
        ], '2026-08', $u);

        $types = fn ($r, $i) => array_column($r['items'][$i]['flags'], 'type');
        $r = $this->check($e);

        $this->assertContains('duplicate', $types($r, 0));
        $this->assertContains('duplicate', $types($r, 1));
        $this->assertContains('previous', $types($r, 0));            // 前回 片道 360 → 今回 364
        $this->assertContains('duplicate', $types($r, 2));
        $this->assertContains('duplicate', $types($r, 3));           // 往復と往路が同じ日
        $this->assertContains('holiday', $types($r, 4));
        $this->assertStringContainsString('土曜', $r['items'][4]['flags'][array_search('holiday', $types($r, 4))]['detail']);
        $this->assertContains('amount', $types($r, 5));
        $this->assertContains('route', $types($r, 6));
        $this->assertContains('route', $types($r, 7));
        $this->assertContains('amount', $types($r, 7));
        $this->assertNotContains('previous', $types($r, 6));

        // 一覧用（前回との比較は省く）でも、重複などは数える
        $light = $this->check($e, false);
        $this->assertNotContains('previous', $types($light, 0));
        $this->assertGreaterThanOrEqual(6, $light['summary']['warn_rows']);
    }

    public function test_何も問題のない行には注意が付かない(): void
    {
        $this->fare($this->sb, '田端', '市ヶ谷', 364);
        $e = $this->expense($this->sb, [$this->row(['occurrence_date' => '2026-08-05'])]);   // 水曜日
        $r = $this->check($e);
        $this->assertSame([], $r['items'][0]['flags']);
        $this->assertSame('match', $r['items'][0]['fare']['state']);
        $this->assertSame(0, $r['summary']['warn_rows']);
    }

    // ── 行ごとの確認・登録（詳細画面） ───────────────────

    private function submitted(Company $c, array $items): TransportExpense
    {
        $e = $this->expense($c, $items);
        $this->actingAs(User::find($e->user_id))->post(route('user.billing.transport.submit', ['expense' => $e->id]))->assertSessionHasNoErrors();
        return $e->fresh();
    }

    public function test_行ごとの確認チェックと_すべて確認(): void
    {
        $clerk = $this->user('clerk', $this->sb);
        $other = $this->user('clerk', $this->sanei);
        $e = $this->submitted($this->sb, [$this->row(), $this->row(['occurrence_date' => '2026-08-06'])]);
        [$i1, $i2] = $e->items->all();

        $this->actingAs($clerk)->post(route('clerk.billing.transport.items.check', ['expense' => $e->id, 'item' => $i1->id]), ['checked' => true])->assertSessionHasNoErrors();
        $this->assertNotNull($i1->fresh()->checked_at);
        $this->assertSame($clerk->id, $i1->fresh()->checked_by);
        $this->assertNull($i2->fresh()->checked_at);

        $this->actingAs($clerk)->get(route('clerk.billing.transport.show', ['expense' => $e->id]))
            ->assertInertia(fn ($p) => $p->where('expense.check_summary.unchecked', 1)->where('expense.items.0.check.checked', true)->where('expense.items.1.check.checked', false));

        $this->actingAs($clerk)->post(route('clerk.billing.transport.items.check', ['expense' => $e->id, 'item' => $i1->id]), ['checked' => false]);
        $this->assertNull($i1->fresh()->checked_at);

        $this->actingAs($clerk)->post(route('clerk.billing.transport.items.check_all', ['expense' => $e->id]))->assertSessionHasNoErrors();
        $this->assertSame(0, $e->items()->whereNull('checked_at')->count());

        // 他社は不可。差戻し後など、確認できない状態でも不可
        $this->actingAs($other)->post(route('clerk.billing.transport.items.check', ['expense' => $e->id, 'item' => $i1->id]), ['checked' => true])->assertStatus(403);
        $e->update(['status' => 'returned']);
        $this->actingAs($clerk)->post(route('clerk.billing.transport.items.check_all', ['expense' => $e->id]))->assertStatus(422);
    }

    public function test_詳細と一覧に_照合の結果と要確認の件数が出る(): void
    {
        $clerk = $this->user('clerk', $this->sb);
        $this->fare($this->sb, '田端', '市ヶ谷', 364);
        $e = $this->submitted($this->sb, [$this->row(), $this->row(['occurrence_date' => '2026-08-06', 'amount' => 700, 'base_amount' => 350])]);

        $this->actingAs($clerk)->get(route('clerk.billing.transport.show', ['expense' => $e->id]))
            ->assertInertia(fn ($p) => $p
                ->where('expense.check_summary.diff', 1)->where('expense.check_summary.match', 1)
                ->where('expense.items.0.check.fare.state', 'match')
                ->where('expense.items.1.check.fare.state', 'diff')->where('expense.items.1.check.fare.diff', -28));

        $this->actingAs($clerk)->get(route('clerk.billing.transport.index', ['month' => '2026-08']))
            ->assertInertia(fn ($p) => $p->where('groups.0.rows.0.issues.diff', 1)->where('groups.0.rows.0.issues.unchecked', 2));
    }

    public function test_行から台帳へ_未検査で登録し_申請の金額で検査済みにできる(): void
    {
        $clerk = $this->user('clerk', $this->sb);
        $e = $this->submitted($this->sb, [
            $this->row(),                                                                                                                  // 往復 364 ×2 (IC)
            $this->row(['occurrence_date' => '2026-08-06', 'station_to' => '新宿', 'amount' => 727, 'base_amount' => 727, 'auto_double' => false]),   // 往復で奇数: 片道が決められない
        ]);
        [$i1, $i2] = $e->items->all();
        $url = fn ($it) => route('clerk.billing.transport.items.fare', ['expense' => $e->id, 'item' => $it->id]);

        $this->actingAs($clerk)->post($url($i1), ['action' => 'verify'])->assertSessionHasNoErrors();
        $f = TransportFare::where('route_key', TransportFare::routeKey('田端', '市ヶ谷'))->first();
        $this->assertSame(['verified', 364, null, $clerk->id], [$f->status, $f->fare_ic, $f->fare_ticket, $f->checked_by]);

        // 片道が決められない行は、この金額では検査済みにできない（未検査での登録はできる）
        $this->actingAs($clerk)->post($url($i2), ['action' => 'verify'])->assertSessionHasErrors('fare');
        $this->actingAs($clerk)->post($url($i2), ['action' => 'register'])->assertSessionHasNoErrors();
        $this->assertSame('unverified', TransportFare::where('route_key', TransportFare::routeKey('田端', '新宿'))->value('status'));

        $this->actingAs($this->user('clerk', $this->sanei))->post($url($i1), ['action' => 'verify'])->assertStatus(403);
    }

    public function test_未登録の候補は_申請の金額のまま_1回の登録で登録済みになる(): void
    {
        $clerk = $this->user('clerk', $this->sb);
        $e = $this->submitted($this->sb, [$this->row()]);
        $cand = TransportFare::where('route_key', TransportFare::routeKey('田端', '市ヶ谷'))->first();
        $this->assertSame('unverified', $cand->status);

        // 一覧の既定は「登録済み」。候補は status=unverified で見る
        $this->actingAs($clerk)->get(route('clerk.billing.transport.fares.index'))->assertInertia(fn ($p) => $p->where('status', 'verified')->has('fares', 0)->where('counts.unverified', 1));
        $this->actingAs($clerk)->get(route('clerk.billing.transport.fares.index', ['status' => 'unverified']))
            ->assertInertia(fn ($p) => $p->has('fares', 1)->where('fares.0.reported_fare', 364)->where('fares.0.reported_fare_type', 'ic'));

        // 候補の「登録」: 状態は送らず、運賃だけ送る → 登録済み（検査済み）
        $this->actingAs($clerk)->put(route('clerk.billing.transport.fares.update', ['fare' => $cand->id]), ['station_a' => '田端', 'station_b' => '市ヶ谷', 'fare_ic' => 364, 'fare_ticket' => null])->assertSessionHasNoErrors();
        $this->assertSame(['verified', 364, $clerk->id], [$cand->fresh()->status, $cand->fresh()->fare_ic, $cand->fresh()->checked_by]);
    }

    public function test_検算の合計は台帳で計算できた行だけで出し_未検査の行は別に数える(): void
    {
        $this->fare($this->sb, '田端', '市ヶ谷', 364);
        $e = $this->expense($this->sb, [
            $this->row(['occurrence_date' => '2026-08-03']),                                                  // 一致 728
            $this->row(['occurrence_date' => '2026-08-04', 'amount' => 700, 'base_amount' => 350]),           // 相違 申請 700（台帳 728）
            $this->row(['occurrence_date' => '2026-08-05', 'station_to' => '錦糸町', 'station_from' => '秋葉原', 'amount' => 400, 'base_amount' => 200]),   // 未検査
        ]);
        $s = $this->check($e)['summary'];
        $this->assertSame([2, 1456, 1428, 1, 400], [$s['computed_rows'], $s['expected_total'], $s['claimed_total'], $s['uncomputed_rows'], $s['uncomputed_amount']]);
    }

    public function test_一致した行だけをまとめて確認済みにする_注意のある行は残す(): void
    {
        $clerk = $this->user('clerk', $this->sb);
        $this->fare($this->sb, '田端', '市ヶ谷', 364);
        $e = $this->submitted($this->sb, [
            $this->row(['occurrence_date' => '2026-08-03']),                                                  // 一致 → 確認済みになる
            $this->row(['occurrence_date' => '2026-08-04', 'amount' => 700, 'base_amount' => 350]),           // 相違 → 残る
            $this->row(['occurrence_date' => '2026-08-05', 'station_to' => '錦糸町', 'station_from' => '秋葉原', 'amount' => 400, 'base_amount' => 200]), // 未検査 → 残る
            $this->row(['occurrence_date' => '2026-08-08']),                                                  // 土曜日で一致 → 注意があるので残る
        ]);

        $this->actingAs($clerk)->post(route('clerk.billing.transport.items.check_matched', ['expense' => $e->id]))->assertSessionHasNoErrors();
        $checked = $e->items()->orderBy('occurrence_date')->get()->map(fn ($i) => $i->checked_at !== null)->all();
        $this->assertSame([true, false, false, false], $checked);
        $this->assertSame($clerk->id, $e->items()->orderBy('occurrence_date')->first()->checked_by);

        // 他社は不可
        $this->actingAs($this->user('clerk', $this->sanei))->post(route('clerk.billing.transport.items.check_matched', ['expense' => $e->id]))->assertStatus(403);
    }
}
