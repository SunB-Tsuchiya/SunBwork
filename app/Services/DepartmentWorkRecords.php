<?php

namespace App\Services;

use App\Http\Controllers\Concerns\CalculatesEventTime;
use App\Models\PersonalWorkEntry;
use App\Models\User;
use App\Models\WorkRecord;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/** Read-only preview. Only this explicit projection may reach the employee UI. */
class DepartmentWorkRecords
{
    use CalculatesEventTime;

    public function __construct(
        private PersonalWorkSchedule $schedule,
        private WorkloadTrialAnalysis $analysis,
        private ClerkCalendarHolidays $holidays,
    ) {}

    public function dates(User $viewer): array
    {
        $today = Carbon::now('Asia/Tokyo')->startOfDay();
        $holidays = array_column($this->holidays->forCompany((int) $viewer->company_id, $today->year), 'date');
        // Always allow today, including a weekend/holiday; the other four are business dates.
        $dates = [$today->toDateString()];
        for ($day = $today->copy()->subDay(); count($dates) < 5; $day->subDay()) {
            if (! $day->isWeekend() && ! in_array($day->toDateString(), $holidays, true)) {
                $dates[] = $day->toDateString();
            }
        }

        return $dates;
    }

    public function records(User $viewer, ?string $date): array
    {
        if (! $this->schedule->available($viewer)) {
            throw new AuthorizationException;
        }
        $dates = $this->dates($viewer);
        $date ??= $dates[0];
        if (! in_array($date, $dates, true)) {
            throw ValidationException::withMessages(['date' => '今日と直近4営業日から選択してください。']);
        }
        $day = Carbon::createFromFormat('!Y-m-d', $date, 'Asia/Tokyo');
        $users = User::where('company_id', $viewer->company_id)
            ->where('department_id', $viewer->department_id)
            ->with(['assignment:id,code,name', 'userSetting', 'employmentSetting'])
            ->ordered()->get();
        // Keep overtime in its work category; never expose a separate overtime gauge.
        $rows = $this->analysis->analyze($viewer, $users, $day, $day->copy()->endOfDay(), false, 'trial_time_v3', false);
        $own = collect($rows)->firstWhere('id', (int) $viewer->id);

        return [
            'date' => $date,
            'dates' => $dates,
            'people' => array_map(fn ($row) => $this->publicPerson($row), $rows),
            'mine' => [
                'minutes' => (int) ($own['minutes'] ?? 0),
                'unclassified_minutes' => (int) round(collect($own['activities'] ?? [])
                    ->where('activity_type', 'unspecified')->sum('minutes')),
                'gap_minutes' => $this->ownGap($viewer, $date),
            ],
        ];
    }

    public function publicPerson(array $row): array
    {
        $breakdown = $row['time_breakdown_minutes'] ?? [];

        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'minutes' => (int) $row['minutes'],
            'breakdown' => array_combine(['job', 'quick', 'meeting', 'other'], array_map(
                fn ($key) => max(0, (int) ($breakdown[$key] ?? 0)), ['job', 'quick', 'meeting', 'other']
            )),
        ];
    }

    private function ownGap(User $viewer, string $date): ?int
    {
        $records = WorkRecord::where('user_id', $viewer->id)->whereDate('date', $date)->get();
        $window = $this->schedule->workWindow($viewer, $date);
        // Actual attendance and an explicit break/window are required; never assume absence.
        if (! $window || $records->isEmpty() || $records->contains(fn ($row) => ! $row->start_time || ! $row->end_time)) {
            return null;
        }
        $attendance = $records->map(fn ($row) => [WorkRecord::timeToMinutes(substr($row->start_time, 0, 5)), WorkRecord::timeToMinutes(substr($row->end_time, 0, 5))])->all();
        // Overnight attendance requires a multi-day view; do not guess its daily allocation here.
        if (collect($attendance)->contains(fn ($range) => $range[1] <= $range[0])) {
            return null;
        }
        $day = Carbon::createFromFormat('!Y-m-d', $date, 'Asia/Tokyo');
        $intervals = [];
        foreach ($this->schedule->eventsForUsers([(int) $viewer->id], $date) as $event) {
            if ($event->cancelled_at || $event->eventItemType?->slug === 'personal_other') {
                continue;
            }
            $from = $this->resolveJstCarbon($event, 'starts_at');
            $until = $this->resolveJstCarbon($event, 'ends_at');
            if ($from && $until && $until->gt($from)) {
                $intervals[] = [(int) (($from->timestamp - $day->timestamp) / 60), (int) (($until->timestamp - $day->timestamp) / 60)];
            }
        }
        foreach (PersonalWorkEntry::where('user_id', $viewer->id)->whereDate('work_date', $date)->whereNull('source_event_id')->get() as $entry) {
            $interval = $this->schedule->slotInterval($entry, $window);
            if ($interval) {
                $intervals[] = $interval;
            }
        }
        $limit = $date === Carbon::now('Asia/Tokyo')->toDateString()
            ? Carbon::now('Asia/Tokyo')->hour * 60 + Carbon::now('Asia/Tokyo')->minute : 1440;

        return $this->gapMinutes($attendance, $intervals, $window, $limit);
    }

    public function gapMinutes(array $attendance, array $intervals, array $window, int $limit): int
    {
        $minutes = [];
        foreach ($attendance as [$start, $end]) {
            for ($minute = max(0, $start, $window['start']); $minute < min(1440, $end, $window['end'], $limit); $minute++) {
                if ($minute < $window['break_start'] || $minute >= $window['break_end']) {
                    $minutes[$minute] = true;
                }
            }
        }
        foreach ($intervals as [$start, $end]) {
            for ($minute = max(0, $start); $minute < min(1440, $end, $limit); $minute++) {
                unset($minutes[$minute]);
            }
        }

        return count($minutes);
    }
}
