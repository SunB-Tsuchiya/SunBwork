<?php

namespace Tests\Unit;

use App\Models\PersonalWorkEntry;
use App\Models\BroadProject;
use App\Models\Event;
use App\Models\ProjectJob;
use App\Models\ProjectJobAssignment;
use App\Models\User;
use App\Services\PersonalWorkSchedule;
use App\Support\PersonalWorkActivities;
use PHPUnit\Framework\TestCase;

class PersonalWorkScheduleTest extends TestCase
{
    public function test_broad_project_name_is_visible_only_within_its_department_and_respects_private(): void
    {
        $broad = new BroadProject(['name' => '大まかな案件', 'company_id' => 1, 'department_id' => 2]);
        $entry = new PersonalWorkEntry([
            'user_id' => 10, 'broad_project_id' => 7, 'work_category' => 'dtp',
            'title' => '初校', 'entry_visibility' => 'department',
        ]);
        $entry->setRelation('projectJob', null);
        $entry->setRelation('broadProject', $broad);
        $viewer = new User();
        $viewer->id = 20;
        $viewer->company_id = 1;
        $viewer->department_id = 2;

        $service = new PersonalWorkSchedule();
        $this->assertSame('大まかな案件／初校', $service->entryForViewer($entry, $viewer)['title']);
        $this->assertSame('大まかな案件', $service->entryForViewer($entry, $viewer)['project_name']);

        $entry->entry_visibility = 'private';
        $this->assertSame('非公開の予定', $service->entryForViewer($entry, $viewer)['title']);

        $entry->entry_visibility = 'department';
        $viewer->department_id = 3;
        $this->assertSame('非公開の予定', $service->entryForViewer($entry, $viewer)['title']);
    }

    public function test_preview_is_unavailable_to_non_super_admin_users(): void
    {
        $user = new User();
        $user->user_role = 'user';
        $user->department_id = 1;

        $this->assertFalse((new PersonalWorkSchedule())->available($user));
    }

    public function test_suggested_minutes_excludes_lunch_and_keeps_unscheduled_work_unknown(): void
    {
        $service = new PersonalWorkSchedule();
        $window = ['start' => 540, 'end' => 1080, 'break_start' => 720, 'break_end' => 780];

        $this->assertSame(480, $service->suggestedMinutes([540, 1080], $window));
        $this->assertSame(60, $service->suggestedMinutes([690, 810], $window));
        $this->assertNull($service->suggestedMinutes(null, $window));
    }

    public function test_activity_catalog_includes_dual_role_office_and_proof_weights(): void
    {
        $codes = PersonalWorkActivities::codes();
        $this->assertContains('voucher_check', $codes);
        $this->assertContains('proof_revision_heavy', $codes);
        $this->assertContains('proof_revision_light', $codes);
        $this->assertContains('manuscript_check', array_keys(PersonalWorkActivities::GROUPS['shinko']));
        $this->assertSame(count($codes), count(array_unique($codes)));
        $this->assertTrue(PersonalWorkActivities::allows('jimu', 'voucher_check'));
        $this->assertFalse(PersonalWorkActivities::allows('kousei', 'voucher_check'));
    }

    public function test_private_entry_hides_names_and_results_from_another_user(): void
    {
        $entry = new PersonalWorkEntry([
            'user_id' => 10, 'work_category' => 'shinko', 'title' => '機密の打ち合わせ',
            'project_name_text' => '機密案件', 'entry_visibility' => 'private',
            'result_note' => '決定事項', 'actual_quantity' => 3, 'actual_unit' => '件',
        ]);
        $entry->setRelation('projectJob', null);
        $viewer = new User();
        $viewer->id = 20;
        $viewer->company_id = 1;

        $result = (new PersonalWorkSchedule())->entryForViewer($entry, $viewer);

        $this->assertSame('非公開の予定', $result['title']);
        $this->assertNull($result['project_name']);
        $this->assertNull($result['result_note']);
        $this->assertNull($result['actual_quantity']);
    }

