<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\EventItemType;
use App\Models\PersonalWorkEntry;
use App\Models\User;
use App\Services\PersonalWorkSchedule;
use App\Services\WorkloadTrialAnalysis;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Illuminate\Support\Collection;

class WorkloadTrialOverlapTest extends TestCase
{
    public function test_fixed_appointment_takes_time_from_a_long_work_block(): void
    {
        $ledger = $this->ledger([
            $this->item('work', 540, 1080, 'my_job', 1.0, 0),
            $this->item('meeting', 600, 960, 'meeting', 0.8, 1),
        ]);

        self::assertSame(480, $ledger['minutes']);
        self::assertSame(180, $ledger['activities']['dtp|my_job']['minutes']);
        self::assertSame(300, $ledger['activities']['dtp|meeting']['minutes']);
        self::assertEqualsWithDelta(70.0, $ledger['points'], 0.001);
        self::assertSame(['work_points' => 30.0, 'meeting_points' => 40.0], $this->breakdown($ledger));
    }

    public function test_simultaneous_work_blocks_still_share_their_time(): void
    {
        $ledger = $this->ledger([
            $this->item('one', 540, 660, 'my_job', 1.0, 0),
            $this->item('two', 540, 660, 'revision', 1.0, 0),
        ]);

        self::assertSame(120, $ledger['minutes']);
        self::assertSame(60.0, $ledger['activities']['dtp|my_job']['minutes']);
        self::assertSame(60.0, $ledger['activities']['dtp|revision']['minutes']);
    }

    public function test_business_outing_is_a_fixed_appointment_but_a_job_is_not(): void
    {
        $service = new WorkloadTrialAnalysis($this->createMock(PersonalWorkSchedule::class));
        $method = new ReflectionMethod($service, 'isFixedAppointment');
        $outing = new Event(['project_job_assignment_id' => null]);
        $outing->setRelation('eventItemType', new EventItemType(['slug' => 'outing']));
        $job = new Event(['project_job_assignment_id' => 12]);
        $job->setRelation('eventItemType', new EventItemType(['slug' => 'outing']));

        self::assertTrue($method->invoke($service, $outing));
        self::assertFalse($method->invoke($service, $job));
    }

    public function test_v3_does_not_treat_a_shared_other_event_as_a_meeting(): void
    {
        $service = new WorkloadTrialAnalysis($this->createMock(PersonalWorkSchedule::class));
        $method = new ReflectionMethod($service, 'isFixedAppointment');
        $other = new Event(['is_company_event' => true]);
        $other->setRelation('eventItemType', new EventItemType(['slug' => 'other']));
        $meeting = new Event(['is_company_event' => false]);
        $meeting->setRelation('eventItemType', new EventItemType(['slug' => 'meeting_internal']));

        self::assertTrue($method->invoke($service, $other, 'trial_time_v2'));
        self::assertFalse($method->invoke($service, $other, 'trial_time_v3'));
        self::assertTrue($method->invoke($service, $meeting, 'trial_time_v3'));
    }

    public function test_v3_counts_a_work_schedule_as_work_and_only_accepted_guests(): void
    {
        $service = new WorkloadTrialAnalysis($this->createMock(PersonalWorkSchedule::class));
        $work = new Event(['is_company_event' => false]);
        $work->setRelation('eventItemType', new EventItemType(['slug' => 'work_schedule']));
        self::assertTrue((new ReflectionMethod($service, 'isScheduledWork'))->invoke($service, $work));
        self::assertFalse((new ReflectionMethod($service, 'isFixedAppointment'))->invoke($service, $work, 'trial_time_v3'));

        $attendees = new Collection([
            (object) ['user_id' => 2, 'status' => 'pending'],
            (object) ['user_id' => 3, 'status' => 'accepted'],
        ]);
        $counts = new ReflectionMethod($service, 'attendeeCounts');
        self::assertTrue($counts->invoke($service, $attendees, 1, 1, true));
        self::assertFalse($counts->invoke($service, $attendees, 2, 1, true));
        self::assertTrue($counts->invoke($service, $attendees, 3, 1, true));
        self::assertTrue($counts->invoke($service, $attendees, 2, 1, false));
    }

    public function test_v3_scores_a_private_work_entry_without_revealing_its_detail(): void
    {
        $schedule = $this->createMock(PersonalWorkSchedule::class);
        $schedule->method('entryForViewer')->willReturn(['private' => true]);
        $service = new WorkloadTrialAnalysis($schedule);
        $entry = new PersonalWorkEntry(['work_category' => 'dtp', 'entry_visibility' => 'private']);
        $viewer = new User();
        $counts = new ReflectionMethod($service, 'entryCounts');

        self::assertFalse($counts->invoke($service, $entry, $viewer, false));
        self::assertTrue($counts->invoke($service, $entry, $viewer, true));
    }

    public function test_v3_excludes_a_cancelled_event_and_its_linked_work_entry(): void
    {
        $service = new WorkloadTrialAnalysis($this->createMock(PersonalWorkSchedule::class));
        $event = new Event();
        $event->setDateFormat('Y-m-d H:i:s');
        $event->cancelled_at = '2026-10-21 12:00:00';
        $eventCounts = new ReflectionMethod($service, 'eventCounts');
        self::assertTrue($eventCounts->invoke($service, $event, false));
        self::assertFalse($eventCounts->invoke($service, $event, true));

        $entry = new PersonalWorkEntry(['work_category' => 'dtp', 'source_event_id' => 5]);
        $entry->setRelation('sourceEvent', $event);
        self::assertFalse((new ReflectionMethod($service, 'entryCounts'))->invoke($service, $entry, new User(), true));
    }

    public function test_sales_visit_is_shown_with_meetings_without_changing_total(): void
    {
        $ledger = $this->ledger([
            $this->item('visit', 540, 600, 'sales_visit', 1.0, 1),
            $this->item('work', 600, 660, 'my_job', 1.0, 0),
        ]);

        self::assertEqualsWithDelta(20.0, $ledger['points'], 0.001);
        self::assertSame(['work_points' => 10.0, 'meeting_points' => 10.0], $this->breakdown($ledger));
    }

    private function ledger(array $items): array
    {
        $service = new WorkloadTrialAnalysis($this->createMock(PersonalWorkSchedule::class));

        return (new ReflectionMethod($service, 'minuteLedger'))->invoke($service, $items, [
            'break_start' => 720, 'break_end' => 780,
        ]);
    }

    private function breakdown(array $ledger): array
    {
        $service = new WorkloadTrialAnalysis($this->createMock(PersonalWorkSchedule::class));

        return (new ReflectionMethod($service, 'splitBasePoints'))
            ->invoke($service, $ledger['activities'], $ledger['points']);
    }

    private function item(string $key, int $from, int $to, string $activity, float $coefficient, int $priority): array
    {
        return [
            'key' => $key, 'from' => $from, 'to' => $to,
            'work_category' => 'dtp', 'activity_type' => $activity,
            'coefficient' => $coefficient, 'priority' => $priority,
        ];
    }
}
