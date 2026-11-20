<?php

namespace Tests\Integration;

use App\Console\Commands\AutoCompleteMyJobs;
use App\Models\{ProgressCell, ProjectJobAssignment, User};
use App\Services\{LegacyReceivedJobCompletion, ProgressStageCompletion, WorkJobMutation, WorkJobReader};
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Artisan, DB, Schema};
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/UnifiedMyJobCreateTest.php';

/** SQLite memory only. No business database, resets or production fixtures. */
class ProgressStageConfirmationTest extends UnifiedMyJobCreateTest
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function stageFixture(): User
    {
        DB::table('project_jobs')->insert(['id' => 10, 'title' => '合成工程', 'company_id' => 1, 'user_id' => 2]);
        Schema::table('project_job_assignments', fn (Blueprint $t) => $t->dateTime('scheduled_at')->nullable());
        foreach ([10, 11] as $id) DB::table('project_job_assignments')->insert([
            'id' => $id, 'project_job_id' => 10, 'user_id' => 1, 'sender_id' => 1,
            'company_id' => 1, 'department_id' => 1, 'title' => '合成作業',
            'source_assignment_id' => $id === 11 ? 10 : null,
            'scheduled_at' => $id === 10 ? '2026-11-19 09:00:00' : '2026-11-21 09:00:00',
        ]);
        DB::table('progress_cells')->insert(['id' => 1, 'assignment_id' => 10, 'value_user_id' => 1]);
        DB::table('project_schedules')->insert(['id' => 1, 'project_job_id' => 10]);
        DB::table('project_job_items')->insert(['progress_sheet_id' => 10, 'type' => 'row', 'row_id' => 10, 'linked_schedule_id' => 1]);
        DB::table('users')->where('id', 1)->update(['user_role' => 'user']);
        return User::findOrFail(1);
    }

    public function test_stage_daily_closure_does_not_confirm_but_manual_confirmation_updates_progress_once(): void
    {
        $user = $this->stageFixture(); Carbon::setTestNow(Carbon::parse('2026-11-20 00:05:00', 'Asia/Tokyo'));
        self::assertSame(':memory:', DB::connection()->getDatabaseName());
        self::assertSame(0, Artisan::call(AutoCompleteMyJobs::class));
        self::assertTrue((bool) ProjectJobAssignment::findOrFail(10)->completed);
        self::assertFalse((bool) ProjectJobAssignment::findOrFail(11)->completed);
        self::assertNull(ProgressCell::findOrFail(1)->completed_at);
        self::assertSame(0, DB::table('project_schedules')->value('progress'));
        self::assertSame(0, DB::table('job_notifications')->count());
        $reader = app(WorkJobReader::class);
        self::assertTrue($reader->series($user, 10, null, false, 'assignment')['stagePending']);
        $result = app(LegacyReceivedJobCompletion::class)->tryComplete($user, 10);
        self::assertTrue($result['changed']);
        self::assertNotNull(ProgressCell::findOrFail(1)->completed_at);
        self::assertSame(100, DB::table('project_schedules')->value('progress'));
        self::assertSame(1, DB::table('job_notifications')->where('type', 'progress_completed')->count());
        self::assertFalse((bool) ProjectJobAssignment::findOrFail(11)->completed);
        self::assertFalse($reader->series($user, 10, null, false, 'assignment')['stagePending']);
        $stamp = DB::table('progress_cells')->value('completed_at');
        $scheduleStamp = DB::table('project_schedules')->value('completed_at');
        Carbon::setTestNow(Carbon::parse('2026-11-20 01:05:00', 'Asia/Tokyo'));
        self::assertFalse(app(LegacyReceivedJobCompletion::class)->tryComplete($user, 10)['changed']);
        self::assertSame($stamp, DB::table('progress_cells')->value('completed_at'));
        self::assertSame($scheduleStamp, DB::table('project_schedules')->value('completed_at'));
        self::assertSame(1, DB::table('job_notifications')->count());
    }

    public function test_stage_already_closed_new_continuation_can_confirm_old_stage_without_touching_future_sibling(): void
    {
        $user = $this->stageFixture();
        $id = DB::table('personal_work_entries')->insertGetId([
            'user_id' => 1, 'company_id' => 1, 'department_id' => 1, 'work_date' => '2026-11-19',
            'work_category' => 'other', 'plan_slot' => 'hour', 'planned_start_time' => '09:00', 'planned_end_time' => '10:00',
            'entry_visibility' => 'department', 'actual_minutes' => 60, 'actual_quantity' => 12,
            'completed_at' => '2026-11-20 00:05:00', 'source_assignment_id' => 10,
        ]);
        DB::table('project_job_assignments')->where('id', 10)->update(['completed' => true]);
        $reader = app(WorkJobReader::class);
        self::assertTrue($reader->series($user, $id, null, false)['stagePending']);
        self::assertTrue(app(WorkJobMutation::class)->completeEntry($user, $id, null)['changed']);
        self::assertSame('2026-11-20 00:05:00', DB::table('personal_work_entries')->value('completed_at'));
        self::assertSame(60, DB::table('personal_work_entries')->value('actual_minutes'));
        self::assertEquals(12, DB::table('personal_work_entries')->value('actual_quantity'));
        self::assertFalse((bool) ProjectJobAssignment::findOrFail(11)->completed);
        self::assertFalse(app(WorkJobMutation::class)->completeEntry($user, $id, null)['changed']);
        self::assertSame(1, DB::table('job_notifications')->count());
    }

    public function test_stage_foreign_project_cell_rolls_back_assignment_and_notifications(): void
    {
        $user = $this->stageFixture();
        DB::table('progress_sheets')->where('id', 10)->update(['project_job_id' => 999]);
        try { app(LegacyReceivedJobCompletion::class)->tryComplete($user, 10); self::fail('Foreign stage accepted'); }
        catch (ValidationException $e) { self::assertArrayHasKey('source', $e->errors()); }
        self::assertFalse((bool) ProjectJobAssignment::findOrFail(10)->completed);
        self::assertNull(ProgressCell::findOrFail(1)->completed_at);
        self::assertSame(0, DB::table('job_notifications')->count());
    }

    public function test_stage_user_cell_route_is_idempotent_and_reopen_recalculates_link(): void
    {
        $user = $this->stageFixture();
        $service = app(ProgressStageCompletion::class);
        $first = $service->confirmCell($user, ProgressCell::findOrFail(1));
        $second = $service->confirmCell($user, ProgressCell::findOrFail(1));
        self::assertEquals($first->completed_at, $second->completed_at);
        self::assertSame(1, DB::table('job_notifications')->count());
        DB::transaction(fn () => $service->reopen(ProjectJobAssignment::findOrFail(10)));
        self::assertNull(ProgressCell::findOrFail(1)->completed_at);
        self::assertSame(0, DB::table('project_schedules')->value('progress'));
        self::assertNull(DB::table('project_schedules')->value('completed_at'));
        $service->confirmCell($user, ProgressCell::findOrFail(1));
        self::assertSame(2, DB::table('job_notifications')->count());
    }

    public function test_stage_generic_proof_closure_is_not_proof_confirmation(): void
    {
        $this->stageFixture();
        DB::table('progress_sheets')->where('id', 10)->update(['column_config' => json_encode([['key' => 'worker', 'type' => 'proof_v2']])]);
        DB::table('project_job_assignments')->where('id', 10)->update(['completed' => true, 'job_type' => 'proof']);
        self::assertFalse(ProgressStageCompletion::confirmed(ProgressCell::findOrFail(1)));
        DB::table('project_job_assignments')->where('id', 10)->update(['proof_completed_at' => '2026-11-20 09:00:00']);
        self::assertTrue(ProgressStageCompletion::confirmed(ProgressCell::findOrFail(1)));
        self::assertFalse(ProgressStageCompletion::confirmed(ProgressCell::findOrFail(1), 'worker'));
    }

    public function test_stage_direct_requested_job_confirms_once_without_importing_or_touching_future_jobs(): void
    {
        $user = $this->stageFixture();
        DB::table('project_job_assignments')->where('id', 10)->update(['sender_id' => null, 'completed' => true]);
        self::assertTrue(app(LegacyReceivedJobCompletion::class)->tryComplete($user, 10)['changed']);
        self::assertFalse(app(LegacyReceivedJobCompletion::class)->tryComplete($user, 10)['changed']);
        self::assertSame(1, DB::table('job_notifications')->count());
        self::assertSame(100, DB::table('project_schedules')->value('progress'));
        self::assertSame(2, DB::table('project_job_assignments')->count());
        self::assertFalse((bool) ProjectJobAssignment::findOrFail(11)->completed);
    }

    public function test_stage_user_route_rejects_other_owner_and_linked_schedule_of_another_project(): void
    {
        $user = $this->stageFixture();
        $request = \Illuminate\Http\Request::create('/synthetic', 'POST');
        $request->setUserResolver(fn () => $user);
        DB::table('progress_cells')->where('id', 1)->update(['value_user_id' => 2]);
        try { app(\App\Http\Controllers\User\ProgressCellController::class)->complete($request, ProgressCell::findOrFail(1)); self::fail('Other owner accepted'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { self::assertSame(403, $e->getStatusCode()); }
        DB::table('progress_cells')->where('id', 1)->update(['value_user_id' => 1]);
        DB::table('project_schedules')->where('id', 1)->update(['project_job_id' => 999]);
        try { app(LegacyReceivedJobCompletion::class)->tryComplete($user, 10); self::fail('Foreign linked schedule accepted'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { self::assertSame(422, $e->getStatusCode()); }
        self::assertNull(ProgressCell::findOrFail(1)->completed_at);
        self::assertFalse((bool) ProjectJobAssignment::findOrFail(10)->completed);
        self::assertSame(0, DB::table('job_notifications')->count());
    }

    public function test_stage_completing_later_child_does_not_repeat_already_confirmed_stage_notice(): void
    {
        $user = $this->stageFixture();
        app(LegacyReceivedJobCompletion::class)->tryComplete($user, 10);
        self::assertSame(1, DB::table('job_notifications')->count());
        app(LegacyReceivedJobCompletion::class)->tryComplete($user, 11);
        self::assertSame(1, DB::table('job_notifications')->count());
        self::assertTrue((bool) ProjectJobAssignment::findOrFail(11)->completed);
    }

    public function test_stage_explicit_proof_completion_updates_proof_only_and_its_linked_progress(): void
    {
        $user = $this->stageFixture();
        DB::table('progress_sheets')->where('id', 10)->update(['column_config' => json_encode([['key' => 'worker', 'type' => 'worker'], ['key' => 'proof', 'type' => 'proof_v2']])]);
        DB::table('project_job_assignments')->insert(['id' => 20, 'project_job_id' => 10, 'user_id' => 1, 'sender_id' => 2, 'job_type' => 'proof']);
        DB::table('progress_cells')->insert(['id' => 2, 'assignment_id' => 10, 'proof_assignment_id' => 20, 'col_key' => 'proof', 'value_user_id' => 1]);
        DB::table('proof_requests')->insert(['id' => 1, 'project_job_id' => 10, 'project_job_assignment_id' => 10, 'proofreader_id' => 1, 'proof_coordinator_id' => 2, 'requester_id' => 2, 'title' => '合成校正', 'status' => 'in_progress']);
        self::assertTrue(app(\App\Services\ProofRequestCompletion::class)->completeRequest($user, 1));
        self::assertNull(ProgressCell::findOrFail(1)->completed_at);
        self::assertNotNull(ProgressCell::findOrFail(2)->completed_at);
        self::assertFalse((bool) ProjectJobAssignment::findOrFail(10)->completed);
        self::assertSame(50, DB::table('project_schedules')->value('progress'));
        self::assertSame(1, DB::table('job_notifications')->where('type', 'proof_completed')->count());
        self::assertSame(0, DB::table('job_notifications')->where('type', 'progress_completed')->count());
        self::assertFalse(app(\App\Services\ProofRequestCompletion::class)->completeRequest($user, 1));
        self::assertSame(1, DB::table('job_notifications')->count());
    }

    public function test_stage_print_projection_does_not_confuse_generic_closure_with_dedicated_proof(): void
    {
        $this->stageFixture();
        DB::table('project_job_assignments')->where('id', 10)->update(['completed' => true]);
        $method = new \ReflectionMethod(\App\Http\Controllers\Coordinator\ProgressSheetController::class, 'buildPrintCells');
        $controller = app(\App\Http\Controllers\Coordinator\ProgressSheetController::class);
        $row = $method->invoke($controller, [10])->first();
        self::assertTrue((bool) $row['assignment_completed']);
        self::assertFalse($row['assignment_proof_completed']);
        self::assertNull($row['completed_at']);
        DB::table('project_job_assignments')->where('id', 10)->update(['proof_completed_at' => '2026-11-20 09:00:00']);
        self::assertTrue($method->invoke($controller, [10])->first()['assignment_proof_completed']);
        self::assertNull(ProgressCell::findOrFail(1)->completed_at);
        self::assertSame(0, DB::table('job_notifications')->count());
    }

    public function test_stage_notification_failure_rolls_back_cell_and_linked_progress(): void
    {
        $user = $this->stageFixture();
        DB::statement("CREATE TRIGGER fail_stage_notice BEFORE INSERT ON job_notifications BEGIN SELECT RAISE(ABORT, 'synthetic notification failure'); END");
        try { app(LegacyReceivedJobCompletion::class)->tryComplete($user, 10); self::fail('Failure swallowed'); }
        catch (\Illuminate\Database\QueryException $e) { self::assertStringContainsString('synthetic notification failure', $e->getMessage()); }
        self::assertFalse((bool) ProjectJobAssignment::findOrFail(10)->completed);
        self::assertNull(ProgressCell::findOrFail(1)->completed_at);
        self::assertSame(0, DB::table('project_schedules')->value('progress'));
        self::assertSame(0, DB::table('job_notifications')->count());
    }
}
