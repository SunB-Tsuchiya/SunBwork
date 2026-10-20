<?php

namespace Tests\Feature\Billing;

use App\Models\Company;
use App\Models\Department;
use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Models\SunA\GeneralAffairs\ApplicationType;
use App\Models\TransportExpense;
use App\Models\TransportExpenseEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 交通費: 総務・経理の受理／差戻し／請求済み、履歴、通知、会社ごとの分離、権限 */
class TransportExpenseManageTest extends TestCase
{
    use RefreshDatabase;

    private Company $sb;
    private Company $sanei;

    protected function setUp(): void
    {
        parent::setUp();
        config(['transport.enforce_deadline' => false]);   // 既存のテストは、過去の月で入力するため、期限の強制を切る（期限の検証は TransportDeadlineTest）
        $this->sb = Company::create(['name' => '株式会社サン・ブレーン', 'code' => 'SB' . random_int(100, 999), 'company_type' => 'sunbrain', 'active' => true]);
        $this->sanei = Company::create(['name' => '株式会社サンエー印刷', 'code' => 'SA' . random_int(100, 999), 'company_type' => 'general', 'active' => true]);
    }

    private function user(string $role, ?Company $c, string $name = ''): User
    {
        return User::factory()->create(array_filter(['user_role' => $role, 'company_id' => $c?->id, 'name' => $name ?: null]));
    }

    /** 申請者（現状の申請画面は SuperAdmin のルート上にあるので SuperAdmin）が、指定の会社で申請済みの 1 件を作る */
    private function submitted(Company $c, string $name = '申請者A', int $amount = 420, ?Department $dept = null): array
    {
        $u = $this->user('superadmin', $c, $name);
        $this->actingAs($u)->post(route('user.billing.transport.store'), [
            'billing_month' => '2026-08', 'charge_department_id' => $dept?->id,
            'items' => [[
                'occurrence_date' => '2026-08-05', 'destination' => '小台工場', 'purpose' => 'round_trip', 'purpose_text' => '',
                'station_from' => '田端', 'station_to' => '宮城都営住宅前', 'fare_type' => 'ic',
                'amount' => $amount, 'base_amount' => $amount, 'auto_double' => false,
            ]],
        ])->assertSessionHasNoErrors();
        $e = TransportExpense::where('user_id', $u->id)->first();
        $this->actingAs($u)->post(route('user.billing.transport.submit', ['expense' => $e->id]))->assertSessionHasNoErrors();
        return [$u, $e->fresh()];
    }

    /** その人の「お知らせ」（受信したもの） */
    private function inbox(User $u)
    {
        return AnnouncementRecipient::where('user_id', $u->id)->with('announcement')->get()->pluck('announcement');
    }

    public function test_申請すると同じ会社の総務経理だけにお知らせが届き_履歴が残る(): void
    {
        $clerkSb = $this->user('clerk', $this->sb);
        $clerkSb2 = $this->user('clerk', $this->sb);
        $clerkSanei = $this->user('clerk', $this->sanei);

        [$applicant, $e] = $this->submitted($this->sb, '申請者A');

        $this->assertCount(1, $this->inbox($clerkSb));
        $this->assertCount(1, $this->inbox($clerkSb2));
        $this->assertCount(0, $this->inbox($clerkSanei));       // 他社には届かない
        $this->assertCount(0, $this->inbox($applicant));        // 本人には届かない
        $this->assertStringContainsString('申請者Aさんが', $this->inbox($clerkSb)->first()->title);
        $this->assertStringContainsString('「申請」→「交通費」', $this->inbox($clerkSb)->first()->content);

        $ev = TransportExpenseEvent::where('transport_expense_id', $e->id)->first();
        $this->assertSame(['submit', 'draft', 'submitted'], [$ev->action, $ev->from_status, $ev->to_status]);
    }