    public function test_department_entry_uses_calendar_title_and_color(): void
    {
        $entry = new PersonalWorkEntry([
            'user_id' => 10, 'work_category' => 'dtp', 'title' => '初校入力',
            'project_name_text' => '2026年問題集', 'entry_visibility' => 'department',
        ]);
        $entry->setRelation('projectJob', null);
        $viewer = new User();
        $viewer->id = 20;
        $viewer->company_id = 1;

        $service = new PersonalWorkSchedule();
        $result = $service->entryForViewer($entry, $viewer);

        $this->assertSame($service->entryCalendarTitle($entry), $result['title']);
        $this->assertSame('2026年問題集／初校入力', $result['title']);
        $this->assertSame(PersonalWorkActivities::COLOR, $result['_custom_color']);
        $this->assertSame(PersonalWorkActivities::TEXT_COLOR, $result['_custom_text_color']);
        $this->assertFalse($result['private']);
    }

    public function test_slot_interval_uses_personal_work_and_lunch_boundaries(): void
    {
        $service = new PersonalWorkSchedule();
        $window = ['start' => 540, 'end' => 1080, 'break_start' => 750, 'break_end' => 810];

        $this->assertSame([540, 750], $service->slotInterval(new PersonalWorkEntry(['plan_slot' => 'am']), $window));
        $this->assertSame([810, 1080], $service->slotInterval(new PersonalWorkEntry(['plan_slot' => 'pm']), $window));
        $this->assertSame([675, 735], $service->slotInterval(new PersonalWorkEntry([
            'plan_slot' => 'hour', 'planned_start_time' => '11:15', 'planned_end_time' => '12:15',
        ]), null));
    }

    public function test_half_day_and_full_day_stay_visible_without_a_worktype(): void
    {
        $service = new PersonalWorkSchedule();

        $this->assertSame([540, 720], $service->slotInterval(new PersonalWorkEntry(['plan_slot' => 'am']), null));
        $this->assertSame([780, 1080], $service->slotInterval(new PersonalWorkEntry(['plan_slot' => 'pm']), null));
        $this->assertSame([540, 1080], $service->slotInterval(new PersonalWorkEntry(['plan_slot' => 'full']), null));
    }

    public function test_calendar_slot_determines_quick_work_minutes(): void
    {
        $service = new PersonalWorkSchedule();
        $window = ['start' => 540, 'end' => 1080, 'break_start' => 720, 'break_end' => 780];

        $this->assertSame(90, $service->plannedMinutes(new PersonalWorkEntry([
            'plan_slot' => 'hour', 'planned_start_time' => '11:30', 'planned_end_time' => '13:00',
        ]), $window));
        $this->assertSame(480, $service->plannedMinutes(new PersonalWorkEntry(['plan_slot' => 'full']), $window));
        $this->assertSame(180, $service->plannedMinutes(new PersonalWorkEntry(['plan_slot' => 'am']), null));
        $this->assertSame(300, $service->plannedMinutes(new PersonalWorkEntry(['plan_slot' => 'pm']), null));
    }

    public function test_secret_project_stays_masked_even_when_entry_is_department_visible(): void
    {
        $project = new ProjectJob(['title' => '極秘案件', 'company_id' => 1, 'user_id' => 10, 'show_on_department_schedule' => false]);
        $project->setRelation('coordinators', collect());
        $entry = new PersonalWorkEntry([
            'user_id' => 10, 'project_job_id' => 5, 'title' => '極秘作業',
            'entry_visibility' => 'department', 'result_note' => '処理済み',
        ]);
        $entry->setRelation('projectJob', $project);
        $viewer = new User();
        $viewer->id = 20;
        $viewer->company_id = 1;

        $result = (new PersonalWorkSchedule())->entryForViewer($entry, $viewer);

        $this->assertSame('非公開の予定', $result['title']);
        $this->assertNull($result['project_name']);
        $this->assertNull($result['result_note']);
    }

