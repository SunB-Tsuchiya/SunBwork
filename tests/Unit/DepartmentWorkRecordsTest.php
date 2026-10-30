<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\ClerkCalendarHolidays;
use App\Services\DepartmentWorkRecords;
use App\Services\PersonalWorkSchedule;
use App\Services\WorkloadTrialAnalysis;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class DepartmentWorkRecordsTest extends TestCase
{
    private mixed $previousFacade;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousFacade = \Illuminate\Support\Facades\Facade::getFacadeApplication();
        $container = new \Illuminate\Container\Container;
        $translator = new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader, 'en');
        $container->instance('validator', new \Illuminate\Validation\Factory($translator, $container));
        \Illuminate\Support\Facades\Facade::clearResolvedInstances();
        \Illuminate\Support\Facades\Facade::setFacadeApplication($container);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        \Illuminate\Support\Facades\Facade::clearResolvedInstances();
        \Illuminate\Support\Facades\Facade::setFacadeApplication($this->previousFacade);
        parent::tearDown();
    }

    private function service(bool $allowed = true): DepartmentWorkRecords
    {
        $schedule = $this->createMock(PersonalWorkSchedule::class);
        $schedule->method('available')->willReturn($allowed);
        $analysis = $this->createMock(WorkloadTrialAnalysis::class);
        $holidays = $this->createMock(ClerkCalendarHolidays::class);
        $holidays->method('forCompany')->willReturn([['date' => '2026-11-03', 'name' => 'Synthetic holiday']]);

        return new DepartmentWorkRecords($schedule, $analysis, $holidays);
    }

    public function test_recent_dates_exclude_weekends_and_company_holidays_but_keep_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-04 00:15:00', 'Asia/Tokyo'));
        self::assertSame(['2026-11-04', '2026-11-02', '2026-10-30', '2026-10-29', '2026-10-28'], $this->service()->dates(new User(['company_id' => 1])));
        Carbon::setTestNow(Carbon::parse('2026-11-01 12:00:00', 'Asia/Tokyo'));
        self::assertSame(['2026-11-01', '2026-10-30', '2026-10-29', '2026-10-28', '2026-10-27'], $this->service()->dates(new User(['company_id' => 1])));
    }

    public function test_preview_rejects_ordinary_users_before_reading_dates_or_analysis(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->service(false)->records(new User(['user_role' => 'user']), null);
    }

    public function test_out_of_window_date_cannot_be_requested_directly(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00', 'Asia/Tokyo'));
        $this->expectException(ValidationException::class);
        $this->service()->records(new User(['company_id' => 1]), '2026-09-01');
    }

    public function test_projection_is_an_allowlist_even_if_source_contains_private_fields(): void
    {
        $row = $this->service()->publicPerson(['id' => 1, 'name' => 'Synthetic', 'minutes' => 60,
            'time_breakdown_minutes' => ['job' => 30, 'quick' => 0, 'meeting' => 30, 'other' => 0],
            'points' => 999, 'title' => 'Secret', 'activities' => [['private' => true]], 'daily' => []]);
        self::assertSame(['id' => 1, 'name' => 'Synthetic', 'minutes' => 60,
            'breakdown' => ['job' => 30, 'quick' => 0, 'meeting' => 30, 'other' => 0]], $row);
    }

    public function test_gap_uses_union_of_attendance_and_plans_excludes_break_and_future(): void
    {
        $window = ['start' => 540, 'end' => 1080, 'break_start' => 720, 'break_end' => 780];
        // 09-18 less lunch=480; 09-11 plus overlapping 10-12 covers180; 13-14 covers60.
        self::assertSame(240, $this->service()->gapMinutes([[540, 1080], [600, 1020]], [[540, 660], [600, 720], [780, 840]], $window, 1440));
        self::assertSame(60, $this->service()->gapMinutes([[540, 1080]], [[540, 600]], $window, 660));
        self::assertSame(0, $this->service()->gapMinutes([[540, 1080]], [], $window, 500));
    }
}
