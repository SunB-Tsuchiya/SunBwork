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
            'departments' => 'id integer primary key, company_id integer, code text',
            'work_records' => 'id integer primary key, user_id integer, date text, start_time text, end_time text, scheduled_end text, overtime_minutes integer default 0',
            'user_monthly_schedules' => 'id integer primary key, user_id integer, year_month text, schedule text',
            'user_monthly_breaks' => 'id integer primary key, user_id integer, year_month text, schedule text',
            'worktypes' => 'id integer primary key, start_time text, end_time text',
            'personal_work_entries' => 'id integer primary key, user_id integer, company_id integer, department_id integer, work_date text, actual_minutes integer, work_category text, activity_type text, source_event_id integer, project_job_id integer, broad_project_id integer, plan_slot text, planned_start_time text, planned_end_time text, actual_quantity real, actual_unit text, title text, entry_visibility text',
            'events' => 'id integer primary key, user_id integer, starts_at text, ends_at text, project_job_assignment_id integer, event_item_type_id integer, is_materialized_copy integer default 0, source_schedule_event_id integer, is_company_event integer default 0, cancelled_at text, meeting_definition_id integer, visibility text',
            'schedule_attendees' => 'id integer primary key, event_id integer, user_id integer, status text',
            'event_item_types' => 'id integer primary key, slug text, name text, coefficient real, description text, sort_order integer, created_at text, updated_at text',
            'project_job_assignments' => 'id integer primary key, project_job_id integer, user_id integer, sender_id integer, job_type text, stage_id integer, work_item_type_id integer, completed integer default 0',
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

    public function test_calendar_jobs_count_without_extra_result_entries_and_overlap_with_quick_work_once(): void
    {
        foreach ([1 => 1, 2 => 2] as $id => $sender) {
            $this->insert('project_job_assignments', ['id' => $id, 'user_id' => 1, 'sender_id' => $sender, 'job_type' => 'work']);
            $this->insert('events', ['id' => $id, 'user_id' => 1, 'project_job_assignment_id' => $id,
                'starts_at' => '2026-10-29 '.($id === 1 ? '09:00:00' : '10:00:00'),
                'ends_at' => '2026-10-29 '.($id === 1 ? '10:00:00' : '11:00:00')]);
        }
        $jobsOnly = $this->analyze('2026-10-29', '2026-10-29');
        self::assertSame(120, $jobsOnly['minutes']);
        self::assertSame(['my_job' => 1, 'assigned_job' => 1], $jobsOnly['source_counts']);
        self::assertSame(20.0, $jobsOnly['base_points']);
        $this->insert('personal_work_entries', ['id' => 1, 'user_id' => 1, 'company_id' => 1,
            'department_id' => 1, 'work_date' => '2026-10-29', 'actual_minutes' => 60, 'work_category' => 'dtp',
            'plan_slot' => 'hour', 'planned_start_time' => '09:30', 'planned_end_time' => '10:30']);
        $withQuick = $this->analyze('2026-10-29', '2026-10-29');
        self::assertSame(120, $withQuick['minutes']);
        self::assertSame(20.0, $withQuick['base_points']);
        self::assertSame(3, $withQuick['records']);
    }

    public function test_linked_result_replaces_its_event_and_cancellation_excludes_both(): void
    {
        $this->insert('project_job_assignments', ['id' => 1, 'user_id' => 1, 'sender_id' => 1, 'job_type' => 'work']);
        $this->insert('events', ['id' => 1, 'user_id' => 1, 'project_job_assignment_id' => 1,
            'starts_at' => '2026-10-29 09:00:00', 'ends_at' => '2026-10-29 10:00:00']);
        $this->insert('personal_work_entries', ['id' => 1, 'user_id' => 1, 'company_id' => 1,
            'department_id' => 1, 'work_date' => '2026-10-29', 'actual_minutes' => 60, 'work_category' => 'dtp',
            'source_event_id' => 1]);
        $result = $this->analyze('2026-10-29', '2026-10-29');
        self::assertSame(60, $result['minutes']);
        self::assertSame(1, $result['records']);
        self::assertSame(10.0, $result['points']);
        $this->database->getConnection()->table('events')->where('id', 1)->update(['cancelled_at' => '2026-10-29 11:00:00']);
        $cancelled = $this->analyze('2026-10-29', '2026-10-29');
        self::assertSame(0, $cancelled['minutes']);
        self::assertSame(0, $cancelled['records']);
        self::assertSame(0.0, $cancelled['points']);
    }

    public function test_personal_meeting_copy_does_not_add_the_original_invitation_twice(): void
    {
        $this->insert('users', ['id' => 2, 'name' => 'Synthetic organizer', 'company_id' => 1, 'department_id' => 1]);
        $this->insert('event_item_types', ['id' => 2, 'slug' => 'meeting_internal']);
        $this->insert('events', ['id' => 1, 'user_id' => 2, 'event_item_type_id' => 2, 'is_company_event' => 1,
            'starts_at' => '2026-10-29 09:00:00', 'ends_at' => '2026-10-29 10:00:00']);
        $this->insert('schedule_attendees', ['id' => 1, 'event_id' => 1, 'user_id' => 1, 'status' => 'accepted']);
        $this->insert('events', ['id' => 2, 'user_id' => 1, 'event_item_type_id' => 2,
            'source_schedule_event_id' => 1, 'is_materialized_copy' => 1,
            'starts_at' => '2026-10-29 09:00:00', 'ends_at' => '2026-10-29 10:00:00']);
        $result = $this->analyze('2026-10-29', '2026-10-29');
        self::assertSame(60, $result['minutes']);
        self::assertSame(1, $result['records']);
        self::assertSame(8.0, $result['meeting_points']);
    }

    public function test_existing_other_is_business_at_ninety_percent_and_private_work_also_counts(): void
    {
        $this->insert('event_item_types', ['id' => 2, 'slug' => 'other']);
        $this->insert('events', ['id' => 1, 'user_id' => 1, 'event_item_type_id' => 2, 'is_company_event' => 1,
            'starts_at' => '2026-10-29 09:00:00', 'ends_at' => '2026-10-29 10:00:00']);
        $this->insert('events', ['id' => 2, 'user_id' => 1, 'event_item_type_id' => 1, 'visibility' => 'private',
            'starts_at' => '2026-10-29 10:00:00', 'ends_at' => '2026-10-29 11:00:00']);
        $result = $this->analyze('2026-10-29', '2026-10-29');
        self::assertSame(120, $result['minutes']);
        self::assertSame(2, $result['records']);
        self::assertSame(19.0, $result['work_points']);
        self::assertSame(0.0, $result['meeting_points']);
    }

    public function test_private_business_other_and_linked_record_use_the_same_ninety_percent_rate(): void
    {
        $this->insert('event_item_types', ['id' => 2, 'slug' => 'other']);
        $this->insert('events', ['id' => 1, 'user_id' => 1, 'event_item_type_id' => 2, 'visibility' => 'private',
            'starts_at' => '2026-09-29 09:00:00', 'ends_at' => '2026-09-29 10:00:00']);
        $existing = $this->analyze('2026-09-29', '2026-09-29');
        self::assertSame(60, $existing['minutes']);
        self::assertSame(9.0, $existing['points']);
        self::assertSame(0.9, $existing['activities'][0]['coefficient']);
        $this->insert('personal_work_entries', ['id' => 1, 'user_id' => 1, 'company_id' => 1,
            'department_id' => 1, 'work_date' => '2026-09-29', 'actual_minutes' => 60,
            'work_category' => 'shinko', 'source_event_id' => 1]);
        $linked = $this->analyze('2026-09-29', '2026-09-29');
        self::assertSame(9.0, $linked['points']);
        self::assertSame(1, $linked['records']);
        $this->database->getConnection()->table('events')->where('id', 1)->update(['cancelled_at' => '2026-09-29 11:00:00']);
        self::assertSame(0.0, $this->analyze('2026-09-29', '2026-09-29')['points']);
    }

    public function test_personal_other_and_linked_record_do_not_create_time_days_or_overtime_points(): void
    {
        $this->insert('event_item_types', ['id' => 2, 'slug' => 'personal_other']);
        $this->insert('events', ['id' => 1, 'user_id' => 1, 'event_item_type_id' => 2, 'is_company_event' => 1,
            'starts_at' => '2026-10-29 18:00:00', 'ends_at' => '2026-10-29 19:00:00']);
        $this->insert('personal_work_entries', ['id' => 1, 'user_id' => 1, 'company_id' => 1,
            'department_id' => 1, 'work_date' => '2026-10-29', 'actual_minutes' => 60,
            'work_category' => 'shinko', 'source_event_id' => 1]);
        $this->insert('work_records', ['id' => 1, 'user_id' => 1, 'date' => '2026-10-29',
            'start_time' => '09:00', 'end_time' => '19:00', 'scheduled_end' => '18:00', 'overtime_minutes' => 60]);
        foreach (['trial_time_v2', 'trial_time_v3'] as $version) {
            $result = $this->analyze('2026-10-29', '2026-10-29', $version);
            self::assertSame(0, $result['minutes']);
            self::assertSame(0, $result['recorded_days']);
            self::assertSame(0.0, $result['points']);
            self::assertSame(0.0, $result['overtime_points']);
        }
    }

    public function test_business_other_overlaps_a_job_without_adding_the_time_twice(): void
    {
        $this->insert('event_item_types', ['id' => 2, 'slug' => 'other']);
        $this->insert('events', ['id' => 1, 'user_id' => 1, 'event_item_type_id' => 2,
            'starts_at' => '2026-10-29 09:00:00', 'ends_at' => '2026-10-29 11:00:00']);
        $this->insert('project_job_assignments', ['id' => 1, 'user_id' => 1, 'sender_id' => 1, 'job_type' => 'work']);
        $this->insert('events', ['id' => 2, 'user_id' => 1, 'project_job_assignment_id' => 1,
            'starts_at' => '2026-10-29 10:00:00', 'ends_at' => '2026-10-29 11:00:00']);
        $result = $this->analyze('2026-10-29', '2026-10-29');
        self::assertSame(120, $result['minutes']);
        self::assertSame(18.5, $result['points']);
    }

    public function test_other_split_migration_keeps_existing_event_ids_and_the_legacy_coefficient(): void
    {
        $this->insert('event_item_types', ['id' => 2, 'slug' => 'other', 'name' => 'そのほか', 'coefficient' => 1.2]);
        $this->insert('events', ['id' => 1, 'user_id' => 1, 'event_item_type_id' => 2,
            'starts_at' => '2026-09-29 09:00:00', 'ends_at' => '2026-09-29 10:00:00']);
        $migration = require __DIR__.'/../../database/migrations/2026_10_29_160001_split_other_schedule_business_and_personal.php';
        $migration->up();
        $migration->up();
        $types = $this->database->getConnection()->table('event_item_types');
        $other = (clone $types)->where('slug', 'other')->first();
        self::assertSame(2, $other->id);
        self::assertSame('そのほか（業務）', $other->name);
        self::assertEquals(1.2, $other->coefficient);
        self::assertSame(1, (clone $types)->where('slug', 'personal_other')->count());
        self::assertEquals(0.0, (clone $types)->where('slug', 'personal_other')->value('coefficient'));
        self::assertSame(2, $this->database->getConnection()->table('events')->where('id', 1)->value('event_item_type_id'));
        self::assertSame(9.0, $this->analyze('2026-09-29', '2026-09-29')['points']);
    }

    public function test_november_invitation_needs_acceptance_and_complete_employee_attendance(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 10:00:00', 'Asia/Tokyo'));
        $this->invitation('2026-11-01');
        self::assertSame(0, $this->analyze('2026-11-01', '2026-11-01')['minutes']);
        $this->insert('work_records', ['id' => 1, 'user_id' => 1, 'date' => '2026-11-01', 'start_time' => '08:00']);
        self::assertSame(0, $this->analyze('2026-11-01', '2026-11-01')['minutes']);
        $this->database->getConnection()->table('work_records')->where('id', 1)->update(['end_time' => '18:00']);
        self::assertSame(60, $this->analyze('2026-11-01', '2026-11-01')['minutes']);
        foreach (['pending', 'declined'] as $status) {
            $this->database->getConnection()->table('schedule_attendees')->where('id', 1)->update(['status' => $status]);
            self::assertSame(0, $this->analyze('2026-11-01', '2026-11-01')['minutes']);
        }
    }

    public function test_linked_invited_meeting_entry_cannot_bypass_employee_attendance(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 10:00:00', 'Asia/Tokyo'));
        $this->invitation('2026-11-01');
        $this->insert('personal_work_entries', ['id' => 1, 'user_id' => 1, 'company_id' => 1,
            'department_id' => 1, 'work_date' => '2026-11-01', 'actual_minutes' => 60,
            'work_category' => 'shinko', 'activity_type' => 'meeting', 'source_event_id' => 1]);
        self::assertSame(0, $this->analyze('2026-11-01', '2026-11-01')['minutes']);
        $this->insert('work_records', ['id' => 1, 'user_id' => 1, 'date' => '2026-11-01',
            'start_time' => '08:00', 'end_time' => '18:00']);
        self::assertSame(60, $this->analyze('2026-11-01', '2026-11-01')['minutes']);
        $this->database->getConnection()->table('schedule_attendees')->where('id', 1)->update(['status' => 'declined']);
        self::assertSame(0, $this->analyze('2026-11-01', '2026-11-01')['minutes']);
    }

    public function test_rescheduled_personal_copy_uses_original_acceptance_outside_the_analysis_range(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-06 10:00:00', 'Asia/Tokyo'));
        $this->invitation('2026-11-01');
        $this->insert('events', ['id' => 2, 'user_id' => 1, 'event_item_type_id' => 2,
            'source_schedule_event_id' => 1, 'is_materialized_copy' => 1,
            'starts_at' => '2026-11-05 09:00:00', 'ends_at' => '2026-11-05 10:00:00']);
        $this->insert('work_records', ['id' => 1, 'user_id' => 1, 'date' => '2026-11-05',
            'start_time' => '08:00', 'end_time' => '18:00']);
        self::assertSame(60, $this->analyze('2026-11-05', '2026-11-05')['minutes']);
    }

    public function test_non_employee_invitation_needs_acceptance_but_not_attendance_and_october_is_preserved(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 10:00:00', 'Asia/Tokyo'));
        $this->invitation('2026-11-01');
        $this->user->employment_type = 'dispatch';
        self::assertSame(60, $this->analyze('2026-11-01', '2026-11-01')['minutes']);
        $this->database->getConnection()->table('schedule_attendees')->where('id', 1)->update(['status' => 'pending']);
        self::assertSame(0, $this->analyze('2026-11-01', '2026-11-01')['minutes']);
        $this->database->getConnection()->table('events')->where('id', 1)->update([
            'starts_at' => '2026-10-31 09:00:00', 'ends_at' => '2026-10-31 10:00:00']);
        $this->user->employment_type = 'regular';
        self::assertSame(60, $this->analyze('2026-10-31', '2026-10-31')['minutes']);
    }

    public function test_overtime_bonus_is_capped_for_each_day_before_monthly_aggregation(): void
    {
        foreach (['2026-10-28', '2026-10-29'] as $index => $date) {
            $this->insert('events', ['id' => $index + 1, 'user_id' => 1, 'event_item_type_id' => 1,
                'starts_at' => "$date 18:00:00", 'ends_at' => "$date 21:00:00"]);
            $this->insert('work_records', ['id' => $index + 1, 'user_id' => 1, 'date' => $date,
                'start_time' => '09:00', 'end_time' => '21:00', 'scheduled_end' => '18:00', 'overtime_minutes' => 180]);
        }
        $day = $this->analyze('2026-10-29', '2026-10-29');
        $month = $this->analyze('2026-10-01', '2026-10-31');
        self::assertSame(30.0, $day['base_points']);
        self::assertSame(10.0, $day['overtime_points']);
        self::assertSame(40.0, $day['points']);
        self::assertSame(60.0, $month['base_points']);
        self::assertSame(20.0, $month['overtime_points']);
        self::assertSame(80.0, $month['points']);
        self::assertSame(['2026-10' => 360], $day['monthly_overtime_minutes']);
        self::assertSame($day['monthly_overtime_minutes'], $month['monthly_overtime_minutes']);
    }

    public function test_v3_project_creation_and_team_activity_do_not_add_automatic_points(): void
    {
        $this->insert('project_jobs', ['id' => 1, 'company_id' => 1, 'user_id' => 1,
            'created_by_user_id' => 1, 'created_at' => '2026-10-29 08:00:00', 'show_on_department_schedule' => 1]);
        $this->insert('project_job_assignments', ['id' => 1, 'project_job_id' => 1,
            'user_id' => 1, 'sender_id' => 1, 'job_type' => 'work']);
        $this->insert('events', ['id' => 1, 'user_id' => 1, 'project_job_assignment_id' => 1,
            'starts_at' => '2026-10-29 09:00:00', 'ends_at' => '2026-10-29 10:00:00']);
        $result = $this->analyze('2026-10-29', '2026-10-29');
        self::assertSame(1, $result['created_projects']);
        self::assertSame(1, $result['active_managed_projects']);
        self::assertSame(0.0, $result['creation_points']);
        self::assertSame(0.0, $result['management_points']);
        self::assertSame(10.0, $result['points']);
    }

    public function test_daily_schedule_contains_the_same_spanning_event_as_analysis(): void
    {
        $this->insert('events', ['id' => 1, 'user_id' => 1, 'event_item_type_id' => 1,
            'starts_at' => '2026-10-27 23:30:00', 'ends_at' => '2026-10-29 00:30:00']);
        $schedule = (new PersonalWorkSchedule())->eventsForUsers([1], '2026-10-29');
        self::assertCount(1, $schedule);
        self::assertSame(1, $schedule->first()->id);
        self::assertSame(30, $this->analyze('2026-10-29', '2026-10-29')['minutes']);
    }

    public function test_trial_access_requires_superadmin_and_matching_info_company_and_department(): void
    {
        $this->insert('departments', ['id' => 1, 'company_id' => 1, 'code' => 'INFO']);
        $this->insert('departments', ['id' => 2, 'company_id' => 1, 'code' => 'OTHER']);
        $schedule = new PersonalWorkSchedule();
        $this->user->user_role = 'superadmin';
        self::assertTrue($schedule->available($this->user));
        $this->user->company_id = 2;
        self::assertFalse($schedule->available($this->user));
        $this->user->company_id = 1;
        $this->user->department_id = 2;
        self::assertFalse($schedule->available($this->user));
        $this->user->department_id = 1;
        foreach (['admin', 'coordinator', 'user'] as $role) {
            $this->user->user_role = $role;
            self::assertFalse($schedule->available($this->user));
        }
    }

    public function test_daily_schedule_keeps_the_existing_one_hour_fallback_for_missing_end_time(): void
    {
        $this->insert('events', ['id' => 1, 'user_id' => 1, 'event_item_type_id' => 1,
            'starts_at' => '2026-10-29 09:00:00']);
        self::assertCount(1, (new PersonalWorkSchedule())->eventsForUsers([1], '2026-10-29'));
        self::assertCount(0, (new PersonalWorkSchedule())->eventsForUsers([1], '2026-10-30'));
    }

    private function invitation(string $date): void
    {
        $this->insert('users', ['id' => 2, 'name' => 'Synthetic organizer', 'company_id' => 1, 'department_id' => 1]);
        $this->insert('event_item_types', ['id' => 2, 'slug' => 'meeting_internal']);
        $this->insert('events', ['id' => 1, 'user_id' => 2, 'event_item_type_id' => 2, 'is_company_event' => 1,
            'starts_at' => "$date 09:00:00", 'ends_at' => "$date 10:00:00"]);
        $this->insert('schedule_attendees', ['id' => 1, 'event_id' => 1, 'user_id' => 1, 'status' => 'accepted']);
    }

    public function test_management_api_matches_existing_v3_calculation_for_the_same_cohort(): void
    {
        $db=$this->database->getConnection();
        foreach (["user_role text", "assignment_id integer", "sort_order integer default 0", "employment_type text"] as $column) $db->statement("alter table users add column $column");
        foreach (["name text", "sort_order integer default 0"] as $column) $db->statement("alter table departments add column $column");
        foreach ([
            'companies'=>'id integer primary key, name text, sort_order integer',
            'assignments'=>'id integer primary key, code text, name text',
            'user_settings'=>'id integer primary key, user_id integer, worktype_id integer',
            'user_employment_settings'=>'id integer primary key, user_id integer, ranking_included integer, workload_tracked integer',
        ] as $table=>$columns) $db->statement("create table $table ($columns)");
        $this->insert('companies',['id'=>1,'name'=>'Synthetic company','sort_order'=>1]);
        $this->insert('departments',['id'=>1,'company_id'=>1,'code'=>'INFO','name'=>'Synthetic department']);
        $this->insert('assignments',['id'=>1,'code'=>'operator','name'=>'Operator']);
        $this->insert('user_settings',['id'=>1,'user_id'=>1,'worktype_id'=>1]);
        $db->table('users')->where('id',1)->update(['user_role'=>'superadmin','assignment_id'=>1,'employment_type'=>'regular']);
        $this->user->user_role='superadmin';
        $this->user->setRelation('adminPermission',null)->setRelation('leaderPermission',null);
        $this->insert('events',['id'=>1,'user_id'=>1,'event_item_type_id'=>1,'starts_at'=>'2026-09-01 09:00:00','ends_at'=>'2026-09-01 10:00:00']);
        $this->insert('events',['id'=>2,'user_id'=>1,'event_item_type_id'=>1,'starts_at'=>'2026-09-01 09:30:00','ends_at'=>'2026-09-01 10:30:00']);
        $expected=$this->analyze('2026-09-01','2026-09-30');
        $request=ManagementRangeRequest::create('/superadmin/workload-analyzer/analysis','GET',['date'=>'2026-09-01','period'=>'month','user_id'=>1]);
        $request->setUserResolver(fn ()=>$this->user);
        $request->setRouteResolver(fn ()=>(new \Illuminate\Routing\Route('GET','test',fn ()=>null))->name('superadmin.workload_analyzer.analysis'));
        $factory=$this->createMock(\Illuminate\Contracts\Routing\ResponseFactory::class);
        $factory->method('json')->willReturnCallback(fn ($data)=>new \Illuminate\Http\JsonResponse($data));
        Container::getInstance()->instance(\Illuminate\Contracts\Routing\ResponseFactory::class,$factory);
        $schedule=new PersonalWorkSchedule();
        $controller=new \App\Http\Controllers\Management\WorkloadAnalysisController($schedule,new WorkloadTrialAnalysis($schedule));
        $actual=$controller->analysis($request)->getData(true);
        self::assertSame('trial_time_v3',$actual['score_version']);
        self::assertSame(90,$actual['people'][0]['minutes']);
        self::assertEquals($expected,$actual['people'][0]);
    }

    public function test_employee_projection_keeps_overtime_in_its_source_without_changing_points(): void
    {
        $this->insert('event_item_types', ['id' => 2, 'slug' => 'other']);
        $this->insert('events', ['id' => 1, 'user_id' => 1, 'event_item_type_id' => 2,
            'starts_at' => '2026-10-29 17:00:00', 'ends_at' => '2026-10-29 19:00:00']);
        $this->insert('work_records', ['id' => 1, 'user_id' => 1, 'date' => '2026-10-29',
            'start_time' => '09:00', 'end_time' => '19:00', 'scheduled_end' => '18:00', 'overtime_minutes' => 60]);
        $analysis = new WorkloadTrialAnalysis(new PersonalWorkSchedule());
        $start = Carbon::parse('2026-10-29', 'Asia/Tokyo')->startOfDay();
        $end = $start->copy()->endOfDay();
        $normal = $analysis->analyze($this->user, collect([$this->user]), $start, $end, false, 'trial_time_v3')[0];
        $employee = $analysis->analyze($this->user, collect([$this->user]), $start, $end, false, 'trial_time_v3', false)[0];
        self::assertSame($normal['points'], $employee['points']);
        self::assertSame($normal['minutes'], $employee['minutes']);
        self::assertSame(60, $normal['time_breakdown_minutes']['overtime']);
        self::assertSame(0, $employee['time_breakdown_minutes']['overtime']);
        self::assertSame(120, $employee['time_breakdown_minutes']['other']);
        self::assertSame(120, array_sum($employee['time_breakdown_minutes']));
    }

    public function test_department_preview_returns_same_department_only_and_no_management_or_private_fields(): void
    {
        $db = $this->database->getConnection();
        $db->statement('alter table users add column sort_order integer default 0');
        $db->statement('alter table users add column assignment_id integer');
        $db->statement('create table assignments (id integer primary key, code text, name text)');
        $db->statement('create table user_settings (id integer primary key, user_id integer, worktype_id integer)');
        $db->statement('create table user_employment_settings (id integer primary key, user_id integer)');
        $this->insert('departments', ['id' => 1, 'company_id' => 1, 'code' => 'INFO']);
        $this->insert('users', ['id' => 2, 'name' => 'Other department', 'company_id' => 1, 'department_id' => 2]);
        $this->insert('users', ['id' => 3, 'name' => 'Other company', 'company_id' => 2, 'department_id' => 1]);
        $this->user->user_role = 'superadmin';
        $this->insert('event_item_types', ['id' => 2, 'slug' => 'other']);
        $this->insert('events', ['id' => 1, 'user_id' => 1, 'event_item_type_id' => 2,
            'starts_at' => '2026-10-29 09:00:00', 'ends_at' => '2026-10-29 10:00:00', 'visibility' => 'private']);
        $holidays = $this->createMock(\App\Services\ClerkCalendarHolidays::class);
        $holidays->method('forCompany')->willReturn([]);
        $schedule = new PersonalWorkSchedule();
        $service = new \App\Services\DepartmentWorkRecords($schedule, new WorkloadTrialAnalysis($schedule), $holidays);
        $result = $service->records($this->user, '2026-10-29');
        self::assertCount(1, $result['people']);
        self::assertSame(['id', 'name', 'minutes', 'breakdown'], array_keys($result['people'][0]));
        self::assertSame(60, $result['people'][0]['minutes']);
        self::assertSame(60, array_sum($result['people'][0]['breakdown']));
        self::assertSame(['minutes', 'unclassified_minutes', 'gap_minutes'], array_keys($result['mine']));
        self::assertNull($result['mine']['gap_minutes']);
        self::assertStringNotContainsString('points', json_encode($result));
        self::assertStringNotContainsString('visibility', json_encode($result));
        self::assertStringNotContainsString('title', json_encode($result));
        $this->insert('work_records', ['id' => 1, 'user_id' => 1, 'date' => '2026-10-29', 'start_time' => '09:00', 'end_time' => '11:00']);
        $withAttendance = $service->records($this->user, '2026-10-29');
        self::assertSame(60, $withAttendance['mine']['gap_minutes']);
        self::assertSame(0, $withAttendance['mine']['unclassified_minutes']);

    }

    private function analyze(string $from, string $to, string $version = 'trial_time_v3'): array
    {
        return (new WorkloadTrialAnalysis(new PersonalWorkSchedule()))->analyze($this->user, collect([$this->user]),
            Carbon::parse($from, 'Asia/Tokyo')->startOfDay(), Carbon::parse($to, 'Asia/Tokyo')->endOfDay(), true, $version)[0];
    }

    private function insert(string $table, array $row): void
    {
        $this->database->getConnection()->table($table)->insert($row);
    }
}


class ManagementRangeRequest extends \Illuminate\Http\Request
{
    public function validate(array $rules, ...$params): array
    {
        $factory=new \Illuminate\Validation\Factory(new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader(),'en'));
        return $factory->make($this->all(),$rules)->validate();
    }
}