    public function test_受理すると申請者に通知され_請求済みまで進められ_取り消しもできる(): void
    {
        $clerk = $this->user('clerk', $this->sb);
        [$applicant, $e] = $this->submitted($this->sb);

        $this->actingAs($clerk)->post(route('clerk.billing.transport.approve', ['expense' => $e->id]), ['comment' => '確認しました'])->assertSessionHasNoErrors();
        $e->refresh();
        $this->assertSame('approved', $e->status);
        $this->assertSame($clerk->id, $e->reviewed_by);
        $this->assertSame('確認しました', $e->review_comment);
        $this->assertStringContainsString('受理されました', $this->inbox($applicant)->last()->title);

        $this->actingAs($clerk)->post(route('clerk.billing.transport.bill', ['expense' => $e->id]))->assertSessionHasNoErrors();
        $this->assertSame('billed', $e->fresh()->status);
        $this->assertNotNull($e->fresh()->billed_at);

        $this->actingAs($clerk)->post(route('clerk.billing.transport.unbill', ['expense' => $e->id]));
        $this->assertSame('approved', $e->fresh()->status);
        $this->assertNull($e->fresh()->billed_at);

        $this->actingAs($clerk)->post(route('clerk.billing.transport.unapprove', ['expense' => $e->id]));
        $this->assertSame('submitted', $e->fresh()->status);
        $this->assertNull($e->fresh()->reviewed_by);

        $this->assertSame(['submit', 'approve', 'bill', 'unbill', 'unapprove'],
            TransportExpenseEvent::where('transport_expense_id', $e->id)->orderBy('id')->pluck('action')->all());
    }

    public function test_差戻しはコメント必須で_修正して再申請でき_履歴にコメントが残る(): void
    {
        $clerk = $this->user('clerk', $this->sb);
        [$applicant, $e] = $this->submitted($this->sb);

        // コメント無しは不可
        $this->actingAs($clerk)->post(route('clerk.billing.transport.return', ['expense' => $e->id]), ['comment' => '  '])->assertSessionHasErrors('comment');
        $this->assertSame('submitted', $e->fresh()->status);

        $this->actingAs($clerk)->post(route('clerk.billing.transport.return', ['expense' => $e->id]), ['comment' => '金額が違います'])->assertSessionHasNoErrors();
        $this->assertSame('returned', $e->fresh()->status);
        $msg = $this->inbox($applicant)->last();
        $this->assertStringContainsString('差戻し', $msg->title);
        $this->assertStringContainsString('金額が違います', $msg->content);

        // 申請者は修正して再申請できる。再申請で差戻しコメントはクリアされるが、履歴には残る
        $this->actingAs($applicant)->post(route('user.billing.transport.submit', ['expense' => $e->id]))->assertSessionHasNoErrors();
        $this->assertSame('submitted', $e->fresh()->status);
        $this->assertNull($e->fresh()->review_comment);
        $this->assertSame('金額が違います', TransportExpenseEvent::where('transport_expense_id', $e->id)->where('action', 'return')->value('comment'));
    }

    public function test_状態が合わない操作は止まる(): void
    {
        $clerk = $this->user('clerk', $this->sb);
        [, $e] = $this->submitted($this->sb);

        // 申請済みのまま「請求済みにする」は不可
        $this->actingAs($clerk)->post(route('clerk.billing.transport.bill', ['expense' => $e->id]))->assertSessionHasErrors('action');
        $this->assertSame('submitted', $e->fresh()->status);

        // 受理済みをもう一度受理しようとすると止まる（他の人が先に操作した場合と同じ）
        $this->actingAs($clerk)->post(route('clerk.billing.transport.approve', ['expense' => $e->id]));
        $this->actingAs($clerk)->post(route('clerk.billing.transport.approve', ['expense' => $e->id]))->assertSessionHasErrors('action');
        $this->assertSame(1, TransportExpenseEvent::where('transport_expense_id', $e->id)->where('action', 'approve')->count());
    }

