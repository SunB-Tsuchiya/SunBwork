<?php

namespace Tests\Feature\Billing;

use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Models\Company;
use App\Models\TransportDeadlineExtension;
use App\Models\TransportExpense;
use App\Models\User;
use App\Services\Billing\Transport\TransportDeadline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** 交通費: 当月末日を過ぎたら申請不可（差戻しは可・総務経理による期限の解除・過去の月は閲覧のみ） */
class TransportDeadlineTest extends TestCase
{
    use RefreshDatabase;

    private Company $sb;
    private Company $sanei;

    protected function setUp(): void
    {
        parent::setUp();
        config(['transport.enforce_deadline' => true]);
        config(['applications.enforce_access' => false]);   // 申請者管理の制限は、TransportApplicantAccessTest で検証
        Carbon::setTestNow('2026-09-15 10:00:00');   // 9 月中旬: 8 月分は期限後、9 月分は期限内
        $this->sb = Company::create(['name' => '株式会社サン・ブレーン', 'code' => 'SB' . random_int(100, 999), 'company_type' => 'sunbrain', 'active' => true]);
        $this->sanei = Company::create(['name' => '株式会社サンエー印刷', 'code' => 'SA' . random_int(100, 999), 'company_type' => 'general', 'active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role = 'user', ?Company $c = null): User
    {
        return User::factory()->create(['user_role' => $role, 'company_id' => ($c ?? $this->sb)->id]);
    }

    private function body(string $month, string $date): array
    {
        return ['billing_month' => $month, 'charge_department_id' => null, 'items' => [[
            'occurrence_date' => $date, 'destination' => '小台工場', 'purpose' => 'round_trip', 'purpose_text' => '',
            'station_from' => '田端', 'station_to' => '宮城都営住宅前', 'fare_type' => 'ic', 'amount' => 840, 'base_amount' => 420, 'auto_double' => true,
        ]]];
    }

    // ── 期限の判定 ──────────────────────────────

    public function test_期限は月末の最後の瞬間まで_翌日の0時から不可(): void
    {
        $this->assertSame('2026-08-31 23:59:59', TransportDeadline::endOf('2026-08')->format('Y-m-d H:i:s'));
        $this->assertSame('2028-02-29 23:59:59', TransportDeadline::endOf('2028-02')->format('Y-m-d H:i:s'));

        Carbon::setTestNow('2026-08-31 23:59:59');
        $this->assertFalse(TransportDeadline::passed('2026-08'));
        Carbon::setTestNow('2026-09-01 00:00:00');
        $this->assertTrue(TransportDeadline::passed('2026-08'));
        $this->assertFalse(TransportDeadline::passed('2026-09'));
    }

    public function test_設定で強制を切ると_過去の月も使える(): void
    {
        config(['transport.enforce_deadline' => false]);
        $this->assertFalse(TransportDeadline::passed('2020-01'));
        $u = $this->user();
        $this->actingAs($u)->postJson(route('user.billing.transport.autosave'), $this->body('2026-01', '2026-01-05'))->assertOk();
    }

    // ── 申請者: 期限後は保存・申請できない ───────────────────

    public function test_期限内は保存と申請ができ_期限後の新規は保存も自動保存も申請もできない(): void
    {
        $u = $this->user();

        // 9 月分（期限内）
        $id = $this->actingAs($u)->postJson(route('user.billing.transport.autosave'), $this->body('2026-09', '2026-09-03'))->assertOk()->json('id');
        $this->actingAs($u)->post(route('user.billing.transport.submit', ['expense' => $id]))->assertSessionHasNoErrors();

        // 8 月分（期限後）: 新規は保存・自動保存できない
        $r = $this->actingAs($u)->postJson(route('user.billing.transport.autosave'), $this->body('2026-08', '2026-08-03'));
        $r->assertStatus(422);
        $this->assertStringContainsString('期限', $r->json('message'));
        $this->actingAs($u)->post(route('user.billing.transport.store'), $this->body('2026-08', '2026-08-03'))->assertStatus(422);
        $this->assertSame(0, TransportExpense::where('user_id', $u->id)->where('billing_month', '2026-08')->count());
    }

    public function test_期限前に作った下書きは_期限後には申請も更新もできないが_削除はできる(): void
    {
        $u = $this->user();
        Carbon::setTestNow('2026-08-20 10:00:00');
        $id = $this->actingAs($u)->postJson(route('user.billing.transport.autosave'), $this->body('2026-08', '2026-08-03'))->assertOk()->json('id');

        Carbon::setTestNow('2026-09-02 09:00:00');   // 期限後
        $this->actingAs($u)->post(route('user.billing.transport.submit', ['expense' => $id]))->assertStatus(422);
        $this->actingAs($u)->postJson(route('user.billing.transport.autosave'), $this->body('2026-08', '2026-08-04'))->assertStatus(422);
        $this->assertSame('draft', TransportExpense::find($id)->status);

        // 閲覧・出力は可（画面の props は、編集不可・削除可）
        $this->actingAs($u)->get(route('user.billing.transport.index', ['month' => '2026-08']))
            ->assertInertia(fn ($p) => $p->where('canEdit', false)->where('deadline.passed', true)->where('deadline.extended', false)
                ->where('expense.deletable', true)->where('expense.status', 'draft'));
        $this->actingAs($u)->get(route('user.billing.transport.pdf', ['expense' => $id]))->assertOk();
        $this->actingAs($u)->get(route('user.billing.transport.excel', ['expense' => $id]))->assertOk();

        // 残った下書きは、削除して片付けられる
        $this->actingAs($u)->delete(route('user.billing.transport.destroy', ['expense' => $id]));
        $this->assertNull(TransportExpense::find($id));
    }

    public function test_差戻しされたものは_期限後でも直して再申請できる(): void
    {
        $u = $this->user();
        Carbon::setTestNow('2026-08-20 10:00:00');
        $id = $this->actingAs($u)->postJson(route('user.billing.transport.autosave'), $this->body('2026-08', '2026-08-03'))->json('id');
        $this->actingAs($u)->post(route('user.billing.transport.submit', ['expense' => $id]))->assertSessionHasNoErrors();
        $clerk = $this->user('clerk');
        $this->actingAs($clerk)->post(route('clerk.billing.transport.return', ['expense' => $id]), ['comment' => '金額を確認してください'])->assertSessionHasNoErrors();

        Carbon::setTestNow('2026-09-20 10:00:00');   // 期限後
        $this->actingAs($u)->get(route('user.billing.transport.index', ['month' => '2026-08']))
            ->assertInertia(fn ($p) => $p->where('canEdit', true)->where('expense.status', 'returned'));
        $this->actingAs($u)->postJson(route('user.billing.transport.autosave'), $this->body('2026-08', '2026-08-04'))->assertOk();
        $this->actingAs($u)->post(route('user.billing.transport.submit', ['expense' => $id]))->assertSessionHasNoErrors();
        $this->assertSame('submitted', TransportExpense::find($id)->status);
    }

    public function test_期限後の取り下げはできない_取り下げると再申請できなくなるため(): void
    {
        $u = $this->user();
        Carbon::setTestNow('2026-08-20 10:00:00');
        $id = $this->actingAs($u)->postJson(route('user.billing.transport.autosave'), $this->body('2026-08', '2026-08-03'))->json('id');
        $this->actingAs($u)->post(route('user.billing.transport.submit', ['expense' => $id]));

        Carbon::setTestNow('2026-09-02 09:00:00');
        $this->actingAs($u)->post(route('user.billing.transport.withdraw', ['expense' => $id]))->assertStatus(422);
        $this->assertSame('submitted', TransportExpense::find($id)->status);
    }

    public function test_総務経理の操作は_期限の影響を受けない(): void
    {
        $u = $this->user();
        Carbon::setTestNow('2026-08-20 10:00:00');
        $id = $this->actingAs($u)->postJson(route('user.billing.transport.autosave'), $this->body('2026-08', '2026-08-03'))->json('id');
        $this->actingAs($u)->post(route('user.billing.transport.submit', ['expense' => $id]));

        Carbon::setTestNow('2026-10-05 10:00:00');   // 期限後
        $clerk = $this->user('clerk');
        $this->actingAs($clerk)->post(route('clerk.billing.transport.approve', ['expense' => $id]))->assertSessionHasNoErrors();
        $this->actingAs($clerk)->post(route('clerk.billing.transport.bill', ['expense' => $id]))->assertSessionHasNoErrors();
        $this->assertSame('billed', TransportExpense::find($id)->status);
    }

    // ── 期限の解除（総務・経理） ──────────────────────

    public function test_総務経理が期限を解除すると_その人のその月だけ申請できる_記録が残り_本人に通知される(): void
    {
        $u = $this->user();
        $other = $this->user();
        $clerk = $this->user('clerk');

        $this->actingAs($u)->postJson(route('user.billing.transport.autosave'), $this->body('2026-08', '2026-08-03'))->assertStatus(422);

        $this->actingAs($clerk)->post(route('clerk.billing.transport.deadlines.store'), ['user_id' => $u->id, 'billing_month' => '2026-08', 'reason' => '出張の精算の出し忘れ'])
            ->assertSessionHasNoErrors();
        $ext = TransportDeadlineExtension::first();
        $this->assertSame([$u->id, '2026-08', $clerk->id, '出張の精算の出し忘れ'], [$ext->user_id, $ext->billing_month, $ext->granted_by, $ext->reason]);

        // 本人にお知らせ
        $n = Announcement::where('title', 'like', '【交通費】%期限が解除されました')->first();
        $this->assertNotNull($n);
        $this->assertTrue(AnnouncementRecipient::where('announcement_id', $n->id)->where('user_id', $u->id)->exists());
        $this->assertStringContainsString('出張の精算の出し忘れ', $n->content);

        // 許可された人は、その月を保存・申請できる。ほかの人・ほかの月はできない
        $id = $this->actingAs($u)->postJson(route('user.billing.transport.autosave'), $this->body('2026-08', '2026-08-03'))->assertOk()->json('id');
        $this->actingAs($u)->post(route('user.billing.transport.submit', ['expense' => $id]))->assertSessionHasNoErrors();
        $this->actingAs($other)->postJson(route('user.billing.transport.autosave'), $this->body('2026-08', '2026-08-03'))->assertStatus(422);
        $this->actingAs($u)->postJson(route('user.billing.transport.autosave'), $this->body('2026-07', '2026-07-03'))->assertStatus(422);

        // 画面の props: 期限後だが、解除されている
        $this->actingAs($u)->get(route('user.billing.transport.index', ['month' => '2026-08']))
            ->assertInertia(fn ($p) => $p->where('deadline.passed', true)->where('deadline.extended', true));

        // 取り消すと、また不可（申請済みのものは、そのまま）
        $this->actingAs($clerk)->delete(route('clerk.billing.transport.deadlines.destroy', ['extension' => $ext->id]))->assertSessionHasNoErrors();
        $this->assertSame(0, TransportDeadlineExtension::count());
        $this->actingAs($u)->post(route('user.billing.transport.withdraw', ['expense' => $id]))->assertStatus(422);
    }

    public function test_期限内の月は解除できない_他社の人は対象にできない_権限のない人は不可(): void
    {
        $clerk = $this->user('clerk');
        $u = $this->user();
        $this->actingAs($clerk)->post(route('clerk.billing.transport.deadlines.store'), ['user_id' => $u->id, 'billing_month' => '2026-09'])
            ->assertSessionHasErrors('billing_month');   // 9 月は、まだ期限内

        $otherCompanyUser = $this->user('user', $this->sanei);
        $this->actingAs($clerk)->post(route('clerk.billing.transport.deadlines.store'), ['user_id' => $otherCompanyUser->id, 'billing_month' => '2026-08'])->assertStatus(403);

        $this->actingAs($this->user('user'))->post(route('clerk.billing.transport.deadlines.store'), ['user_id' => $u->id, 'billing_month' => '2026-08'])->assertStatus(403);
        $this->assertSame(0, TransportDeadlineExtension::count());

        // 他社の総務・経理は、取り消しもできない
        $ext = TransportDeadlineExtension::create(['company_id' => $this->sb->id, 'user_id' => $u->id, 'billing_month' => '2026-08', 'granted_by' => $clerk->id]);
        $this->actingAs($this->user('clerk', $this->sanei))->delete(route('clerk.billing.transport.deadlines.destroy', ['extension' => $ext->id]))->assertStatus(403);
    }

    public function test_管理画面に_解除の一覧と選択肢が出る(): void
    {
        $clerk = $this->user('clerk');
        $u = $this->user('user');
        $this->actingAs($clerk)->post(route('clerk.billing.transport.deadlines.store'), ['user_id' => $u->id, 'billing_month' => '2026-08', 'reason' => '理由']);

        $this->actingAs($clerk)->get(route('clerk.billing.transport.index'))
            ->assertInertia(fn ($p) => $p->where('deadlineEnforced', true)
                ->where('deadlines.0.user_name', $u->name)->where('deadlines.0.billing_month', '2026-08')->where('deadlines.0.reason', '理由')
                ->where('applicants', fn ($list) => collect($list)->pluck('id')->contains($u->id))
                ->where('pastMonths', fn ($m) => collect($m)->contains('2026-08') && !collect($m)->contains('2026-09')));
    }

    // ── 申請者からの依頼 → 総務・経理が許可・却下 ──────────────

    private function request(User $u, string $month = '2026-08', string $reason = '出張の精算の出し忘れのため')
    {
        return $this->actingAs($u)->post(route('user.billing.transport.deadline_requests.store'), ['billing_month' => $month, 'reason' => $reason]);
    }

    public function test_期限後の月を依頼すると_依頼中になり_同じ会社の総務経理にだけ通知される(): void
    {
        $u = $this->user();
        $clerk = $this->user('clerk');
        $clerk2 = $this->user('clerk');
        $clerkOther = $this->user('clerk', $this->sanei);

        $this->request($u)->assertSessionHasNoErrors();
        $ext = TransportDeadlineExtension::first();
        $this->assertSame([$u->id, '2026-08', 'requested', '出張の精算の出し忘れのため', $this->sb->id], [$ext->user_id, $ext->billing_month, $ext->status, $ext->reason, $ext->company_id]);
        $this->assertNotNull($ext->requested_at);

        // 依頼中は、まだ入力・申請できない
        $this->actingAs($u)->postJson(route('user.billing.transport.autosave'), $this->body('2026-08', '2026-08-03'))->assertStatus(422);
        $this->actingAs($u)->get(route('user.billing.transport.index', ['month' => '2026-08']))
            ->assertInertia(fn ($p) => $p->where('canEdit', false)->where('deadlineRequest.status', 'requested')->where('deadlineRequest.reason', '出張の精算の出し忘れのため'));

        // 総務・経理（同じ会社）に、お知らせ。他社には届かない。本人には届かない
        $n = Announcement::where('title', 'like', '%の期限後の申請を依頼しました')->first();
        $this->assertNotNull($n);
        $ids = AnnouncementRecipient::where('announcement_id', $n->id)->pluck('user_id')->all();
        $this->assertEqualsCanonicalizing([$clerk->id, $clerk2->id], $ids);
        $this->assertStringContainsString('出張の精算の出し忘れのため', $n->content);

        // 依頼し直すと 1 件のまま（理由を更新）
        $this->request($u, '2026-08', '理由を書き直します');
        $this->assertSame(1, TransportDeadlineExtension::count());
        $this->assertSame('理由を書き直します', TransportDeadlineExtension::first()->reason);
    }

    public function test_依頼の入力チェックと_取り下げ(): void
    {
        $u = $this->user();
        $this->request($u, '2026-08', '')->assertSessionHasErrors('reason');                 // 理由は必須
        $this->request($u, '2026-09')->assertSessionHasErrors('billing_month');              // 9 月は期限内
        $this->assertSame(0, TransportDeadlineExtension::count());

        $this->request($u);
        $ext = TransportDeadlineExtension::first();
        $other = $this->user();
        $this->actingAs($other)->delete(route('user.billing.transport.deadline_requests.destroy', ['extension' => $ext->id]))->assertStatus(403);   // 他人の依頼は取り下げられない
        $this->actingAs($u)->delete(route('user.billing.transport.deadline_requests.destroy', ['extension' => $ext->id]))->assertSessionHasNoErrors();
        $this->assertSame(0, TransportDeadlineExtension::count());

        // すでに許可されている月は、依頼できない
        TransportDeadlineExtension::create(['company_id' => $this->sb->id, 'user_id' => $u->id, 'billing_month' => '2026-08', 'status' => 'granted']);
        $this->request($u)->assertSessionHasErrors('billing_month');
    }

    public function test_許可すると_期限が解除されて本人に通知される(): void
    {
        $u = $this->user();
        $clerk = $this->user('clerk');
        $this->request($u);
        $ext = TransportDeadlineExtension::first();

        $this->actingAs($clerk)->post(route('clerk.billing.transport.deadlines.approve', ['extension' => $ext->id]), ['comment' => '今回は許可します'])->assertSessionHasNoErrors();
        $ext->refresh();
        $this->assertSame(['granted', $clerk->id, '今回は許可します'], [$ext->status, $ext->granted_by, $ext->decision_comment]);
        $this->assertNotNull($ext->decided_at);

        $n = Announcement::where('title', 'like', '【交通費】%期限が解除されました')->first();
        $this->assertNotNull($n);
        $this->assertTrue(AnnouncementRecipient::where('announcement_id', $n->id)->where('user_id', $u->id)->exists());
        $this->assertStringContainsString('今回は許可します', $n->content);

        $this->actingAs($u)->postJson(route('user.billing.transport.autosave'), $this->body('2026-08', '2026-08-03'))->assertOk();
        $this->actingAs($u)->get(route('user.billing.transport.index', ['month' => '2026-08']))
            ->assertInertia(fn ($p) => $p->where('canEdit', true)->where('deadline.extended', true));
    }

    public function test_却下すると_入力できないままで_コメントが本人に伝わり_依頼し直せる(): void
    {
        $u = $this->user();
        $clerk = $this->user('clerk');
        $this->request($u);
        $ext = TransportDeadlineExtension::first();

        $this->actingAs($clerk)->post(route('clerk.billing.transport.deadlines.reject', ['extension' => $ext->id]), ['comment' => '次の月に含めてください'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $ext->fresh()->status);
        $n = Announcement::where('title', 'like', '%の依頼が、見送られました')->first();
        $this->assertNotNull($n);
        $this->assertStringContainsString('次の月に含めてください', $n->content);

        $this->actingAs($u)->postJson(route('user.billing.transport.autosave'), $this->body('2026-08', '2026-08-03'))->assertStatus(422);
        $this->actingAs($u)->get(route('user.billing.transport.index', ['month' => '2026-08']))
            ->assertInertia(fn ($p) => $p->where('canEdit', false)->where('deadlineRequest.status', 'rejected')->where('deadlineRequest.decision_comment', '次の月に含めてください'));

        // 依頼し直すと、依頼中に戻り、前の判断は消える
        $this->request($u, '2026-08', '事情が変わりました')->assertSessionHasNoErrors();
        $ext->refresh();
        $this->assertSame(['requested', null, null], [$ext->status, $ext->granted_by, $ext->decision_comment]);
    }

    public function test_許可・却下は同じ会社の総務経理だけ_決まったものは、もう一度は決められない(): void
    {
        $u = $this->user();
        $this->request($u);
        $ext = TransportDeadlineExtension::first();

        $this->actingAs($this->user('clerk', $this->sanei))->post(route('clerk.billing.transport.deadlines.approve', ['extension' => $ext->id]))->assertStatus(403);
        $this->actingAs($this->user('user'))->post(route('clerk.billing.transport.deadlines.approve', ['extension' => $ext->id]))->assertStatus(403);
        $this->assertSame('requested', $ext->fresh()->status);

        $clerk = $this->user('clerk');
        $this->actingAs($clerk)->post(route('clerk.billing.transport.deadlines.approve', ['extension' => $ext->id]))->assertSessionHasNoErrors();
        // 他の人が先に許可した後に、却下しようとすると止まる
        $this->actingAs($this->user('clerk'))->post(route('clerk.billing.transport.deadlines.reject', ['extension' => $ext->id]))->assertSessionHasErrors('action');
        $this->assertSame('granted', $ext->fresh()->status);
    }

    public function test_管理画面の一覧とハブに_依頼中の件数と履歴が出る(): void
    {
        $clerk = $this->user('clerk');
        $u1 = $this->user();
        $u2 = $this->user();
        $this->request($u1);
        // 2 か月前の依頼（仕様の変更前に出されたもの）。許可・却下の操作は、先月より前でもできる
        $ext2 = TransportDeadlineExtension::create(['company_id' => $this->sb->id, 'user_id' => $u2->id, 'billing_month' => '2026-07', 'status' => 'requested', 'reason' => '古い依頼', 'requested_at' => now()]);
        $this->actingAs($clerk)->post(route('clerk.billing.transport.deadlines.reject', ['extension' => $ext2->id]), ['comment' => '見送り']);

        $this->actingAs($clerk)->get(route('clerk.billing.transport.index'))
            ->assertInertia(fn ($p) => $p->has('deadlineRequests', 1)->where('deadlineRequests.0.user_name', $u1->name)->where('deadlineRequests.0.reason', '出張の精算の出し忘れのため')
                ->has('deadlines', 1)->where('deadlines.0.status', 'rejected')->where('deadlines.0.decision_comment', '見送り'));
        $this->actingAs($clerk)->get(route('clerk.applications.index'))->assertInertia(fn ($p) => $p->where('deadlinePending', 1));
    }

    // ── 期限後の申請を依頼・許可できるのは、先月分まで ─────────────

    public function test_依頼できるのは先月分まで_それより前は依頼も許可もできない(): void
    {
        // 今は 2026-09-15: 先月 = 2026-08
        $this->assertTrue(TransportDeadline::requestable('2026-08'));
        $this->assertFalse(TransportDeadline::requestable('2026-07'));   // 2 か月前
        $this->assertFalse(TransportDeadline::requestable('2026-09'));   // 当月は期限内（依頼は不要）

        // 月が変わって 10 月になれば、9 月分が対象で、8 月分は対象外になる
        Carbon::setTestNow('2026-10-01 00:00:00');
        $this->assertTrue(TransportDeadline::requestable('2026-09'));
        $this->assertFalse(TransportDeadline::requestable('2026-08'));
        // 年をまたぐ: 2027-01 のとき、先月は 2026-12
        Carbon::setTestNow('2027-01-10 10:00:00');
        $this->assertTrue(TransportDeadline::requestable('2026-12'));
        $this->assertFalse(TransportDeadline::requestable('2026-11'));
    }

    public function test_先月より前の月は_申請者の依頼も総務経理の直接の許可もできず_画面にも依頼の案内が出ない(): void
    {
        $u = $this->user();
        $clerk = $this->user('clerk');

        $this->request($u, '2026-07')->assertSessionHasErrors('billing_month');
        $this->actingAs($clerk)->post(route('clerk.billing.transport.deadlines.store'), ['user_id' => $u->id, 'billing_month' => '2026-07'])
            ->assertSessionHasErrors('billing_month');
        $this->assertSame(0, TransportDeadlineExtension::count());

        $this->actingAs($u)->get(route('user.billing.transport.index', ['month' => '2026-07']))
            ->assertInertia(fn ($p) => $p->where('canEdit', false)->where('deadline.passed', true)->where('deadline.requestable', false));
        $this->actingAs($u)->get(route('user.billing.transport.index', ['month' => '2026-08']))
            ->assertInertia(fn ($p) => $p->where('deadline.requestable', true));

        // 先月分は、依頼できる
        $this->request($u, '2026-08')->assertSessionHasNoErrors();
        $this->assertSame(1, TransportDeadlineExtension::count());
    }

    public function test_設定で何か月前まで依頼できるかを変えられる(): void
    {
        config(['transport.request_months_back' => 3]);   // 3 か月前（2026-06）まで
        $this->assertTrue(TransportDeadline::requestable('2026-06'));
        $this->assertFalse(TransportDeadline::requestable('2026-05'));
    }
}
