<?php

namespace Tests\Feature;

use App\Http\Controllers\Coordinator\MGinbonCellController;
use App\Models\MGinbon\MGinbonItem;
use App\Models\ProjectJobAssignment;
use App\Models\User;
use App\Services\MGinbon\MGinbonAssignmentSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MGinbonAssignmentSyncServiceTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = tempnam(sys_get_temp_dir(), 'mginbon_test_');
        config()->set('database.connections.mginbon', [
            'driver' => 'sqlite',
            'database' => $this->databasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('mginbon');
        Artisan::call('migrate', [
            '--database' => 'mginbon',
            '--path' => 'database/migrations/mginbon',
            '--realpath' => false,
            '--force' => true,
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('mginbon');
        @unlink($this->databasePath);
        parent::tearDown();
    }

    public function test_completion_is_idempotent_and_reopen_restores_manual_date(): void
    {
        [$assignment, $ids] = $this->fixture('initial_text_proof');
        $db = DB::connection('mginbon');
        $db->table('mginbon_milestones')->insert([
            'mginbon_item_id' => $ids['item'],
            'mginbon_item_subject_id' => $ids['item_subject'],
            'code' => 'initial_text_proof_completed_on',
            'occurred_on' => '2026-01-10',
            'source' => 'manual',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $service = app(MGinbonAssignmentSyncService::class);
        $service->complete($assignment, 99, '2026-02-20');
        $service->complete($assignment, 99, '2026-02-21');

        $this->assertSame('completed', $db->table('mginbon_stage_tasks')->where('id', $ids['task'])->value('status'));
        $this->assertSame('2026-02-20', $db->table('mginbon_milestones')
            ->where('code', 'initial_text_proof_completed_on')->value('occurred_on'));
        $this->assertSame(1, $db->table('mginbon_change_logs')
            ->where('field_path', 'milestones.initial_text_proof_completed_on')
            ->where('source', 'assignment_sync')->count());

        $service->reopen($assignment, 99);

        $milestone = $db->table('mginbon_milestones')->where('code', 'initial_text_proof_completed_on')->first();
        $this->assertSame('2026-01-10', $milestone->occurred_on);
        $this->assertSame('manual', $milestone->source);
        $this->assertSame('assigned', $db->table('mginbon_stage_tasks')->where('id', $ids['task'])->value('status'));
    }

    public function test_release_removes_only_automatically_created_start_date(): void
    {
        [$assignment, $ids] = $this->fixture('reproof_scan_check');
        $db = DB::connection('mginbon');
        $service = app(MGinbonAssignmentSyncService::class);

        $service->start($assignment, '2026-03-05', 99);
        $this->assertSame('2026-03-05', $db->table('mginbon_milestones')
            ->where('code', 'reproof_scan_check_started_on')->value('occurred_on'));

        $service->release($assignment->id, 99);

        $this->assertFalse($db->table('mginbon_milestones')
            ->where('code', 'reproof_scan_check_started_on')->exists());
        $this->assertSame('not_started', $db->table('mginbon_stage_tasks')->where('id', $ids['task'])->value('status'));
        $this->assertNull($db->table('mginbon_stage_tasks')->where('id', $ids['task'])->value('project_job_assignment_id'));
        $this->assertSame('cancelled', $db->table('mginbon_work_packages')->where('id', $ids['package'])->value('status'));
    }

    #[DataProvider('completionMilestones')]
    public function test_each_completion_stage_records_its_milestone(string $stageCode, string $milestoneCode): void
    {
        [$assignment] = $this->fixture($stageCode);

        app(MGinbonAssignmentSyncService::class)->complete($assignment, 99, '2026-04-12');

        $this->assertSame('2026-04-12', DB::connection('mginbon')->table('mginbon_milestones')
            ->where('code', $milestoneCode)->value('occurred_on'));
    }

    public static function completionMilestones(): array
    {
        return [
            '文字入力' => ['text_input', 'text_input_completed_on'],
            '作図' => ['drawing', 'drawing_completed_on'],
            '初校校正' => ['initial_text_proof', 'initial_text_proof_completed_on'],
            '再校校正1' => ['reproof_scan_check', 'reproof_scan_check_completed_on'],
            '再校校正2' => ['reproof_text_proof', 'reproof_text_proof_completed_on'],
        ];
    }

    #[DataProvider('startMilestones')]
    public function test_each_proof_start_stage_records_its_milestone(string $stageCode, string $milestoneCode): void
    {
        [$assignment] = $this->fixture($stageCode);

        app(MGinbonAssignmentSyncService::class)->start($assignment, '2026-04-11', 99);

        $this->assertSame('2026-04-11', DB::connection('mginbon')->table('mginbon_milestones')
            ->where('code', $milestoneCode)->value('occurred_on'));
    }

    public static function startMilestones(): array
    {
        return [
            '初校校正' => ['initial_text_proof', 'initial_text_proof_started_on'],
            '再校校正1' => ['reproof_scan_check', 'reproof_scan_check_started_on'],
            '再校校正2' => ['reproof_text_proof', 'reproof_text_proof_started_on'],
        ];
    }

    public function test_date_cell_updates_every_requested_subject(): void
    {
        [, $ids] = $this->fixture('initial_operation');
        $db = DB::connection('mginbon');
        $now = now();
        $secondSubject = $db->table('mginbon_subjects')->insertGetId([
            'code' => 'math', 'name' => '算数', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $secondItemSubject = $db->table('mginbon_item_subjects')->insertGetId([
            'mginbon_item_id' => $ids['item'], 'mginbon_subject_id' => $secondSubject,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $updatedAt = (string) $db->table('mginbon_items')->where('id', $ids['item'])->value('updated_at');
        $request = Request::create('/mginbon/date-cell', 'PATCH', [
            'updated_at' => $updatedAt,
            'subject_ids' => [$ids['item_subject'], $secondItemSubject],
            'code' => 'manuscript_received_on',
            'date' => '2026-05-08',
        ]);
        $user = new User();
        $user->id = 99;
        $request->setUserResolver(fn () => $user);

        $response = app(MGinbonCellController::class)->updateDate(
            $request,
            MGinbonItem::query()->findOrFail($ids['item'])
        );

        $this->assertSame([$ids['item_subject'], $secondItemSubject], $response->getData(true)['subjectIds']);
        $this->assertSame(2, $db->table('mginbon_milestones')
            ->where('code', 'manuscript_received_on')->where('occurred_on', '2026-05-08')->count());
    }

    private function fixture(string $stageCode): array
    {
        $db = DB::connection('mginbon');
        $now = now();
        $project = $db->table('mginbon_projects')->insertGetId([
            'year' => 2099, 'name' => 'テスト銀本', 'status' => 'active',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $media = $db->table('mginbon_media_types')->insertGetId([
            'code' => 'problem', 'name' => '問題', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $subject = $db->table('mginbon_subjects')->insertGetId([
            'code' => 'japanese', 'name' => '国語', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $unit = $db->table('mginbon_production_units')->insertGetId([
            'mginbon_project_id' => $project, 'unit_type' => 'school', 'display_name' => 'テスト中学校',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $item = $db->table('mginbon_items')->insertGetId([
            'mginbon_production_unit_id' => $unit, 'mginbon_media_type_id' => $media,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $itemSubject = $db->table('mginbon_item_subjects')->insertGetId([
            'mginbon_item_id' => $item, 'mginbon_subject_id' => $subject,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $stage = $db->table('mginbon_stage_definitions')->insertGetId([
            'mginbon_project_id' => $project, 'code' => $stageCode, 'name' => 'テスト工程',
            'activity_type' => 'proof', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $assignment = new ProjectJobAssignment();
        $assignment->id = 7001;
        $assignment->source_assignment_id = null;
        $task = $db->table('mginbon_stage_tasks')->insertGetId([
            'mginbon_item_id' => $item, 'mginbon_item_subject_id' => $itemSubject,
            'mginbon_stage_definition_id' => $stage, 'status' => 'assigned',
            'project_job_assignment_id' => $assignment->id,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $package = $db->table('mginbon_work_packages')->insertGetId([
            'mginbon_project_id' => $project, 'mginbon_item_id' => $item,
            'mginbon_stage_definition_id' => $stage, 'user_id' => 99,
            'project_job_assignment_id' => $assignment->id, 'status' => 'assigned',
            'assigned_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $db->table('mginbon_work_package_tasks')->insert([
            'mginbon_work_package_id' => $package, 'mginbon_stage_task_id' => $task,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return [$assignment, [
            'project' => $project,
            'item' => $item,
            'item_subject' => $itemSubject,
            'task' => $task,
            'package' => $package,
        ]];
    }
}