    public function test_他社の総務経理は見られず操作もできない_下書きは見えない(): void
    {
        $clerkSb = $this->user('clerk', $this->sb);
        $clerkSanei = $this->user('clerk', $this->sanei);
        [, $e] = $this->submitted($this->sb);

        $this->actingAs($clerkSanei)->get(route('clerk.billing.transport.show', ['expense' => $e->id]))->assertStatus(403);
        $this->actingAs($clerkSanei)->post(route('clerk.billing.transport.approve', ['expense' => $e->id]))->assertStatus(403);
        $this->actingAs($clerkSanei)->get(route('clerk.billing.transport.excel', ['expense' => $e->id]))->assertStatus(403);
        $this->actingAs($clerkSb)->get(route('clerk.billing.transport.excel', ['expense' => $e->id]))->assertOk();

        // 一覧にも他社分は出ない
        $this->actingAs($clerkSanei)->get(route('clerk.billing.transport.index', ['month' => '2026-08']))
            ->assertInertia(fn ($p) => $p->where('grandTotal', 0)->has('groups', 0));

        // 下書きは管理側に見えない
        $e->update(['status' => 'draft']);
        $this->actingAs($clerkSb)->get(route('clerk.billing.transport.show', ['expense' => $e->id]))->assertStatus(404);
        $this->actingAs($clerkSb)->get(route('clerk.billing.transport.index', ['month' => '2026-08']))
            ->assertInertia(fn ($p) => $p->has('groups', 0));
    }

    public function test_管理権限のない人は使えない(): void
    {
        [, $e] = $this->submitted($this->sb);
        $plain = $this->user('user', $this->sb);
        $this->actingAs($plain)->get(route('clerk.billing.transport.index'))->assertStatus(403);
        $this->actingAs($plain)->post(route('clerk.billing.transport.approve', ['expense' => $e->id]))->assertStatus(403);
    }

    public function test_一覧は請求先部門ごとにまとまり_状態で絞り込め_CSVが出る(): void
    {
        $clerk = $this->user('clerk', $this->sb);
        $seihan = Department::create(['company_id' => $this->sb->id, 'name' => '製版', 'code' => 'SEIHAN', 'sort_order' => 1, 'active' => true]);
        [, $e1] = $this->submitted($this->sb, '山田', 1000, $seihan);
        [, $e2] = $this->submitted($this->sb, '鈴木', 500, null);   // 共通
        $e2->update(['status' => 'approved']);

        $this->actingAs($clerk)->get(route('clerk.billing.transport.index', ['month' => '2026-08']))
            ->assertInertia(fn ($p) => $p
                ->where('grandTotal', 1500)
                ->where('groups.0.department_name', '製版')       // 共通は最後
                ->where('groups.1.department_name', '共通')
                ->where('statusSummary.submitted.count', 1)
                ->where('statusSummary.approved.count', 1));

        $this->actingAs($clerk)->get(route('clerk.billing.transport.index', ['month' => '2026-08', 'status' => 'approved']))
            ->assertInertia(fn ($p) => $p->where('grandTotal', 500)->has('groups', 1));

        $res = $this->actingAs($clerk)->get(route('clerk.billing.transport.summary', ['month' => '2026-08']));
        $res->assertOk();
        $csv = $res->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('山田', $csv);
        $this->assertStringContainsString('1500', $csv);
    }

    public function test_申請者の画面に履歴が出る(): void
    {
        $clerk = $this->user('clerk', $this->sb);
        [$applicant, $e] = $this->submitted($this->sb);
        $this->actingAs($clerk)->post(route('clerk.billing.transport.return', ['expense' => $e->id]), ['comment' => '見直してください']);

        $this->actingAs($applicant)->get(route('user.billing.transport.index', ['month' => '2026-08']))
            ->assertInertia(fn ($p) => $p->where('expense.status', 'returned')
                ->where('expense.events.1.action', 'return')->where('expense.events.1.comment', '見直してください'));
    }

