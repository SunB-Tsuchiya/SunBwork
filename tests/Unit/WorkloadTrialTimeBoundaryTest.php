<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\ProjectJobAssignment;
use App\Services\PersonalWorkSchedule;
use App\Services\WorkloadTrialAnalysis;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class WorkloadTrialTimeBoundaryTest extends TestCase
{
    public function test_daily_break_change_preserves_work_before_the_actual_break(): void
    {
        $service = $this->service();
        $item = $this->item($service, 'work', [660, 750]); // 11:00–12:30
        $ledger = new ReflectionMethod($service, 'minuteLedger');

        $default = $ledger->invoke($service, [$item], ['break_start' => 720, 'break_end' => 780]);
        $changed = $ledger->invoke($service, [$item], ['break_start' => 750, 'break_end' => 810]);

        self::assertSame(60, $default['minutes']);
        self::assertSame(90, $changed['minutes']);
        self::assertEqualsWithDelta(15.0, $changed['points'], 0.00001);
        $spanning = $ledger->invoke($service, [$this->item($service, 'work', [660, 840])],
            ['break_start' => 750, 'break_end' => 810]);
        self::assertSame(120, $spanning['minutes']);
    }

    public function test_midnight_at_month_end_splits_time_without_losing_or_adding_minutes(): void
    {
        $service = $this->service();
        $interval = new ReflectionMethod($service, 'dateInterval');
        $from = Carbon::parse('2026-10-31 23:30:00', 'Asia/Tokyo');
        $to = Carbon::parse('2026-11-01 00:30:00', 'Asia/Tokyo');
        $october = $interval->invoke($service, $from, $to, '2026-10-31');
        $november = $interval->invoke($service, $from, $to, '2026-11-01');

        self::assertSame([1410, 1440], $october);
        self::assertSame([0, 30], $november);
        self::assertNull($interval->invoke($service, $from, $to, '2026-11-02'));
        $ledger = new ReflectionMethod($service, 'minuteLedger');
        $first = $ledger->invoke($service, [$this->item($service, 'first', $october)], null);
        $second = $ledger->invoke($service, [$this->item($service, 'second', $november)], null);
        self::assertSame(60, $first['minutes'] + $second['minutes']);
        self::assertEqualsWithDelta(10.0, $first['points'] + $second['points'], 0.00001);
    }

    public function test_proof_utc_storage_and_normal_jst_storage_resolve_to_the_same_work_time(): void
    {
        $service = $this->service();
        $normal = new Event();
        $normal->setRawAttributes(['starts_at' => '2026-10-29 09:00:00', 'ends_at' => '2026-10-29 10:00:00'], true);
        $normal->setRelation('projectJobAssignment', null);
        $proof = new Event();
        $proof->setRawAttributes(['starts_at' => '2026-10-29 00:00:00', 'ends_at' => '2026-10-29 01:00:00'], true);
        $proof->setRelation('projectJobAssignment', new ProjectJobAssignment(['job_type' => 'proof']));
        $resolve = new ReflectionMethod($service, 'resolveJstCarbon');
        $interval = new ReflectionMethod($service, 'dateInterval');

        foreach ([$normal, $proof] as $event) {
            $from = $resolve->invoke($service, $event, 'starts_at');
            $to = $resolve->invoke($service, $event, 'ends_at');
            self::assertSame('2026-10-29 09:00:00', $from->format('Y-m-d H:i:s'));
            self::assertSame('Asia/Tokyo', $from->timezoneName);
            self::assertSame([540, 600], $interval->invoke($service, $from, $to, '2026-10-29'));
        }
    }

    public function test_adjacent_blocks_do_not_overlap_and_invalid_intervals_are_ignored(): void
    {
        $service = $this->service();
        $ledger = (new ReflectionMethod($service, 'minuteLedger'))->invoke($service, [
            $this->item($service, 'first', [540, 600]),
            $this->item($service, 'second', [600, 660]),
        ], null);
        self::assertSame(120, $ledger['minutes']);
        self::assertEqualsWithDelta(20.0, $ledger['points'], 0.00001);
        $at = Carbon::parse('2026-10-29 10:00:00', 'Asia/Tokyo');
        $interval = new ReflectionMethod($service, 'dateInterval');
        self::assertNull($interval->invoke($service, $at, $at, '2026-10-29'));
        self::assertNull($interval->invoke($service, $at, $at->copy()->subHour(), '2026-10-29'));
    }

    private function service(): WorkloadTrialAnalysis
    {
        return new WorkloadTrialAnalysis($this->createMock(PersonalWorkSchedule::class));
    }

    private function item(WorkloadTrialAnalysis $service, string $key, array $range): array
    {
        return (new ReflectionMethod($service, 'item'))->invoke($service, $key, $range, 'dtp', 'my_job', false, 'job');
    }
}
