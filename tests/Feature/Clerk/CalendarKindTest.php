<?php

namespace Tests\Feature\Clerk;

use App\Models\ClerkCalendarColor;
use App\Models\ClerkCalendarReminder;
use App\Models\ClerkEvent;
use App\Models\ClerkScheduleRule;
use App\Models\ClerkWeekPost;
use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use App\Support\ClerkCalendarKind;
use App\Support\ClerkCalendarKindBackfill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * サンエー印刷: 総務・経理・営業が、それぞれ別のカレンダーと予定を持つ（SUNASALES_PLAN1.md §14）。
 * サン・ブレーン（一般会社でない）は、従来どおり会社単位（calendar_kind が空文字）で、挙動が変わらないことも確認する。
 */
class CalendarKindTest extends TestCase
{
    use RefreshDatabase;

    private Company $sanei;

    private Company $sunbrain;

    private Department $soumuDept;

    private Department $keiriDept;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanei = Company::create(['name' => 'サンエー_' . uniqid(), 'code' => 'SANEI_' . uniqid(), 'company_type' => 'general', 'active' => true]);
        $this->sunbrain = Company::create(['name' => 'SB_' . uniqid(), 'code' => 'SB_' . uniqid(), 'company_type' => 'sunbrain', 'active' => true]);
        $this->soumuDept = Department::create(['company_id' => $this->sanei->id, 'name' => '総務部', 'code' => 'soumu_' . uniqid(), 'area' => 'soumu']);
        $this->keiriDept = Department::create(['company_id' => $this->sanei->id, 'name' => '経理部', 'code' => 'keiri_' . uniqid(), 'area' => 'keiri']);
    }

    private function clerk(Department $dept, ?Company $company = null): User
    {
        return User::factory()->create(['user_role' => 'clerk', 'company_id' => ($company ?? $this->sanei)->id, 'department_id' => $dept->id]);
    }

    private function sunbrainClerk(): User
    {
        return User::factory()->create(['user_role' => 'clerk', 'company_id' => $this->sunbrain->id]);
    }

    private function addEvent(User $user, string $title, string $start = '2026-11-10')
    {
        return $this->actingAs($user)->postJson(route('clerk.calendar.events.store'), ['title' => $title, 'starts_at' => $start, 'all_day' => true]);
    }

    private function titles(User $user): array
    {
        $r = $this->actingAs($user)->getJson(route('clerk.calendar.events.index', ['year' => 2026]))->assertOk();

        return collect($r->json())->pluck('title')->sort()->values()->all();
    }

    private function kindFor(?User $user, array $session = []): string
    {
        $request = \Illuminate\Http\Request::create('/clerk/calendar');
        $request->setUserResolver(fn () => $user);
        session($session);

        return ClerkCalendarKind::forRequest($request);
    }

    public function test_kind_is_resolved_from_company_and_department()
    {
        $this->assertSame('soumu', $this->kindFor($this->clerk($this->soumuDept)));
        $this->assertSame('keiri', $this->kindFor($this->clerk($this->keiriDept)));
        // サン・ブレーンは、従来どおり（空文字）
        $this->assertSame('', $this->kindFor($this->sunbrainClerk()));
        // 管理者も、自分の部署のエリアだけ（切替はない）。エリアなしの一般は総務の扱い（入口の判定は別）
        $adminKeiri = User::factory()->create(['user_role' => 'admin', 'company_id' => $this->sanei->id, 'department_id' => $this->keiriDept->id]);
        $this->assertSame('keiri', $this->kindFor($adminKeiri, ['clerk_kind' => 'soumu']));
        // SuperAdmin は、画面の総務/経理の切替（セッション）。既定は総務
        $super = User::factory()->create(['user_role' => 'superadmin', 'company_id' => null]);
        $ctx = ['superadmin_context' => ['company_id' => $this->sanei->id]];
        $this->assertSame('soumu', $this->kindFor($super, $ctx + ['clerk_kind' => null]));
        $this->assertSame('keiri', $this->kindFor($super, $ctx + ['clerk_kind' => 'keiri']));
        $this->assertSame('soumu', $this->kindFor($super, $ctx + ['clerk_kind' => 'bogus']));
        // 未ログイン
        $this->assertSame('', $this->kindFor(null));
    }

    public function test_soumu_and_keiri_events_do_not_mix_and_cross_calendar_edits_are_404()
    {
        $soumu = $this->clerk($this->soumuDept);
        $keiri = $this->clerk($this->keiriDept);

        $soumuEventId = $this->addEvent($soumu, '総務の予定')->assertStatus(201)->json('id');
        $this->addEvent($keiri, '経理の予定')->assertStatus(201);

        $this->assertSame(['総務の予定'], $this->titles($soumu));
        $this->assertSame(['経理の予定'], $this->titles($keiri));
        $this->assertSame('soumu', ClerkEvent::find($soumuEventId)->calendar_kind);

        // 他のカレンダーの予定は、ID を知っていても編集・削除・完了できない（404）
        $this->actingAs($keiri)->putJson(route('clerk.calendar.events.update', ['event' => $soumuEventId]), ['title' => '乗っ取り', 'starts_at' => '2026-11-10', 'all_day' => true])->assertNotFound();
        $this->actingAs($keiri)->deleteJson(route('clerk.calendar.events.destroy', ['event' => $soumuEventId]))->assertNotFound();
        $this->actingAs($keiri)->patchJson(route('clerk.calendar.events.complete', ['event' => $soumuEventId]))->assertNotFound();
        $this->assertSame('総務の予定', ClerkEvent::find($soumuEventId)->title);

        // 同じカレンダーの人は、編集できる
        $second = $this->clerk($this->soumuDept);
        $this->actingAs($second)->putJson(route('clerk.calendar.events.update', ['event' => $soumuEventId]), ['title' => '更新後', 'starts_at' => '2026-11-10', 'all_day' => true])->assertOk();
    }

    public function test_superadmin_sees_the_calendar_selected_by_the_switch_and_admin_only_their_own_area()
    {
        $this->addEvent($this->clerk($this->soumuDept), '総務の予定');
        $this->addEvent($this->clerk($this->keiriDept), '経理の予定');
        $super = User::factory()->create(['user_role' => 'superadmin', 'company_id' => null]);
        $this->withSession(['superadmin_context' => ['company_id' => $this->sanei->id]]);

        $this->assertSame(['総務の予定'], $this->titles($super));
        $this->withSession(['clerk_kind' => 'keiri']);
        $this->assertSame(['経理の予定'], $this->titles($super));

        // 管理者は切替ができず、自分の部署のエリアのカレンダーだけ
        $adminSoumu = User::factory()->create(['user_role' => 'admin', 'company_id' => $this->sanei->id, 'department_id' => $this->soumuDept->id]);
        $this->assertSame(['総務の予定'], $this->titles($adminSoumu));
    }

    public function test_sunbrain_calendar_is_unchanged_company_wide_and_separate_from_sanei()
    {
        $sb1 = $this->sunbrainClerk();
        $sb2 = $this->sunbrainClerk();
        $id = $this->addEvent($sb1, 'SBの予定')->assertStatus(201)->json('id');

        $this->assertSame('', ClerkEvent::find($id)->calendar_kind);          // 従来どおり（空文字）
        $this->assertSame(['SBの予定'], $this->titles($sb2));                   // 同じ会社なら共有（従来の動作）
        $this->assertSame([], $this->titles($this->clerk($this->soumuDept)));   // サンエー印刷には見えない
        $this->actingAs($sb2)->deleteJson(route('clerk.calendar.events.destroy', ['event' => $id]))->assertSuccessful();
    }

    public function test_week_posts_are_separate_per_calendar()
    {
        $soumu = $this->clerk($this->soumuDept);
        $keiri = $this->clerk($this->keiriDept);
        $postId = $this->actingAs($soumu)->postJson(route('clerk.calendar.week_posts.store'), ['year' => 2026, 'week' => 46, 'body' => '総務の投稿'])->assertStatus(201)->json('id');

        $this->assertCount(1, $this->actingAs($soumu)->getJson(route('clerk.calendar.week_posts.index', ['year' => 2026, 'week' => 46]))->json());
        $this->assertCount(0, $this->actingAs($keiri)->getJson(route('clerk.calendar.week_posts.index', ['year' => 2026, 'week' => 46]))->json());
        $this->actingAs($keiri)->deleteJson(route('clerk.calendar.week_posts.destroy', ['post' => $postId]))->assertNotFound();
        $this->assertSame('soumu', ClerkWeekPost::find($postId)->calendar_kind);
    }

    public function test_color_names_are_per_calendar_and_the_sunbrain_unique_key_still_holds()
    {
        $soumu = $this->clerk($this->soumuDept);
        $keiri = $this->clerk($this->keiriDept);

        $this->actingAs($soumu)->getJson(route('clerk.calendar.colors.index'))->assertOk();
        $this->actingAs($keiri)->getJson(route('clerk.calendar.colors.index'))->assertOk();
        $this->actingAs($soumu)->patchJson(route('clerk.calendar.colors.update', ['colorKey' => 'orange']), ['label' => '給与'])->assertOk();

        $label = fn (string $kind) => ClerkCalendarColor::where('company_id', $this->sanei->id)->where('calendar_kind', $kind)->where('color_key', 'orange')->value('label');
        $this->assertSame('給与', $label('soumu'));
        $this->assertNull($label('keiri'));  // 経理の色の名前は、別（まだ付けていない）
        $this->assertSame(1, ClerkCalendarColor::where('company_id', $this->sanei->id)->where('calendar_kind', 'keiri')->where('color_key', 'orange')->count());

        // サン・ブレーン（空文字）は、同じ会社・同じ色の組み合わせで、重複できない（従来の制約と同じ）
        ClerkCalendarColor::create(['company_id' => $this->sunbrain->id, 'color_key' => 'orange', 'sort_order' => 0]);
        $this->expectException(\Illuminate\Database\QueryException::class);
        ClerkCalendarColor::create(['company_id' => $this->sunbrain->id, 'color_key' => 'orange', 'sort_order' => 0]);
    }

    public function test_schedule_rules_and_their_generated_events_follow_the_calendar()
    {
        $keiri = $this->clerk($this->keiriDept);
        $soumu = $this->clerk($this->soumuDept);

        $this->actingAs($keiri)->post(route('clerk.calendar.schedule_rules.store'), [
            'title' => '経理の月次処理', 'recurrence' => 'monthly_day', 'day_of_month' => 25, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'color_key' => 'blue', 'is_active' => true,
        ])->assertRedirect();

        $rule = ClerkScheduleRule::where('company_id', $this->sanei->id)->firstOrFail();
        $this->assertSame('keiri', $rule->calendar_kind);
        // 経理のルートで作ったルールは、経理のカレンダーに予定を生成する（総務には出ない）
        $this->assertContains('経理の月次処理', $this->titles($keiri));
        $this->assertNotContains('経理の月次処理', $this->titles($soumu));
        $this->assertSame('keiri', ClerkEvent::where('title', '経理の月次処理')->first()->calendar_kind);

        // ルールの一覧・編集は、同じカレンダーだけ
        $this->actingAs($soumu)->get(route('clerk.calendar.schedule_rules.index'))->assertOk()->assertInertia(fn ($p) => $p->where('rules', fn ($r) => count($r) === 0));
        $this->actingAs($soumu)->get(route('clerk.calendar.schedule_rules.edit', ['scheduleRule' => $rule->id]))->assertNotFound();
        $this->actingAs($soumu)->delete(route('clerk.calendar.schedule_rules.destroy', ['scheduleRule' => $rule->id]))->assertNotFound();
    }

    public function test_reminders_are_per_calendar_and_cross_calendar_access_is_404()
    {
        $soumu = $this->clerk($this->soumuDept);
        $keiri = $this->clerk($this->keiriDept);
        $this->actingAs($soumu)->post(route('clerk.reminders.store'), [
            'content' => '総務のお知らせ', 'color_key' => 'blue', 'target_type' => 'all', 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-30', 'is_active' => true,
        ])->assertRedirect(route('clerk.reminders.index'));

        $reminder = ClerkCalendarReminder::firstOrFail();
        $this->assertSame('soumu', $reminder->calendar_kind);
        $this->actingAs($soumu)->get(route('clerk.reminders.index'))->assertInertia(fn ($p) => $p->where('reminders', fn ($r) => count($r) === 1));
        $this->actingAs($keiri)->get(route('clerk.reminders.index'))->assertInertia(fn ($p) => $p->where('reminders', fn ($r) => count($r) === 0));
        $this->actingAs($keiri)->get(route('clerk.reminders.edit', ['reminder' => $reminder->id]))->assertNotFound();
        $this->actingAs($keiri)->delete(route('clerk.reminders.destroy', ['reminder' => $reminder->id]))->assertNotFound();
    }

    public function test_backfill_moves_only_general_company_rows_to_soumu_and_is_idempotent()
    {
        $make = fn (Company $c) => ClerkEvent::create(['company_id' => $c->id, 'user_id' => User::factory()->create(['company_id' => $c->id])->id, 'title' => 'x', 'starts_at' => '2026-11-10', 'all_day' => true]);
        $general = $make($this->sanei);
        $sunbrain = $make($this->sunbrain);
        $already = ClerkEvent::create(['company_id' => $this->sanei->id, 'user_id' => $general->user_id, 'title' => 'k', 'starts_at' => '2026-11-10', 'all_day' => true, 'calendar_kind' => 'keiri']);

        $this->assertSame(1, ClerkCalendarKindBackfill::run());
        $this->assertSame('soumu', $general->fresh()->calendar_kind);
        $this->assertSame('', $sunbrain->fresh()->calendar_kind);     // サン・ブレーンは触らない
        $this->assertSame('keiri', $already->fresh()->calendar_kind);  // すでに種類がある行は触らない
        $this->assertSame(0, ClerkCalendarKindBackfill::run());       // 2回目は、何もしない
    }
}