    public function test_申請ハブ_全Clerkに交通費が出て_年末調整などはサンエーの総務だけ(): void
    {
        ApplicationType::create(['company_id' => $this->sanei->id, 'created_by' => $this->user('admin', $this->sanei)->id, 'name' => '年末調整', 'feature_key' => 'nencho', 'sort_order' => 1, 'is_active' => true]);
        $deptSoumu = Department::create(['company_id' => $this->sanei->id, 'name' => '総務部', 'code' => 'SOUMU', 'sort_order' => 1, 'active' => true]);
        $deptKeiri = Department::create(['company_id' => $this->sanei->id, 'name' => '経理部', 'code' => 'KEIRI', 'sort_order' => 2, 'active' => true]);

        $clerkSb = $this->user('clerk', $this->sb);
        $soumu = User::factory()->create(['user_role' => 'clerk', 'company_id' => $this->sanei->id, 'department_id' => $deptSoumu->id]);
        $keiri = User::factory()->create(['user_role' => 'clerk', 'company_id' => $this->sanei->id, 'department_id' => $deptKeiri->id]);
        $this->submitted($this->sb);   // サン・ブレーンの申請済みが 1 件

        // サン・ブレーン: 交通費のみ（年末調整は出さない）。申請済みの件数がバッジになる
        $this->actingAs($clerkSb)->get(route('clerk.applications.index'))
            ->assertInertia(fn ($p) => $p->component('Billing/Applications/Index', false)
                ->where('transportPending', 1)->where('showTypes', false)->has('types', 0));

        // サンエー印刷の総務: 年末調整などの種類も出る。他社の申請は数えない
        $this->actingAs($soumu)->get(route('clerk.applications.index'))
            ->assertInertia(fn ($p) => $p->where('transportPending', 0)->where('showTypes', true)->has('types', 1)->where('types.0.name', '年末調整'));

        // サンエー印刷の経理: 交通費のみ
        $this->actingAs($keiri)->get(route('clerk.applications.index'))
            ->assertInertia(fn ($p) => $p->where('showTypes', false)->has('types', 0));

        // 管理権限のない人は入れない
        $this->actingAs($this->user('user', $this->sb))->get(route('clerk.applications.index'))->assertStatus(403);
    }

    public function test_申請と再申請は_同じ会社の総務経理とAdmin_およびSuperAdminに通知され_本人と他社のAdminには届かない(): void
    {
        $applicantClerk = $this->user('clerk', $this->sb, '総務の太郎');   // 総務の人が、自分の交通費をユーザーとして申請する場合
        $adminSb = $this->user('admin', $this->sb);
        $adminSanei = $this->user('admin', $this->sanei);
        $superadmin = $this->user('superadmin', $this->sb);
        $superadminSanei = $this->user('superadmin', $this->sanei);
        $clerkSb = $this->user('clerk', $this->sb);

        $body = ['billing_month' => '2026-08', 'charge_department_id' => null, 'items' => [[
            'occurrence_date' => '2026-08-05', 'destination' => '小台工場', 'purpose' => 'round_trip', 'purpose_text' => '',
            'station_from' => '田端', 'station_to' => '宮城都営住宅前', 'fare_type' => 'ic', 'amount' => 840, 'base_amount' => 420, 'auto_double' => true,
        ]]];
        $id = $this->actingAs($applicantClerk)->postJson(route('user.billing.transport.autosave'), $body)->assertOk()->json('id');
        $this->actingAs($applicantClerk)->post(route('user.billing.transport.submit', ['expense' => $id]))->assertSessionHasNoErrors();

        // 申請: 管理側（同じ会社の Clerk・Admin、SuperAdmin）に届く。本人・他社の Admin には届かない
        foreach ([$adminSb, $superadmin, $superadminSanei, $clerkSb] as $u) {
            $this->assertCount(1, $this->inbox($u), "{$u->user_role} に届く");
            $this->assertStringContainsString('申請しました', $this->inbox($u)->first()->title);
            $this->assertStringNotContainsString('再申請', $this->inbox($u)->first()->title);
        }
        $this->assertCount(0, $this->inbox($applicantClerk));
        $this->assertCount(0, $this->inbox($adminSanei));

        // 差戻し → 再申請: 「再申請しました」で、同じ人たちに届く
        $this->actingAs($clerkSb)->post(route('clerk.billing.transport.return', ['expense' => $id]), ['comment' => '金額を確認してください'])->assertSessionHasNoErrors();
        $this->actingAs($applicantClerk)->post(route('user.billing.transport.submit', ['expense' => $id]))->assertSessionHasNoErrors();

        foreach ([$adminSb, $superadmin, $superadminSanei] as $u) {
            $this->assertCount(2, $this->inbox($u));
            $last = $this->inbox($u)->last();
            $this->assertStringContainsString('総務の太郎さんが2026年8月分を再申請しました', $last->title);
            $this->assertStringContainsString('差戻しのあと、修正して再申請されました', $last->content);
        }
        $this->assertCount(0, $this->inbox($adminSanei));
    }
}
