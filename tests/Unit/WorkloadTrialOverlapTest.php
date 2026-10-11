<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\EventItemType;
use App\Services\PersonalWorkSchedule;
use App\Services\WorkloadTrialAnalysis;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

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

    private function ledger(array $items): array
    {
        $service = new WorkloadTrialAnalysis($this->createMock(PersonalWorkSchedule::class));

        return (new ReflectionMethod($service, 'minuteLedger'))->invoke($service, $items, [
            'break_start' => 720, 'break_end' => 780,
        ]);
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
