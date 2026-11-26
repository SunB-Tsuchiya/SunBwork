<?php

namespace Tests\Integration;

use App\Models\{Diary, User};
use App\Services\{DiaryWorkTimeline, PersonalWorkSchedule};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Session\{ArraySessionHandler, Store};
use Illuminate\Support\Facades\{Auth, DB, Schema};
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/UnifiedMyJobCreateTest.php';

/** Tests with SQLite :memory: only. Never resets or connects to a business database. */
class DiaryReviewTimelineTest extends UnifiedMyJobCreateTest
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! str_starts_with($this->name(), 'test_diary_')) return;
        Schema::create('diaries', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->date('date'); $t->text('content'); $t->json('read_by')->nullable(); $t->timestamps(); });
        Schema::create('diary_comments', function (Blueprint $t) { $t->id(); $t->integer('diary_id'); $t->integer('user_id'); $t->text('comment'); $t->timestamps(); });
        Schema::create('work_records', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->date('date'); $t->integer('worktype_id')->nullable(); $t->string('start_time')->nullable(); $t->string('end_time')->nullable(); $t->integer('overtime_minutes')->default(0); });
        foreach (['admin_permissions', 'leader_permissions'] as $table) Schema::create($table, function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->boolean('diary_management'); });
        Schema::create('teams', function (Blueprint $t) { $t->id(); $t->string('name')->nullable(); $t->string('team_type'); $t->integer('company_id'); $t->integer('department_id'); $t->integer('leader_id'); $t->boolean('can_read_diary'); });
        Schema::create('team_sub_leaders', function (Blueprint $t) { $t->integer('team_id'); $t->integer('user_id'); });
        Schema::create('units', function (Blueprint $t) { $t->id(); $t->string('name'); $t->integer('company_id'); $t->integer('department_id'); });
        Schema::create('unit_members', function (Blueprint $t) { $t->integer('unit_id'); $t->integer('user_id'); });
        Schema::create('diary_team_leaders', function (Blueprint $t) { $t->integer('diary_team_id'); $t->integer('user_id'); });
        Schema::create('diary_team_members', function (Blueprint $t) { $t->integer('diary_team_id'); $t->integer('user_id'); });
        DB::table('users')->where('id', 1)->update(['user_role' => 'admin']);
        DB::table('users')->where('id', 2)->update(['user_role' => 'user']);
        DB::table('diaries')->insert(['id' => 1, 'user_id' => 2, 'date' => '2026-11-26', 'content' => '<p>本人が記載した本文</p>', 'read_by' => '[]']);
    }

    private function entry(array $changes = []): int
    {
        return DB::table('personal_work_entries')->insertGetId($changes + [
            'user_id' => 2, 'company_id' => 1, 'department_id' => 1, 'work_date' => '2026-11-26',
            'work_category' => 'dtp', 'plan_slot' => 'hour', 'planned_start_time' => '08:30', 'planned_end_time' => '10:00',
            'entry_visibility' => 'department', 'title' => '対象者の新マイジョブ', 'actual_minutes' => 90,
            'actual_quantity' => 12, 'detail_note' => '表示に含めない内部メモ', 'result_note' => '非公開の結果',
        ]);
    }

    private function page(string $role = 'admin', ?int $context = null): array
    {
        DB::table('users')->where('id', 1)->update(['user_role' => $role]);
        $actor = User::findOrFail(1);
        Auth::setUser($actor);
        $request = Request::create('/synthetic/diary/1', 'GET');
        $request->headers->set('X-Inertia', 'true');
        $session = new Store('synthetic', new ArraySessionHandler(120));
        $session->start();
        if ($context !== null) $session->put('superadmin_context.company_id', $context);
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => $actor);
        app()->instance('request', $request);
        app()->instance('session', $session);
        $controller = $role === 'diary_manager'
            ? app(\App\Http\Controllers\DiaryManager\DiaryInteractionController::class)
            : app(\App\Http\Controllers\Diaries\DiaryInteractionController::class);
        return $controller->show(Diary::findOrFail(1))->toResponse($request)->getData(true)['props'];
    }

    public function test_diary_admin_reads_target_not_viewer_and_does_not_write_or_disclose_editor_metadata(): void
    {
        $id = $this->entry(); $this->entry(['user_id' => 1, 'title' => '閲覧者の作業']);
        DB::table('work_records')->insert(['user_id' => 2, 'date' => '2026-11-26', 'start_time' => '08:30', 'end_time' => '17:00']);
        $workRecordBefore = DB::table('work_records')->get()->toJson();
        $before = DB::table('personal_work_entries')->orderBy('id')->get()->toJson();
        $page = $this->page();
        self::assertCount(1, $page['diaryWorkEvents']);
        $event = $page['diaryWorkEvents'][0];
        self::assertSame('personal-work-'.$id, $event['id']);
        self::assertSame('対象者の新マイジョブ', $event['title']);
        self::assertSame('2026-11-26T08:30:00+09:00', $event['start']);
        self::assertSame('2026-11-26T10:00:00+09:00', $event['end']);
        self::assertTrue($event['readOnly']);
        foreach (['_work_entry', 'edit_version', 'result_note', 'actual_minutes', 'actual_quantity', 'file_info', 'detail_note'] as $key) self::assertArrayNotHasKey($key, $event);
        self::assertSame('<p>本人が記載した本文</p>', $page['diary']['content']);
        self::assertSame('[]', DB::table('diaries')->value('read_by'));
        self::assertSame($before, DB::table('personal_work_entries')->orderBy('id')->get()->toJson());
        self::assertSame(0, DB::table('job_notifications')->count());
        self::assertSame($workRecordBefore, DB::table('work_records')->get()->toJson());
        self::assertSame('08:30', $page['workRecord']['start_time']);
    }

    public function test_diary_leader_and_subleader_follow_readable_department_and_permission_flag(): void
    {
        $this->entry();
        DB::table('teams')->insert(['id' => 1, 'team_type' => 'department', 'company_id' => 1, 'department_id' => 1, 'leader_id' => 1, 'can_read_diary' => true]);
        self::assertCount(1, $this->page('leader')['diaryWorkEvents']);
        DB::table('teams')->where('id', 1)->update(['leader_id' => 99]);
        DB::table('team_sub_leaders')->insert(['team_id' => 1, 'user_id' => 1]);
        self::assertCount(1, $this->page('leader')['diaryWorkEvents']);
        DB::table('teams')->where('id', 1)->update(['can_read_diary' => false]);
        try { $this->page('leader'); self::fail('Unreadable team allowed'); } catch (HttpException $e) { self::assertSame(403, $e->getStatusCode()); }
    }

    public function test_diary_unit_leader_and_manager_only_read_registered_members(): void
    {
        $this->entry();
        DB::table('teams')->insert(['id' => 1, 'name' => '合成ユニット', 'team_type' => 'unit', 'company_id' => 1, 'department_id' => 1, 'leader_id' => 1, 'can_read_diary' => true]);
        DB::table('units')->insert(['id' => 1, 'name' => '合成ユニット', 'company_id' => 1, 'department_id' => 1]);
        DB::table('unit_members')->insert(['unit_id' => 1, 'user_id' => 2]);
        self::assertCount(1, $this->page('leader')['diaryWorkEvents']);
        DB::table('diary_team_leaders')->insert(['diary_team_id' => 1, 'user_id' => 1]);
        DB::table('diary_team_members')->insert(['diary_team_id' => 1, 'user_id' => 2]);
        self::assertCount(1, $this->page('diary_manager')['diaryWorkEvents']);
        DB::table('diary_team_members')->where('user_id', 2)->update(['user_id' => 999]);
        try { $this->page('diary_manager'); self::fail('Unlisted member allowed'); } catch (HttpException $e) { self::assertSame(403, $e->getStatusCode()); }
    }

    public function test_diary_disabled_admin_permission_and_other_company_are_rejected(): void
    {
        $this->entry();
        DB::table('admin_permissions')->insert(['user_id' => 1, 'diary_management' => false]);
        try { $this->page(); self::fail('Disabled permission accepted'); } catch (HttpException $e) { self::assertSame(403, $e->getStatusCode()); }
        DB::table('admin_permissions')->where('user_id', 1)->update(['diary_management' => true]);
        DB::table('users')->where('id', 2)->update(['company_id' => 2, 'department_id' => 3]);
        try { $this->page(); self::fail('Other company accepted'); } catch (HttpException $e) { self::assertSame(403, $e->getStatusCode()); }
    }

    public function test_diary_superadmin_context_does_not_expand_company_scope(): void
    {
        $this->entry();
        self::assertCount(1, $this->page('superadmin', 1)['diaryWorkEvents']);
        try { $this->page('superadmin', 2); self::fail('Other context accepted'); } catch (HttpException $e) { self::assertSame(403, $e->getStatusCode()); }
        self::assertCount(1, $this->page('superadmin')['diaryWorkEvents']);
    }

    public function test_diary_private_and_confidential_titles_are_redacted_but_owner_and_project_lead_keep_existing_access(): void
    {
        $private = $this->entry(['entry_visibility' => 'private']);
        DB::table('project_jobs')->insert(['id' => 10, 'company_id' => 1, 'user_id' => 99, 'title' => '機密案件', 'show_on_department_schedule' => false]);
        $secret = $this->entry(['project_job_id' => 10]);
        $events = $this->page()['diaryWorkEvents'];
        self::assertSame(['非公開の予定', '非公開の予定'], array_column($events, 'title'));
        $ownerEvents = app(DiaryWorkTimeline::class)->forDiary(User::findOrFail(2), Diary::findOrFail(1), [2], 1);
        self::assertStringContainsString('対象者の新マイジョブ', $ownerEvents[0]['title']);
        DB::table('project_job_coordinators')->insert(['project_job_id' => 10, 'user_id' => 1]);
        $events = $this->page()['diaryWorkEvents'];
        self::assertSame('非公開の予定', $events[0]['title']);
        self::assertStringContainsString('機密案件', $events[1]['title']);
    }

    public function test_diary_only_target_day_native_work_with_valid_related_scopes_is_returned(): void
    {
        $id = $this->entry();
        foreach ([['work_date' => '2026-11-25'], ['work_date' => '2026-11-27'], ['source_event_id' => 99], ['company_id' => 2], ['department_id' => 2], ['planned_end_time' => '08:00'], ['project_job_id' => 999], ['broad_project_id' => 2]] as $changes) $this->entry($changes);
        self::assertSame(['personal-work-'.$id], array_column($this->page()['diaryWorkEvents'], 'id'));
    }

    public function test_diary_global_superadmin_keeps_other_company_titles_hidden_and_noninfo_has_no_new_data(): void
    {
        DB::table('companies')->where('id', 2)->update(['company_type' => 'sunbrain']);
        DB::table('users')->where('id', 2)->update(['company_id' => 2, 'department_id' => 3]);
        $this->entry(['company_id' => 2, 'department_id' => 3]);
        $events = $this->page('superadmin')['diaryWorkEvents'];
        self::assertCount(1, $events);
        self::assertSame('非公開の予定', $events[0]['title']);
        DB::table('departments')->where('id', 3)->update(['code' => 'OTHER']);
        self::assertSame([], $this->page('superadmin')['diaryWorkEvents']);
    }

    public function test_diary_disabled_leader_permission_overrides_membership(): void
    {
        $this->entry();
        DB::table('teams')->insert(['id' => 1, 'team_type' => 'department', 'company_id' => 1, 'department_id' => 1, 'leader_id' => 1, 'can_read_diary' => true]);
        DB::table('leader_permissions')->insert(['user_id' => 1, 'diary_management' => false]);
        try { $this->page('leader'); self::fail('Disabled leader permission accepted'); } catch (HttpException $e) { self::assertSame(403, $e->getStatusCode()); }
    }

    public function test_diary_old_slot_uses_target_work_window_and_not_the_viewer(): void
    {
        $this->entry(['plan_slot' => 'am']);
        $schedule = new class extends PersonalWorkSchedule {
            public function workWindow(User $user, string $date): ?array {
                \PHPUnit\Framework\Assert::assertSame(2, $user->id);
                \PHPUnit\Framework\Assert::assertSame('2026-11-26', $date);
                return ['start' => 480, 'break_start' => 690, 'break_end' => 750, 'end' => 990];
            }
        };
        app()->instance(PersonalWorkSchedule::class, $schedule);
        $event = $this->page()['diaryWorkEvents'][0];
        self::assertSame('2026-11-26T08:00:00+09:00', $event['start']);
        self::assertSame('2026-11-26T11:30:00+09:00', $event['end']);
    }
}
