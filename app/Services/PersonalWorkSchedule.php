<?php

namespace App\Services;

use App\Http\Controllers\Concerns\CalculatesEventTime;
use App\Models\Event;
use App\Models\PersonalWorkEntry;
use App\Models\User;
use App\Models\UserMonthlyBreak;
use App\Models\UserMonthlySchedule;
use App\Models\Worktype;
use App\Support\PersonalWorkActivities;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PersonalWorkSchedule
{
    use CalculatesEventTime;

    private const CATEGORIES = ['shinko', 'eigyo', 'dtp', 'kousei', 'jimu', 'other'];

    public function available(User $user): bool
    {
        return $user->isSuperAdmin() && $user->department_id && DB::table('departments')
            ->where('id', $user->department_id)
            ->where('company_id', $user->company_id)
            ->where('code', 'INFO')
            ->exists();
    }

    public function ownCandidates(User $user, string $date): array
    {
        if (! $this->available($user)) return ['entries' => [], 'events' => []];

        $entries = PersonalWorkEntry::where('user_id', $user->id)
            ->whereDate('work_date', $date)
            ->whereNull('source_event_id')
            ->orderBy('id')
            ->with('projectJob:id,title')
            ->get(['id', 'work_category', 'activity_type', 'title', 'project_name_text', 'project_job_id', 'plan_slot', 'planned_start_time', 'planned_end_time', 'actual_quantity', 'actual_unit', 'actual_minutes', 'result_note']);

        $window = $this->workWindow($user, $date);
        $entries = $entries->map(function (PersonalWorkEntry $entry) use ($window) {
            $row = $entry->toArray();
            $row['suggested_minutes'] = $this->plannedMinutes($entry, $window);
            return $row;
        });

        $events = $this->eventsForUsers([$user->id], $date)
            ->filter(fn (Event $event) => (int) $event->user_id === (int) $user->id && $event->project_job_assignment_id)
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'project_name' => $event->projectJobAssignment?->projectJob?->title,
                'work_category' => $this->categoryForEvent($event),
                'suggested_minutes' => $this->eventSuggestedMinutes($event, $date, $window),
            ])->values()->all();

        return ['entries' => $entries->toArray(), 'events' => $events];
    }

    public function eventsForUsers(array $userIds, string $date)
    {
        $start = Carbon::parse($date, 'Asia/Tokyo')->startOfDay();
        $lower = $start->copy()->subDay()->toDateTimeString();
        $upper = $start->copy()->addDays(2)->toDateTimeString();

        $attendees = DB::table('schedule_attendees')
            ->whereIn('user_id', $userIds)
            ->where('status', 'accepted')
            ->pluck('event_id');

        return Event::query()
            ->where(function ($query) use ($userIds, $attendees) {
                $query->whereIn('user_id', $userIds)->orWhereIn('id', $attendees);
            })
            ->whereBetween('starts_at', [$lower, $upper])
            ->with(['user:id,company_id', 'projectJobAssignment.projectJob.coordinators:id', 'projectJobAssignment.projectJob:id,title,company_id,user_id,show_on_department_schedule', 'eventItemType:id,slug'])
            ->get()
            ->filter(function (Event $event) use ($start) {
                $from = $this->resolveJstCarbon($event, 'starts_at');
                $until = $this->resolveJstCarbon($event, 'ends_at') ?? $from?->copy()->addHour();
                return $from && $until && $from->lt($start->copy()->addDay()) && $until->gt($start);
            });
    }

    public function categoryForEvent(Event $event): string
    {
        $assignment = $event->projectJobAssignment;
        $kind = $assignment
            ? ($assignment->sender_id !== null && (int) $assignment->sender_id === (int) $event->user_id ? 'my_job' : 'assigned_job')
            : 'event';
        if ($assignment?->job_type === 'proof') return 'kousei';
        return $assignment ? 'dtp' : 'other';
    }

    public function eventForViewer(Event $event, User $viewer, string $date, int $personId, ?PersonalWorkEntry $result = null, bool $viewerInvited = false, bool $hasProgress = false): array
    {
        $project = $event->projectJobAssignment?->projectJob;
        $own = (int) $viewer->id === $personId;
        $related = $own || ($project && ((int) $project->user_id === (int) $viewer->id
            || $project->coordinators->contains('id', $viewer->id)));
        if ($project) {
            // Legacy projects can lack a company; the department board already scopes people to the viewer's department.
            $public = $project->show_on_department_schedule
                && ($project->company_id === null || (int) $project->company_id === (int) $viewer->company_id);
            $showDetail = $related || $public;
        } else {
            // Reuse the shared schedule's public/company/group visibility for meetings.
            $sameCompany = (int) $event->user?->company_id === (int) $viewer->company_id;
            $showDetail = $own || $viewerInvited || ($event->is_company_event
                && ($event->visibility === 'public'
                    || ($sameCompany && in_array($event->visibility, ['company', 'group'], true))));
        }
        $start = $this->resolveJstCarbon($event, 'starts_at');
        $end = $this->resolveJstCarbon($event, 'ends_at') ?? $start?->copy()->addHour();
        $assignment = $event->projectJobAssignment;
        $kind = $assignment
            ? ($assignment->sender_id !== null && (int) $assignment->sender_id === (int) $event->user_id ? 'my_job' : 'assigned_job')
            : 'event';
        $color = $assignment
            ? (($hasProgress || $assignment->source_assignment_id || $assignment->supersedes_assignment_id) ? '#7C3AED'
                : ($assignment->job_type === 'proof' ? '#DB2777'
                    : ((int) $assignment->sender_id === (int) $event->user_id && $assignment->sender_id !== null ? '#4F46E5' : '#059669')))
            : ($event->is_company_event ? null : '#1fb6b3');
        $displayTitle = trim(preg_replace('/^【完了】\s*/u', '', (string) $event->title));
        if ($displayTitle === '') $displayTitle = $project?->title ?: (string) $event->title;

        return [
            'id' => $event->id,
            'source' => 'event',
            'kind' => $kind,
            'title' => $showDetail ? $displayTitle : '非公開の予定',
            'project_name' => $showDetail ? $project?->title : null,
            'work_category' => $event->project_job_assignment_id ? $this->categoryForEvent($event) : 'other',
            'activity_type' => $showDetail ? $result?->activity_type : null,
            'starts_at' => $start?->format('Y-m-d H:i'),
            'ends_at' => $end?->format('Y-m-d H:i'),
            'private' => ! $showDetail,
            'own' => $own,
            'is_own' => true,
            'event_item_type' => $event->relationLoaded('eventItemType') && $event->eventItemType
                ? ['slug' => $event->eventItemType->slug] : null,
            '_custom_color' => $color,
            'result_note' => $showDetail ? $result?->result_note : null,
            'actual_quantity' => $showDetail ? $result?->actual_quantity : null,
            'actual_unit' => $showDetail ? $result?->actual_unit : null,
            'actual_minutes' => $showDetail ? $result?->actual_minutes : null,
        ];
    }

    public function entryForViewer(PersonalWorkEntry $entry, User $viewer, ?array $window = null): array
    {
        $own = (int) $entry->user_id === (int) $viewer->id;
        $project = $entry->projectJob;
        $related = $own || ($project && ((int) $project->user_id === (int) $viewer->id
            || $project->coordinators->contains('id', $viewer->id)));
        $showDetail = $related || ($entry->entry_visibility === 'department'
            && (! $project || ($project->show_on_department_schedule
                && ($project->company_id === null || (int) $project->company_id === (int) $viewer->company_id))));

        $interval = $this->slotInterval($entry, $window);

        return [
            'id' => $entry->id,
            'source' => 'entry',
            'kind' => 'quick_work',
            'title' => $showDetail ? $this->entryCalendarTitle($entry) : '非公開の予定',
            'project_name' => $showDetail ? ($project?->title ?: $entry->project_name_text) : null,
            'work_category' => $entry->work_category,
            'activity_type' => $showDetail ? $entry->activity_type : null,
            'plan_slot' => $entry->plan_slot,
            'planned_start_time' => $entry->planned_start_time ? substr($entry->planned_start_time, 0, 5) : null,
            'planned_end_time' => $entry->planned_end_time ? substr($entry->planned_end_time, 0, 5) : null,
            'timeline_start_minute' => $interval[0] ?? null,
            'timeline_end_minute' => $interval[1] ?? null,
            'result_note' => $showDetail ? $entry->result_note : null,
            'actual_quantity' => $showDetail ? $entry->actual_quantity : null,
            'actual_unit' => $showDetail ? $entry->actual_unit : null,
            'actual_minutes' => $showDetail ? $entry->actual_minutes : null,
            'entry_visibility' => $own ? $entry->entry_visibility : null,
            'project_job_id' => $own ? $entry->project_job_id : null,
            'project_name_text' => $own ? $entry->project_name_text : null,
            'private' => ! $showDetail,
            'own' => $own,
            '_custom_color' => PersonalWorkActivities::COLOR,
            '_custom_text_color' => PersonalWorkActivities::TEXT_COLOR,
            '_custom_border_color' => PersonalWorkActivities::BORDER_COLOR,
        ];
    }

    public function entryCalendarTitle(PersonalWorkEntry $entry): string
    {
        $projectName = $entry->projectJob?->title ?: $entry->project_name_text;
        $workTitle = trim($entry->title ?? '');

        if ($projectName && $workTitle && $projectName !== $workTitle) return $projectName.'／'.$workTitle;
        return $projectName ?: ($workTitle ?: match ($entry->work_category) {
            'shinko' => '進行管理', 'eigyo' => '営業', 'dtp' => '組版・DTP・制作',
            'kousei' => '校正', 'jimu' => '事務', default => 'その他',
        });
    }

    public function workWindow(User $user, string $date): ?array
    {
        $worktypeId = UserMonthlySchedule::worktypeIdForDate($user->id, $date)
            ?? $user->userSetting?->worktype_id;
        $worktype = $worktypeId ? Worktype::find($worktypeId) : null;
        if (! $worktype) return null;

        $start = $this->minutes($worktype->start_time);
        $end = $this->minutes($worktype->end_time);
        if ($end <= $start) $end += 1440;
        $break = UserMonthlyBreak::breakForDate($user->id, $date);
        $breakStart = $this->minutes($break['start'] ?? $user->userSetting?->lunch_start ?? '12:00');
        $breakEnd = $this->minutes($break['end'] ?? $user->userSetting?->lunch_end ?? '13:00');
        if ($breakStart < $start || $breakStart >= $end) {
            $breakStart = $breakEnd = intdiv($start + $end, 2);
        }
        return ['start' => $start, 'end' => $end, 'break_start' => $breakStart, 'break_end' => $breakEnd];
    }

    public function slotInterval(PersonalWorkEntry $entry, ?array $window): ?array
    {
        if (! $entry->plan_slot) return null;
        if ($entry->plan_slot === 'hour') {
            return [$this->minutes($entry->planned_start_time), $this->minutes($entry->planned_end_time)];
        }
        // 勤務形態が未設定でも表示・作業時間を同じ仮の時刻から計算する。
        $window ??= ['start' => 540, 'break_start' => 720, 'break_end' => 780, 'end' => 1080];
        return match ($entry->plan_slot) {
            'am' => [$window['start'], $window['break_start']],
            'pm' => [$window['break_end'], $window['end']],
            'full' => [$window['start'], $window['end']],
            default => null,
        };
    }

    public function suggestedMinutes(?array $interval, ?array $window): ?int
    {
        if (! $interval || $interval[1] <= $interval[0]) return null;
        $minutes = $interval[1] - $interval[0];
        if ($window) {
            $minutes -= max(0, min($interval[1], $window['break_end']) - max($interval[0], $window['break_start']));
        }
        return max(0, $minutes);
    }

    public function plannedMinutes(PersonalWorkEntry $entry, ?array $window): ?int
    {
        $interval = $this->slotInterval($entry, $window);
        if ($entry->plan_slot === 'hour') {
            return $interval && $interval[1] > $interval[0] ? $interval[1] - $interval[0] : null;
        }
        $window ??= ['start' => 540, 'break_start' => 720, 'break_end' => 780, 'end' => 1080];
        return $this->suggestedMinutes($interval, $window);
    }

    private function eventSuggestedMinutes(Event $event, string $date, ?array $window): ?int
    {
        $start = $this->resolveJstCarbon($event, 'starts_at');
        $end = $this->resolveJstCarbon($event, 'ends_at');
        if (! $start || ! $end) return null;
        $day = Carbon::parse($date, 'Asia/Tokyo')->startOfDay();
        $from = max(0, (int) (($start->timestamp - $day->timestamp) / 60));
        $to = min(1440, (int) (($end->timestamp - $day->timestamp) / 60));
        return $this->suggestedMinutes([$from, $to], $window);
    }

    public function occupancy(User $user, string $date, $events, $entries): array
    {
        $hasAny = $events->isNotEmpty() || $entries->contains(fn ($entry) => filled($entry->plan_slot));
        if (! $hasAny) return ['stage' => 'unknown', 'percent' => null];
        $window = $this->workWindow($user, $date);
        if (! $window) return ['stage' => 'planned', 'percent' => null];

        $occupied = array_fill(0, min(2880, $window['end']), false);
        $mark = function (int $from, int $to) use (&$occupied, $window) {
            for ($minute = max($window['start'], $from); $minute < min($window['end'], $to); $minute++) {
                if ($minute < $window['break_start'] || $minute >= $window['break_end']) $occupied[$minute] = true;
            }
        };

        foreach ($events as $event) {
            $from = $this->resolveJstCarbon($event, 'starts_at');
            $to = $this->resolveJstCarbon($event, 'ends_at') ?? $from?->copy()->addHour();
            if (! $from || ! $to) continue;
            $day = Carbon::parse($date, 'Asia/Tokyo')->startOfDay();
            $mark((int) (($from->timestamp - $day->timestamp) / 60), (int) (($to->timestamp - $day->timestamp) / 60));
        }
        foreach ($entries as $entry) {
            $interval = $this->slotInterval($entry, $window);
            if ($interval) $mark($interval[0], $interval[1]);
        }

        $denominator = max(1, $window['end'] - $window['start']
            - max(0, min($window['break_end'], $window['end']) - max($window['break_start'], $window['start'])));
        $percent = (int) round(count(array_filter($occupied)) / $denominator * 100);
        return ['stage' => $percent >= 85 ? 'full' : ($percent >= 50 ? 'medium' : 'light'), 'percent' => min(100, $percent)];
    }

    private function minutes(?string $time): int
    {
        [$hour, $minute] = array_pad(explode(':', substr((string) $time, 0, 5)), 2, 0);
        return (int) $hour * 60 + (int) $minute;
    }
}
