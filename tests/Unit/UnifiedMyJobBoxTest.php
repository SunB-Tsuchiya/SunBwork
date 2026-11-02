<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\PersonalWorkSchedule;
use App\Services\UnifiedMyJobBox;
use App\Services\UserWorkHub;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

class UnifiedMyJobBoxTest extends TestCase
{
    private Capsule $db;
    private mixed $previousFacade;
    private mixed $previousResolver;
    private UnifiedMyJobBox $box;

    protected function setUp(): void
    {
        $this->previousFacade = Facade::getFacadeApplication();
        $this->previousResolver = \Illuminate\Database\Eloquent\Model::getConnectionResolver();
        $container = new Container;
        $this->db = new Capsule($container);
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->db->bootEloquent();
        $container->instance('db', $this->db->getDatabaseManager());
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);
        $s = $this->db->getConnection()->getSchemaBuilder();
        $s->create('companies', function (Blueprint $t) { $t->id(); $t->string('company_type'); });
        $s->create('departments', function (Blueprint $t) { $t->id(); $t->integer('company_id'); $t->string('code'); });
        $s->create('project_jobs', function (Blueprint $t) { $t->id(); $t->string('title'); $t->integer('company_id')->nullable(); $t->integer('broad_project_id')->nullable(); });
        $s->create('broad_projects', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->integer('company_id'); $t->integer('department_id'); $t->integer('parent_id')->nullable(); $t->integer('fiscal_year')->nullable();
        });
        $s->create('project_job_assignments', function (Blueprint $t) {
            $t->id(); $t->integer('user_id'); $t->integer('sender_id')->nullable(); $t->integer('project_job_id');
            $t->integer('company_id')->nullable(); $t->integer('department_id')->nullable(); $t->string('title');
            $t->string('job_type')->nullable(); $t->boolean('completed')->default(false); $t->integer('progress_cell_id')->nullable();
            $t->integer('source_assignment_id')->nullable(); $t->integer('supersedes_assignment_id')->nullable(); $t->integer('coordinator_assignment_id')->nullable();
            $t->date('desired_end_date')->nullable(); $t->timestamps();
        });
        $s->create('workflow_cells', function (Blueprint $t) { $t->id(); $t->integer('assignment_id')->nullable(); });
        $s->create('events', function (Blueprint $t) {
            $t->id(); $t->integer('user_id'); $t->integer('project_job_assignment_id')->nullable(); $t->dateTime('starts_at'); $t->dateTime('ends_at');
        });
        $s->create('personal_work_entries', function (Blueprint $t) {
            $t->id(); $t->integer('user_id'); $t->integer('company_id'); $t->integer('department_id'); $t->date('work_date');
            $t->string('title'); $t->string('work_category'); $t->string('project_name_text')->nullable(); $t->integer('project_job_id')->nullable();
            $t->integer('broad_project_id')->nullable(); $t->integer('source_event_id')->nullable();
            $t->string('planned_start_time')->nullable(); $t->string('planned_end_time')->nullable();
            $t->decimal('actual_quantity')->nullable(); $t->string('actual_unit')->nullable();
        });
        $this->table('companies')->insert([['id' => 1, 'company_type' => 'sunbrain'], ['id' => 2, 'company_type' => 'general']]);
        $this->table('departments')->insert([['id' => 1, 'company_id' => 1, 'code' => 'INFO'], ['id' => 2, 'company_id' => 1, 'code' => 'OTHER'], ['id' => 3, 'company_id' => 2, 'code' => 'INFO']]);
        $this->table('project_jobs')->insert([['id' => 1, 'title' => '既存案件', 'company_id' => null], ['id' => 2, 'title' => '別会社秘密', 'company_id' => 2]]);
        $this->box = new UnifiedMyJobBox(new UserWorkHub(new PersonalWorkSchedule));
    }

    private function table(string $name) { return $this->db->getConnection()->table($name); }
    private function user(array $data = []): User { return (new User)->forceFill($data + ['id' => 1, 'user_role' => 'superadmin', 'company_id' => 1, 'department_id' => 1]); }
    private function assignment(int $id, array $data = []): void {
        $this->table('project_job_assignments')->insert($data + ['id' => $id, 'user_id' => 1, 'sender_id' => 1, 'project_job_id' => 1, 'title' => '同名作業', 'created_at' => '2026-10-01 09:00:00']);
    }
    private function event(int $id, ?int $assignment, string $start = '2026-11-02 09:00:00', string $end = '2026-11-02 10:00:00', int $user = 1): void {
        $this->table('events')->insert(['id' => $id, 'user_id' => $user, 'project_job_assignment_id' => $assignment, 'starts_at' => $start, 'ends_at' => $end]);
    }
    private function entry(int $id, array $data = []): void {
        $this->table('personal_work_entries')->insert($data + ['id' => $id, 'user_id' => 1, 'company_id' => 1, 'department_id' => 1,
            'work_date' => '2026-11-02', 'title' => '同名作業', 'work_category' => 'other', 'planned_start_time' => '09:00:00', 'planned_end_time' => '10:00:00']);
    }
    protected function tearDown(): void {
        Facade::clearResolvedInstances(); Facade::setFacadeApplication($this->previousFacade);
        $this->db->getConnection()->disconnect();
        if ($this->previousResolver) \Illuminate\Database\Eloquent\Model::setConnectionResolver($this->previousResolver);
        else \Illuminate\Database\Eloquent\Model::unsetConnectionResolver();
    }

    public function test_trial_requires_actual_info_superadmin_and_own_company_context(): void
    {
        self::assertTrue($this->box->available($this->user(), 1));
        self::assertFalse($this->box->available(null));
        foreach ([['user_role' => 'user'], ['user_role' => 'admin'], ['user_role' => 'coordinator'], ['user_role' => 'leader'], ['department_id' => 2], ['company_id' => 2, 'department_id' => 3], ['company_id' => 2]] as $data) {
            $user = $this->user($data);
            self::assertFalse($this->box->available($user));
            try { $this->box->rows($user); self::fail('Unauthorized listing'); } catch (AuthorizationException $e) { self::assertTrue(true); }
        }
        self::assertFalse($this->box->available($this->user(), 2));
        $this->expectException(AuthorizationException::class);
        $this->box->rows($this->user(), 2);
    }

    public function test_independent_other_and_unplanned_remain_distinct_and_read_only(): void
    {
        $this->assignment(1); $this->assignment(2, ['user_id' => 2]);
        $this->assignment(3, ['project_job_id' => 2]); $this->assignment(4, ['company_id' => 2]);
        $this->entry(1); $this->entry(2, ['source_event_id' => 20]);
        $this->entry(3, ['user_id' => 2]); $this->entry(4, ['department_id' => 2]);
        $this->event(20, null); // ordinary meeting is not a job
        $this->db->getConnection()->enableQueryLog();
        $rows = collect($this->box->rows($this->user()))->keyBy('key');
        self::assertSame(['assignment:1', 'entry:1'], $rows->keys()->all());
        self::assertSame('未予定', $rows['assignment:1']['status']);
        self::assertSame(['2026-10'], $rows['assignment:1']['months']);
        self::assertSame('登録済み', $rows['entry:1']['status']);
        foreach ($this->db->getConnection()->getQueryLog() as $query) self::assertMatchesRegularExpression('/^select /i', $query['query']);
    }

    public function test_multiple_dates_one_row_with_proof_utc_and_end_exclusive_months(): void
    {
        $this->assignment(1, ['job_type' => 'proof', 'coordinator_assignment_id' => 90]);
        $this->event(1, 1, '2026-10-31 15:00:00', '2026-10-31 16:00:00'); // Nov 1 JST
        $this->event(2, 1, '2026-11-30 14:30:00', '2026-11-30 15:00:00'); // ends Dec 1 midnight
        $this->event(3, 1, '2026-12-01 01:00:00', '2026-12-01 02:00:00', 2); // other's event
        $rows = $this->box->rows($this->user());
        self::assertCount(1, $rows); self::assertCount(2, $rows[0]['slots']);
        self::assertSame('2026-11-01', $rows[0]['slots'][0]['date']);
        self::assertSame('00:00', $rows[0]['slots'][0]['start']);
        self::assertSame(['2026-11'], $rows[0]['months']);
        self::assertSame('校正', $rows[0]['kind']);
    }

    public function test_replacement_merges_explicit_received_source_but_not_continuation_or_proof(): void
    {
        $this->assignment(1, ['sender_id' => null]);
        $this->assignment(2, ['supersedes_assignment_id' => 1]);
        $this->assignment(3, ['source_assignment_id' => 2]);
        $this->assignment(4, ['job_type' => 'proof', 'coordinator_assignment_id' => 1]);
        $this->assignment(5, ['sender_id' => 7]); $this->assignment(6, ['sender_id' => 7]);
        $this->event(1, 1); $this->event(2, 2); $this->event(3, 4); $this->event(4, 5);
        $rows = collect($this->box->rows($this->user()))->keyBy('key');
        self::assertSame(['assignment:2', 'assignment:3', 'assignment:4', 'assignment:5'], $rows->keys()->all());
        self::assertCount(2, $rows['assignment:2']['slots']);
        self::assertSame('依頼から登録', $rows['assignment:2']['kind']);
        self::assertNull($rows['assignment:5']['assignmentId']);
        self::assertSame(4, $rows['assignment:4']['assignmentId']);
    }

    public function test_ambiguous_replacements_and_table_work_remain_independent(): void
    {
        $this->assignment(1, ['sender_id' => 7]);
        $this->assignment(2, ['supersedes_assignment_id' => 1]);
        $this->assignment(3, ['supersedes_assignment_id' => 1]);
        $this->assignment(4, ['progress_cell_id' => 9]);
        $this->assignment(5);
        $this->table('workflow_cells')->insert(['assignment_id' => 5]);
        $this->event(1, 1);
        $rows = collect($this->box->rows($this->user()))->keyBy('key');
        self::assertCount(5, $rows);
        self::assertSame('進行表', $rows['assignment:4']['kind']);
        self::assertSame('管理表', $rows['assignment:5']['kind']);
        self::assertSame('依頼ジョブ', $rows['assignment:1']['kind']);
    }

    public function test_filter_pagination_search_and_unplanned_fallback_do_not_drop_records(): void
    {
        for ($id = 1; $id <= 55; $id++) $this->entry($id);
        $this->assignment(1, ['desired_end_date' => '2026-11-20', 'completed' => true]);
        $this->assignment(2); $this->event(1, 2, '2026-11-30 23:30:00', '2026-12-01 00:30:00');
        $list = $this->box->listing($this->user(), 1, ['period' => '2026-11']);
        $focused = $this->box->listing($this->user(), 1, ['period' => '2026-11', 'created_entry' => 1]);
        self::assertSame('entry:1', $focused['createdKey']);
        self::assertContains('entry:1', array_column($focused['rows'], 'key'));
        self::assertNull($this->box->listing($this->user(), 1, ['period' => '2026-11', 'created_entry' => 999])['createdKey']);
        self::assertSame(57, $list['pagination']['total']); self::assertCount(50, $list['rows']);
        self::assertSame(7, count($this->box->listing($this->user(), 1, ['period' => '2026-11', 'page' => 2])['rows']));
        self::assertSame(56, $this->box->listing($this->user(), 1, ['period' => 'all', 'hide_completed' => true])['pagination']['total']);
        self::assertSame(1, $this->box->listing($this->user(), 1, ['period' => '2026-12', 'q' => '既存案件'])['pagination']['total']);
        self::assertSame(0, $this->box->listing($this->user(), 1, ['period' => 'all', 'q' => '存在しない'])['pagination']['total']);
    }
}
