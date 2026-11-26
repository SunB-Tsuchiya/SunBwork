<?php

namespace App\Services;

use App\Models\{Diary, PersonalWorkEntry, User};
use App\Support\PersonalWorkActivities;
use Carbon\CarbonImmutable;

/** Read-only supplement to a diary already authorized by its existing review controller. */
class DiaryWorkTimeline
{
    public function __construct(private PersonalWorkSchedule $schedule) {}

    public function forDiary(User $viewer, Diary $diary, array $permittedUserIds, ?int $contextCompany): array
    {
        abort_unless(in_array((int) $diary->user_id, array_map('intval', $permittedUserIds), true), 403);
        $owner = User::findOrFail($diary->user_id);
        abort_unless($viewer->isSuperAdmin() || (int) $owner->company_id === (int) $viewer->company_id, 403);
        abort_unless($contextCompany === null || (int) $owner->company_id === $contextCompany, 403);
        if (! $this->schedule->personalAvailable($owner)) return [];

        $date = $diary->date->format('Y-m-d');
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Tokyo');
        $entries = PersonalWorkEntry::ownedWork($owner)->where('work_date', $date)
            ->select(['id', 'user_id', 'company_id', 'department_id', 'work_date', 'work_category', 'activity_type',
                'title', 'project_name_text', 'plan_slot', 'planned_start_time', 'planned_end_time',
                'entry_visibility', 'project_job_id', 'broad_project_id'])
            ->where(fn ($q) => $q->whereNull('project_job_id')->orWhereHas('projectJob', fn ($p) => $p
                ->whereNull('company_id')->orWhere('company_id', $owner->company_id)))
            ->where(fn ($q) => $q->whereNull('broad_project_id')->orWhereHas('broadProject', fn ($b) => $b
                ->where('company_id', $owner->company_id)->where('department_id', $owner->department_id)
                ->where(fn ($b) => $b->whereNull('parent_id')->orWhereHas('parent', fn ($p) => $p
                    ->where('company_id', $owner->company_id)->where('department_id', $owner->department_id)))))
            ->with(['projectJob:id,title,user_id,company_id,show_on_department_schedule', 'projectJob.coordinators:id',
                'broadProject:id,name,company_id,department_id'])->orderBy('id')->get();
        $window = $entries->contains(fn ($entry) => $entry->plan_slot !== 'hour')
            ? $this->schedule->workWindow($owner, $date) : null;

        return $entries->map(function (PersonalWorkEntry $entry) use ($viewer, $owner, $window, $day) {
            $projection = $this->schedule->entryForViewer($entry, $viewer, $window);
            $start = $projection['timeline_start_minute'];
            $end = $projection['timeline_end_minute'];
            if ($start === null || $end === null || $end <= $start) return null;
            // Global SuperAdmin diary access does not grant cross-company work-title disclosure.
            $title = (int) $owner->company_id === (int) $viewer->company_id ? $projection['title'] : '非公開の予定';
            return [
                'id' => 'personal-work-'.$entry->id, 'title' => $title,
                'start' => $day->addMinutes($start)->format('Y-m-d\TH:i:sP'),
                'end' => $day->addMinutes($end)->format('Y-m-d\TH:i:sP'),
                'allDay' => false, 'color' => PersonalWorkActivities::COLOR,
                'textColor' => PersonalWorkActivities::TEXT_COLOR, 'borderColor' => PersonalWorkActivities::BORDER_COLOR,
                'readOnly' => true,
            ];
        })->filter()->values()->all();
    }
}