    public function test_unassigned_project_company_is_visible_only_when_department_display_is_enabled(): void
    {
        $viewer = new User();
        $viewer->id = 20;
        $viewer->company_id = 1;
        $project = new ProjectJob([
            'title' => '大枠のテスト', 'company_id' => null, 'user_id' => 10,
            'show_on_department_schedule' => true,
        ]);
        $project->setRelation('coordinators', collect());
        $assignment = new ProjectJobAssignment(['user_id' => 10, 'sender_id' => 10]);
        $assignment->setRelation('projectJob', $project);
        $event = new Event();
        $event->setRawAttributes([
            'user_id' => 10, 'title' => '【完了】大枠のテスト／入力',
            'starts_at' => '2026-10-09 09:00:00', 'ends_at' => '2026-10-09 10:00:00',
        ]);
        $event->setRelation('projectJobAssignment', $assignment);
        $service = new PersonalWorkSchedule();

        $visible = $service->eventForViewer($event, $viewer, '2026-10-09', 10);
        $this->assertFalse($visible['private']);
        $this->assertSame('大枠のテスト／入力', $visible['title']);
        $this->assertSame('大枠のテスト', $visible['project_name']);
        $this->assertSame('【完了】大枠のテスト／入力', $event->title);

        $project->show_on_department_schedule = false;
        $secret = $service->eventForViewer($event, $viewer, '2026-10-09', 10);
        $this->assertTrue($secret['private']);
        $this->assertSame('非公開の予定', $secret['title']);

        $project->show_on_department_schedule = true;
        $project->company_id = 2;
        $this->assertTrue($service->eventForViewer($event, $viewer, '2026-10-09', 10)['private']);
    }

    public function test_department_entry_can_show_unassigned_project_without_revealing_private_entry(): void
    {
        $viewer = new User();
        $viewer->id = 20;
        $viewer->company_id = 1;
        $project = new ProjectJob([
            'title' => '大枠のテスト', 'company_id' => null, 'user_id' => 10,
            'show_on_department_schedule' => true,
        ]);
        $project->setRelation('coordinators', collect());
        $entry = new PersonalWorkEntry([
            'user_id' => 10, 'title' => '入力', 'entry_visibility' => 'department',
        ]);
        $entry->setRelation('projectJob', $project);
        $service = new PersonalWorkSchedule();

        $this->assertSame('大枠のテスト／入力', $service->entryForViewer($entry, $viewer)['title']);

        $entry->entry_visibility = 'private';
        $this->assertSame('非公開の予定', $service->entryForViewer($entry, $viewer)['title']);
    }

    public function test_result_without_plan_does_not_claim_the_day_is_scheduled(): void
    {
        $user = new User();
        $entry = new PersonalWorkEntry(['work_category' => 'eigyo', 'result_note' => '訪問を完了']);

        $result = (new PersonalWorkSchedule())->occupancy($user, '2026-10-08', collect(), collect([$entry]));

        $this->assertSame(['stage' => 'unknown', 'percent' => null], $result);
    }

    public function test_company_meeting_uses_existing_schedule_visibility(): void
    {
        $owner = new User();
        $owner->id = 10;
        $owner->company_id = 1;
        $viewer = new User();
        $viewer->id = 20;
        $viewer->company_id = 1;
        $event = new Event();
        $event->setRawAttributes([
            'user_id' => 10, 'title' => '公開会議', 'is_company_event' => true,
            'visibility' => 'company', 'starts_at' => '2026-10-08 10:00:00',
            'ends_at' => '2026-10-08 11:00:00',
        ]);
        $event->setRelation('user', $owner);
        $event->setRelation('projectJobAssignment', null);

        $service = new PersonalWorkSchedule();
        $this->assertSame('公開会議', $service->eventForViewer($event, $viewer, '2026-10-08', 10)['title']);

        $event->visibility = 'private';
        $this->assertSame('非公開の予定', $service->eventForViewer($event, $viewer, '2026-10-08', 10)['title']);
    }

    public function test_self_assigned_event_is_identified_as_my_job(): void
    {
        $viewer = new User();
        $viewer->id = 10;
        $viewer->company_id = 1;
        $assignment = new ProjectJobAssignment(['user_id' => 10, 'sender_id' => 10, 'job_type' => 'operator']);
        $assignment->setRelation('projectJob', null);
        $event = new Event();
        $event->setRawAttributes([
            'user_id' => 10, 'title' => '初校入力', 'project_job_assignment_id' => 5,
            'starts_at' => '2026-10-09 17:30:00', 'ends_at' => '2026-10-09 19:00:00',
        ]);
        $event->setRelation('projectJobAssignment', $assignment);
        $event->setRelation('user', $viewer);

        $result = (new PersonalWorkSchedule())->eventForViewer($event, $viewer, '2026-10-09', 10);

        $this->assertSame('my_job', $result['kind']);
        $this->assertSame('#4F46E5', $result['_custom_color']);
    }
}
