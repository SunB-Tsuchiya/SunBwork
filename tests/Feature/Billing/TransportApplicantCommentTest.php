<?php

namespace Tests\Feature\Billing;

use App\Models\AnnouncementRecipient;
use App\Models\Company;
use App\Models\TransportExpense;
use App\Models\TransportExpenseEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 交通費: 申請者のコメント（申請・再申請に添える。差戻しへの返事など） */
class TransportApplicantCommentTest extends TestCase
{
    use RefreshDatabase;

    private Company $sb;

    protected function setUp(): void
    {
        parent::setUp();
        config(['transport.enforce_deadline' => false, 'applications.enforce_access' => false]);
        $this->sb = Company::create(['name' => '株式会社サン・ブレーン', 'code' => 'SB' . random_int(100, 999), 'company_type' => 'sunbrain', 'active' => true]);
    }

    private function user(string $role = 'user'): User
    {
        return User::factory()->create(['user_role' => $role, 'company_id' => $this->sb->id]);
    }

    private function body(array $over = []): array
    {
        return array_merge(['billing_month' => '2026-08', 'charge_department_id' => null, 'items' => [[
            'occurrence_date' => '2026-08-05', 'destination' => '国際交流基金', 'purpose' => 'outbound', 'purpose_text' => '',
            'station_from' => '田端', 'station_to' => '市ヶ谷', 'fare_type' => 'ic', 'amount' => 200, 'base_amount' => 200, 'auto_double' => false,
        ]]], $over);
    }

    private function draft(User $u, array $over = []): TransportExpense
    {
        $this->actingAs($u)->postJson(route('user.billing.transport.autosave'), $this->body($over))->assertOk();
        return TransportExpense::where('user_id', $u->id)->firstOrFail();
    }

    public function test_書きかけのコメントは自動保存され_開き直しても返る(): void
    {
        $u = $this->user();
        $this->draft($u, ['applicant_comment' => '出先から懇親会へ行きました']);
        $this->assertSame('出先から懇親会へ行きました', TransportExpense::first()->applicant_comment);
        $this->actingAs($u)->get(route('user.billing.transport.index', ['month' => '2026-08']))
            ->assertInertia(fn ($p) => $p->where('expense.applicant_comment', '出先から懇親会へ行きました'), false);

        // 空にすると NULL に戻る
        $this->draft($u, ['applicant_comment' => '  ']);
        $this->assertNull(TransportExpense::first()->applicant_comment);
    }

    public function test_申請するとコメントが履歴に残り_書きかけは空に戻り_総務経理の通知にも添えられる(): void
    {
        $u = $this->user(); $clerk = $this->user('clerk'); $admin = $this->user('admin');
        $e = $this->draft($u, ['applicant_comment' => '下書きのコメント']);
        $this->actingAs($u)->post(route('user.billing.transport.submit', ['expense' => $e->id]), ['comment' => '3行目：直帰のため、会社には戻っていません。'])
            ->assertSessionHasNoErrors();

        $e->refresh();
        $this->assertSame('submitted', $e->status);
        $this->assertNull($e->applicant_comment);
        $this->assertSame('3行目：直帰のため、会社には戻っていません。', TransportExpenseEvent::where('action', 'submit')->first()->comment);

        $n = AnnouncementRecipient::where('user_id', $clerk->id)->with('announcement')->get()->pluck('announcement')->first();
        $this->assertStringContainsString('申請者のコメント', $n->content);
        $this->assertStringContainsString('直帰のため', $n->content);
        $this->assertSame(0, AnnouncementRecipient::where('user_id', $admin->id)->count());   // Admin には届かない（総務・経理だけ）
    }

    public function test_申請時にコメントを送らなければ_書きかけのコメントを使い_どちらも無くても申請できる(): void
    {
        $u = $this->user();
        $e = $this->draft($u, ['applicant_comment' => '書きかけ']);
        $this->actingAs($u)->post(route('user.billing.transport.submit', ['expense' => $e->id]))->assertSessionHasNoErrors();
        $this->assertSame('書きかけ', TransportExpenseEvent::where('action', 'submit')->first()->comment);

        $u2 = $this->user();
        $e2 = $this->draft($u2);
        $this->actingAs($u2)->post(route('user.billing.transport.submit', ['expense' => $e2->id]))->assertSessionHasNoErrors();
        $this->assertNull(TransportExpenseEvent::where('action', 'submit')->where('transport_expense_id', $e2->id)->first()->comment);
    }

    public function test_差戻しへの返事を添えて再申請でき_総務経理の確認画面には最新のコメントが出る(): void
    {
        $u = $this->user(); $clerk = $this->user('clerk');
        $e = $this->draft($u);
        $this->actingAs($u)->post(route('user.billing.transport.submit', ['expense' => $e->id]), ['comment' => '最初の申請です'])->assertSessionHasNoErrors();
        $this->actingAs($clerk)->post(route('clerk.billing.transport.return', ['expense' => $e->id]), ['comment' => '1行目：会社に戻っていないが、よいか？'])->assertSessionHasNoErrors();
        $this->actingAs($u)->post(route('user.billing.transport.submit', ['expense' => $e->id]), ['comment' => '1行目：直帰です。この記載で正しいです。'])->assertSessionHasNoErrors();

        // 履歴は、申請 → 差戻し → 再申請 の順に、コメントつきで残る
        $this->assertSame(
            ['最初の申請です', '1行目：会社に戻っていないが、よいか？', '1行目：直帰です。この記載で正しいです。'],
            TransportExpenseEvent::where('transport_expense_id', $e->id)->orderBy('id')->pluck('comment')->all(),
        );
        $this->actingAs($clerk)->get(route('clerk.billing.transport.show', ['expense' => $e->id]))->assertOk()
            ->assertInertia(fn ($p) => $p->where('expense.applicant_comment.comment', '1行目：直帰です。この記載で正しいです。'), false);
    }

    public function test_コメントが長すぎると_保存も申請もできない(): void
    {
        $u = $this->user();
        $this->actingAs($u)->postJson(route('user.billing.transport.autosave'), $this->body(['applicant_comment' => str_repeat('あ', 1001)]))->assertStatus(422);
        $e = $this->draft($u);
        $this->actingAs($u)->post(route('user.billing.transport.submit', ['expense' => $e->id]), ['comment' => str_repeat('あ', 1001)])->assertSessionHasErrors('comment');
        $this->assertSame('draft', $e->fresh()->status);
    }
}
