<?php

namespace Tests\Feature\TeamRoom;

use App\Models\ClerkCalendarHoliday;
use App\Models\Company;
use App\Models\Team;
use App\Models\TeamCalendarColor;
use App\Models\TeamEvent;
use App\Models\User;
use App\Support\CalendarColors;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** チームルームのカレンダー: 色設定（チームごと）・予定の色・完了（作成者のみ）・祝日（会社ごと） */
class TeamCalendarTest extends TestCase
{
    use RefreshDatabase;

    private Company $sb;
    private Company $sanei;
    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sb = Company::create(['name' => '株式会社サン・ブレーン', 'code' => 'SB' . random_int(100, 999), 'company_type' => 'sunbrain', 'active' => true]);
        $this->sanei = Company::create(['name' => '株式会社サンエー印刷', 'code' => 'SA' . random_int(100, 999), 'company_type' => 'general', 'active' => true]);
        $this->team = $this->team($this->sb, 'チームA');
    }

    private function team(Company $c, string $name): Team
    {
        return Team::create(['name' => $name, 'personal_team' => false, 'company_id' => $c->id, 'team_type' => 'unit', 'user_id' => User::factory()->create(['company_id' => $c->id])->id]);
    }

    private function member(Team $team, string $role = 'user'): User
    {
        $u = User::factory()->create(['user_role' => $role, 'company_id' => $team->company_id]);
        DB::table('team_user')->insert(['team_id' => $team->id, 'user_id' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
        return $u;
    }

    private function event(User $u, array $over = []): TeamEvent
    {
        return TeamEvent::create(array_merge(['team_id' => $this->team->id, 'user_id' => $u->id, 'title' => '定例', 'starts_at' => '2026-11-05', 'ends_at' => '2026-11-05', 'all_day' => true], $over));
    }

    // ── 色設定（チームごと・メンバー全員が変更できる）─────────────

    public function test_色設定は初回に11色が作られ_メンバー全員が名前と並び順を変えられ_最後に変えた人が残る(): void
    {
        $a = $this->member($this->team); $b = $this->member($this->team);
        $r = $this->actingAs($a)->getJson(route('team-rooms.calendar.colors.index', ['team' => $this->team->id]))->assertOk();
        $this->assertCount(11, $r->json('colors'));
        $this->assertSame('indigo', $r->json('colors.0.color_key'));
        $this->assertNull($r->json('last_changed'));

        $this->actingAs($a)->patchJson(route('team-rooms.calendar.colors.update', ['team' => $this->team->id, 'colorKey' => 'blue']), ['label' => '会議'])
            ->assertOk()->assertJsonPath('last_changed.name', $a->name);
        // 別のメンバーも変更できる
        $this->actingAs($b)->postJson(route('team-rooms.calendar.colors.reorder', ['team' => $this->team->id]), ['orders' => [['color_key' => 'gray', 'sort_order' => 0], ['color_key' => 'indigo', 'sort_order' => 10]]])
            ->assertOk()->assertJsonPath('last_changed.name', $b->name);

        $r = $this->actingAs($a)->getJson(route('team-rooms.calendar.colors.index', ['team' => $this->team->id]))->assertOk();
        $this->assertSame('gray', $r->json('colors.0.color_key'));
        $this->assertSame('会議', collect($r->json('colors'))->firstWhere('color_key', 'blue')['label']);
    }

    public function test_色設定は_チームごとに独立し_メンバー以外は見られず_変えられない(): void
    {
        $other = $this->team($this->sb, 'チームB');
        $a = $this->member($this->team); $b = $this->member($other);
        $this->actingAs($a)->patchJson(route('team-rooms.calendar.colors.update', ['team' => $this->team->id, 'colorKey' => 'red']), ['label' => '休み'])->assertOk();

        $r = $this->actingAs($b)->getJson(route('team-rooms.calendar.colors.index', ['team' => $other->id]))->assertOk();
        $this->assertNull(collect($r->json('colors'))->firstWhere('color_key', 'red')['label']);   // 別のチームには、影響しない

        $this->actingAs($b)->getJson(route('team-rooms.calendar.colors.index', ['team' => $this->team->id]))->assertForbidden();
        $this->actingAs($b)->patchJson(route('team-rooms.calendar.colors.update', ['team' => $this->team->id, 'colorKey' => 'red']), ['label' => 'x'])->assertForbidden();
        $this->actingAs($a)->patchJson(route('team-rooms.calendar.colors.update', ['team' => $this->team->id, 'colorKey' => 'nothing']), ['label' => 'x'])->assertNotFound();
    }

    // ── 予定の色・完了 ──────────────────────────

    public function test_予定の色を保存でき_色を送らなければ_初期色のまま_不正な色は拒否される(): void
    {
        $a = $this->member($this->team);
        $body = ['title' => '打合せ', 'starts_at' => '2026-11-05', 'ends_at' => '2026-11-05', 'all_day' => true];
        $id = $this->actingAs($a)->postJson(route('team-rooms.events.store', ['team' => $this->team->id]), $body + ['color_key' => 'green'])->assertCreated()->json('id');
        $this->assertSame('green', TeamEvent::find($id)->color_key);

        $id2 = $this->actingAs($a)->postJson(route('team-rooms.events.store', ['team' => $this->team->id]), $body)->assertCreated()->json('id');
        $this->assertSame(CalendarColors::DEFAULT_KEY, TeamEvent::find($id2)->color_key);

        $this->actingAs($a)->postJson(route('team-rooms.events.store', ['team' => $this->team->id]), $body + ['color_key' => 'magenta'])->assertStatus(422);

        // 更新で色を変え、色を送らなければ、今の色のまま
        $this->actingAs($a)->putJson(route('team-rooms.events.update', ['team' => $this->team->id, 'event' => $id]), $body + ['color_key' => 'red'])->assertOk();
        $this->assertSame('red', TeamEvent::find($id)->color_key);
        $this->actingAs($a)->putJson(route('team-rooms.events.update', ['team' => $this->team->id, 'event' => $id]), $body)->assertOk();
        $this->assertSame('red', TeamEvent::find($id)->color_key);
    }

    public function test_完了チェックは_作成者とSuperAdminだけ_ほかのメンバーは押せない_一覧に反映される(): void
    {
        $a = $this->member($this->team); $b = $this->member($this->team);
        $super = User::factory()->create(['user_role' => 'superadmin', 'company_id' => $this->sb->id]);
        $e = $this->event($a);

        $this->actingAs($b)->patchJson(route('team-rooms.events.complete', ['team' => $this->team->id, 'event' => $e->id]))->assertForbidden();
        $this->assertNull($e->fresh()->completed_at);

        $this->actingAs($a)->patchJson(route('team-rooms.events.complete', ['team' => $this->team->id, 'event' => $e->id]))->assertOk()->assertJson(['completed' => true]);
        $this->assertNotNull($e->fresh()->completed_at);

        // 一覧: 完了と、押せるか（can_complete）が、人によって違う
        $mine = collect($this->actingAs($a)->getJson(route('team-rooms.events.index', ['team' => $this->team->id]))->json())->firstWhere('id', $e->id);
        $theirs = collect($this->actingAs($b)->getJson(route('team-rooms.events.index', ['team' => $this->team->id]))->json())->firstWhere('id', $e->id);
        $this->assertSame([true, true, 'indigo'], [$mine['completed'], $mine['can_complete'], $mine['color_key']]);
        $this->assertSame([true, false], [$theirs['completed'], $theirs['can_complete']]);

        // SuperAdmin は、取り消せる
        $this->actingAs($super)->patchJson(route('team-rooms.events.complete', ['team' => $this->team->id, 'event' => $e->id]))->assertOk()->assertJson(['completed' => false]);
    }

    // ── 祝日（会社の休日カレンダーの読み取り）─────────────────

    public function test_祝日は_チームの会社の休日カレンダーが出る_他社の祝日は出ず_DBは書き換えない(): void
    {
        $a = $this->member($this->team);
        ClerkCalendarHoliday::create(['company_id' => $this->sb->id, 'date' => '2026-11-03', 'name' => '文化の日']);
        ClerkCalendarHoliday::create(['company_id' => $this->sb->id, 'date' => '2026-12-26', 'name' => '会社の休み']);
        ClerkCalendarHoliday::create(['company_id' => $this->sanei->id, 'date' => '2026-11-10', 'name' => '他社の休み']);
        DB::table('clerk_calendar_years')->insert(['company_id' => $this->sb->id, 'year' => 2026, 'initialized_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $before = DB::table('clerk_calendar_years')->count();

        $r = $this->actingAs($a)->getJson(route('team-rooms.calendar.holidays', ['team' => $this->team->id, 'year' => 2026]))->assertOk();
        $names = collect($r->json())->pluck('name', 'date');
        $this->assertSame('文化の日', $names['2026-11-03']);
        $this->assertSame('会社の休み', $names['2026-12-26']);
        $this->assertFalse($names->has('2026-11-10'));          // 他社の休日は出ない
        $this->assertTrue($names->has('2025-11-03') || $names->has('2027-01-01'));   // 未設定の年（前後）は、元の祝日データで補う
        $this->assertSame($before, DB::table('clerk_calendar_years')->count());     // 閲覧だけでは、DB を書き換えない
    }

    public function test_祝日はメンバー以外は取得できない_会社の無いチームは空(): void
    {
        $b = $this->member($this->team($this->sanei, 'チームC'));   // 別の会社の、別のチームのメンバー
        $this->actingAs($b)->getJson(route('team-rooms.calendar.holidays', ['team' => $this->team->id]))->assertForbidden();

        $noCompany = Team::create(['name' => '会社なし', 'personal_team' => false, 'team_type' => 'unit', 'user_id' => $b->id]);
        DB::table('team_user')->insert(['team_id' => $noCompany->id, 'user_id' => $b->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($b)->getJson(route('team-rooms.calendar.holidays', ['team' => $noCompany->id]))->assertOk()->assertExactJson([]);
    }
}
