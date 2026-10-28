<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClerkCalendarHoliday;
use App\Models\Company;
use App\Models\ProjectJob;
use App\Models\ProjectSchedule;
use App\Models\ProjectTeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 案件のカレンダー: 色設定（案件ごと・案件を編集できる人だけ変更）・予定の種類の色・祝日（会社ごと） */
class ProjectCalendarTest extends TestCase
{
    use RefreshDatabase;

    private Company $sb;
    private Company $sanei;
    private ProjectJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sb = Company::create(['name' => '株式会社サン・ブレーン', 'code' => 'SB' . random_int(100, 999), 'company_type' => 'sunbrain', 'active' => true]);
        $this->sanei = Company::create(['name' => '株式会社サンエー印刷', 'code' => 'SA' . random_int(100, 999), 'company_type' => 'general', 'active' => true]);
        $this->job = $this->job($this->sb, 'JOB-A');
    }

    private function user(string $role, ?Company $c = null): User
    {
        return User::factory()->create(['user_role' => $role, 'company_id' => ($c ?? $this->sb)->id]);
    }

    private function job(Company $c, string $code): ProjectJob
    {
        $client = Client::create(['name' => '取引先' . $code]);
        return ProjectJob::create(['jobcode' => $code, 'title' => '案件' . $code, 'user_id' => $this->user('coordinator', $c)->id, 'client_id' => $client->id, 'company_id' => $c->id]);
    }

    private function colorsUrl(?ProjectJob $j = null): string
    {
        return route('project_jobs.calendar.colors.index', ['projectJob' => ($j ?? $this->job)->id]);
    }

    private function patchUrl(string $key, ?ProjectJob $j = null): string
    {
        return route('project_jobs.calendar.colors.update', ['projectJob' => ($j ?? $this->job)->id, 'colorKey' => $key]);
    }

    // ── 色設定 ───────────────────────────────────

    public function test_案件を編集できる人は_色設定を見て変えられ_最後に変えた人が残る(): void
    {
        foreach (['coordinator', 'leader', 'admin', 'clerk'] as $role) {
            $u = $this->user($role);
            $r = $this->actingAs($u)->getJson($this->colorsUrl())->assertOk();
            $this->assertTrue($r->json('can_edit'), "{$role} は変更できる");
            $this->assertCount(11, $r->json('colors'));
        }
        $coord = $this->user('coordinator');
        $this->actingAs($coord)->patchJson($this->patchUrl('blue'), ['label' => '校了'])->assertOk()->assertJsonPath('last_changed.name', $coord->name);
        $this->actingAs($coord)->postJson(route('project_jobs.calendar.colors.reorder', ['projectJob' => $this->job->id]), ['orders' => [['color_key' => 'gray', 'sort_order' => 0], ['color_key' => 'indigo', 'sort_order' => 10]]])->assertOk();

        $r = $this->actingAs($coord)->getJson($this->colorsUrl())->assertOk();
        $this->assertSame('gray', $r->json('colors.0.color_key'));
        $this->assertSame('校了', collect($r->json('colors'))->firstWhere('color_key', 'blue')['label']);
    }

    public function test_案件に関わる人は_見るだけ_変更はできず_無関係の人と他社の担当者は見られない(): void
    {
        $member = $this->user('user');
        ProjectTeamMember::create(['project_job_id' => $this->job->id, 'user_id' => $member->id]);
        $r = $this->actingAs($member)->getJson($this->colorsUrl())->assertOk();
        $this->assertFalse($r->json('can_edit'));
        $this->actingAs($member)->patchJson($this->patchUrl('red'), ['label' => 'x'])->assertForbidden();
        $this->actingAs($member)->postJson(route('project_jobs.calendar.colors.reorder', ['projectJob' => $this->job->id]), ['orders' => [['color_key' => 'red', 'sort_order' => 0]]])->assertForbidden();

        $stranger = $this->user('user');
        $this->actingAs($stranger)->getJson($this->colorsUrl())->assertForbidden();
        $otherCompanyCoordinator = $this->user('coordinator', $this->sanei);
        $this->actingAs($otherCompanyCoordinator)->getJson($this->colorsUrl())->assertForbidden();
        $this->actingAs($otherCompanyCoordinator)->patchJson($this->patchUrl('red'), ['label' => 'x'])->assertForbidden();

        $this->actingAs($this->user('superadmin', $this->sanei))->getJson($this->colorsUrl())->assertOk()->assertJsonPath('can_edit', true);
    }

    public function test_色設定は案件ごとに独立する(): void
    {
        $other = $this->job($this->sb, 'JOB-B');
        $coord = $this->user('coordinator');
        $this->actingAs($coord)->patchJson($this->patchUrl('red'), ['label' => '急ぎ'])->assertOk();
        $r = $this->actingAs($coord)->getJson($this->colorsUrl($other))->assertOk();
        $this->assertNull(collect($r->json('colors'))->firstWhere('color_key', 'red')['label']);
        $this->actingAs($coord)->patchJson($this->patchUrl('nothing'), ['label' => 'x'])->assertNotFound();
    }

    // ── 祝日 ─────────────────────────────────────

    public function test_祝日は案件の会社の休日カレンダーが出る_関係の無い人は取得できない(): void
    {
        ClerkCalendarHoliday::create(['company_id' => $this->sb->id, 'date' => '2026-11-03', 'name' => '文化の日']);
        ClerkCalendarHoliday::create(['company_id' => $this->sanei->id, 'date' => '2026-11-10', 'name' => '他社の休み']);
        $coord = $this->user('coordinator');
        $names = collect($this->actingAs($coord)->getJson(route('project_jobs.calendar.holidays', ['projectJob' => $this->job->id, 'year' => 2026]))->assertOk()->json())->pluck('name', 'date');
        $this->assertSame('文化の日', $names['2026-11-03']);
        $this->assertFalse($names->has('2026-11-10'));

        $this->actingAs($this->user('user'))->getJson(route('project_jobs.calendar.holidays', ['projectJob' => $this->job->id]))->assertForbidden();
    }

    // ── 予定の種類の色（color_key）────────────────

    public function test_予定の種類の色を保存でき_色を送らない更新では変わらず_空で解除でき_不正な色は拒否される(): void
    {
        $coord = $this->user('coordinator');
        $id = $this->actingAs($coord)->postJson(route('coordinator.project_schedules.store'), [
            'project_job_id' => $this->job->id, 'name' => '初校', 'start_date' => '2026-11-05', 'end_date' => '2026-11-06', 'color_key' => 'green',
        ])->assertOk()->json('schedule.id');
        $this->assertSame('green', ProjectSchedule::find($id)->color_key);

        $this->actingAs($coord)->postJson(route('coordinator.project_schedules.store'), [
            'project_job_id' => $this->job->id, 'name' => '再校', 'start_date' => '2026-11-05', 'color_key' => 'magenta',
        ])->assertStatus(422);

        // ドラッグ移動など、色を送らない更新では、今の色のまま
        $this->actingAs($coord)->patchJson(route('coordinator.project_schedules.update', ['project_schedule' => $id]), ['start_date' => '2026-11-07', 'end_date' => '2026-11-08'])
            ->assertOk()->assertJsonPath('schedule.color_key', 'green');
        $this->assertSame('green', ProjectSchedule::find($id)->color_key);

        // 変更と、解除（空）
        $this->actingAs($coord)->patchJson(route('coordinator.project_schedules.update', ['project_schedule' => $id]), ['color_key' => 'red'])->assertOk()->assertJsonPath('schedule.color_key', 'red');
        $this->actingAs($coord)->patchJson(route('coordinator.project_schedules.update', ['project_schedule' => $id]), ['color_key' => null])->assertOk()->assertJsonPath('schedule.color_key', null);
        $this->assertNull(ProjectSchedule::find($id)->color_key);
    }
}
