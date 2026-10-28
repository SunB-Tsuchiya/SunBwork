<?php

namespace Tests\Unit;

use App\Models\Assignment;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\PersonalWorkSchedule;
use App\Services\WorkloadTrialAnalysis;
use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

/** Uses only a new SQLite :memory: connection; no application database or migrations. */
class WorkloadTrialRangeTest extends TestCase
{
    private Capsule $database;
    private Container $previousContainer;
    private mixed $previousFacade;
    private mixed $previousResolver;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacade = Facade::getFacadeApplication();
        $this->previousResolver = Model::getConnectionResolver();
        $container = new Container();
        Container::setInstance($container);
        $this->database = new Capsule($container);
        $this->database->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $this->database->bootEloquent();
        $container->instance('db', $this->database->getDatabaseManager());
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);
        Carbon::setTestNow(Carbon::parse('2026-10-30 10:00:00', 'Asia/Tokyo'));

        $schemas = [
            'users' => 'id integer primary key, name text, company_id integer, department_id integer, is_ghost integer default 0',
            'work_records' => 'id integer primary key, user_id integer, date text, start_time text, end_time text, scheduled_end text, overtime_minutes integer default 0',
            'user_monthly_schedules' => 'id integer primary key, user_id integer, year_month text, schedule text',
            'user_monthly_breaks' => 'id integer primary key, user_id integer, year_month text, schedule text',
            'worktypes' => 'id integer primary key, start_time text, end_time text',
            'personal_work_entries' => 'id integer primary key, user_id integer, company_id integer, department_id integer, work_date text, actual_minutes integer, work_category text, activity_type text, source_event_id integer, project_job_id integer, broad_project_id integer, plan_slot text, planned_start_time text, planned_end_time text, actual_quantity real, actual_unit text, title text, entry_visibility text',
            'events' => 'id integer primary key, user_id integer, starts_at text, ends_at text, project_job_assignment_id integer, event_item_type_id integer, is_materialized_copy integer default 0, source_schedule_event_id integer, is_company_event integer default 0, cancelled_at text, meeting_definition_id integer, visibility text',
            'schedule_attendees' => 'id integer primary key, event_id integer, user_id integer, status text',
            'event_item_types' => 'id integer primary key, slug text',
            'project_job_assignments' => 'id integer primary key, project_job_id integer, user_id integer, sender_id integer, job_type text, stage_id integer, work_item_type_id integer',
            'project_jobs' => 'id integer primary key, company_id integer, created_by_user_id integer, created_at text, user_id integer, show_on_department_schedule integer, title text',
            'project_job_coordinators' => 'project_job_id integer, user_id integer',
            'project_team_members' => 'id integer primary key, project_job_id integer, user_id integer',
        ];
        foreach ($schemas as $table => $columns) {
            $this->database->getConnection()->statement("create table $table ($columns)");
        }
        $this->insert('users', ['id' => 1, 'name' => 'Synthetic user', 'company_id' => 1, 'department_id' => 1]);
        $this->insert('worktypes', ['id' => 1, 'start_time' => '09:00', 'end_time' => '18:00']);
        $this->insert('event_item_types', ['id' => 1, 'slug' => 'work_schedule']);
        $this->user = new User(['name' => 'Synthetic user', 'company_id' => 1, 'department_id' => 1, 'employment_type' => 'regular']);
        $this->user->id = 1;
        $this->user->setRelation('assignment', new Assignment(['code' => 'operator']));
        $this->user->setRelation('userSetting', new UserSetting(['worktype_id' => 1]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->database->getConnection()->disconnect();
        if ($this->previousResolver) Model::setConnectionResolver($this->previousResolver);
        else Model::unsetConnectionResolver();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousFacade);
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    public function test_a_spanning_event_is_included_in_both_daily_and_weekly_analysis(): void
    {
        $this->insert('events', ['id' => 1, 'user_id' => 1, 'event_item_type_id' => 1,
            'starts_at' => '2026-10-27 23:30:00', 'ends_at' => '2026-10-29 00:30:00']);
        $daily = $this->analyze('2026-10-29', '2026-10-29');
        $weekly = $this->analyze('2026-10-25', '2026-10-31');
        self::assertSame(30, $daily['minutes']);
        $weeklyDay = collect($weekly['daily'])->firstWhere('date', '2026-10-29');
        self::assertSame($daily['minutes'], $weeklyDay['minutes']);
        self::assertSame($daily['base_points'], $weeklyDay['base_points']);
        $month = $this->analyze('2026-10-01', '2026-10-31');
        $separateDays = array_map(fn ($date) => $this->analyze($date, $date),
            ['2026-10-27', '2026-10-28', '2026-10-29']);
        self::assertSame($weekly['minutes'], array_sum(array_column($separateDays, 'minutes')));
        self::assertSame($weekly['minutes'], $month['minutes']);
        self::assertEqualsWithDelta($weekly['base_points'], array_sum(array_column($separateDays, 'base_points')), 0.1);
    }

    public function test_future_quick_work_is_excluded_and_today_is_cut_at_the_current_minute(): void
    {
        foreach (['2026-10-30', '2026-10-31'] as $index => $date) {
            $this->insert('personal_work_entries', ['id' => $index + 1, 'user_id' => 1, 'company_id' => 1,
                'department_id' => 1, 'work_date' => $date, 'actual_minutes' => 120, 'work_category' => 'dtp',
                'plan_slot' => 'hour', 'planned_start_time' => '09:00', 'planned_end_time' => '11:00']);
        }
        $today = $this->analyze('2026-10-30', '2026-10-30');
        $future = $this->analyze('2026-10-31', '2026-10-31');
        self::assertSame(60, $today['minutes']);
        self::assertSame(10.0, $today['points']);
        self::assertSame(0, $future['minutes']);
        self::assertSame(0.0, $future['points']);
    }

    public function test_saved_daily_break_is_applied_to_the_actual_analysis(): void
    {
        $this->insert('personal_work_entries', ['id' => 1, 'user_id' => 1, 'company_id' => 1,
            'department_id' => 1, 'work_date' => '2026-10-29', 'actual_minutes' => 90, 'work_category' => 'dtp',
            'plan_slot' => 'hour', 'planned_start_time' => '11:00', 'planned_end_time' => '12:30']);
        self::assertSame(60, $this->analyze('2026-10-29', '2026-10-29')['minutes']);
        $this->insert('user_monthly_breaks', ['id' => 1, 'user_id' => 1, 'year_month' => '2026-10',
            'schedule' => json_encode(['29' => ['start' => '12:30', 'end' => '13:30']])]);
        $changed = $this->analyze('2026-10-29', '2026-10-29');
        self::assertSame(90, $changed['minutes']);
        self::assertSame(15.0, $changed['base_points']);
    }

    private function analyze(string $from, string $to): array
    {
        return (new WorkloadTrialAnalysis(new PersonalWorkSchedule()))->analyze($this->user, collect([$this->user]),
            Carbon::parse($from, 'Asia/Tokyo')->startOfDay(), Carbon::parse($to, 'Asia/Tokyo')->endOfDay(), true, 'trial_time_v3')[0];
    }

    private function insert(string $table, array $row): void
    {
        $this->database->getConnection()->table($table)->insert($row);
    }
}
