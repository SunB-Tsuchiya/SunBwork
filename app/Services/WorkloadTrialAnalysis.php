<?php

namespace App\Services;

use App\Http\Controllers\Concerns\CalculatesEventTime;
use App\Models\Event;
use App\Models\PersonalWorkEntry;
use App\Models\ProjectJob;
use App\Models\User;
use App\Models\WorkRecord;
use App\Support\PersonalWorkActivities;
use App\Support\PersonalWorkPoints;
use App\Support\PersonalWorkRole;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Trial measure of recorded work. All intervals are resolved to JST before counting. */
class WorkloadTrialAnalysis
{
    use CalculatesEventTime;

    public const CREATION_POINTS = 5.0;
    public const OVERTIME_BONUS_FIRST_THREE_HOURS = 0.5;
    public const OVERTIME_BONUS_AFTER_THREE_HOURS = 0.2;
    public const MANAGEMENT_POINTS_PER_ACTIVE_DAY = 0.5;
    public const DEPUTY_FACTOR = 0.8;

    public function __construct(private PersonalWorkSchedule $schedule) {}

    public function analyze(User $viewer, Collection $users, Carbon $start, Carbon $end, bool $withDaily = false): array
    {
        $userIds = $users->pluck('id')->map(fn ($id) => (int) $id)->all();
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();
        $now = now('Asia/Tokyo');
        $today = $now->toDateString();
        $currentTime = $now->format('H:i:s');
        $userById = $users->keyBy('id');
        $entries = PersonalWorkEntry::query()
            ->where('company_id', $viewer->company_id)
            ->where('department_id', $viewer->department_id)
            ->whereIn('user_id', $userIds)
            ->whereBetween('work_date', [$startDate, $endDate])
            ->where('work_date', '<=', $today)
            ->where('actual_minutes', '>', 0)
            ->with([
                'projectJob.coordinators:id',
                'projectJob:id,title,company_id,user_id,show_on_department_schedule',
                'sourceEvent.user:id,company_id',
                'sourceEvent.eventItemType:id,slug',
                'sourceEvent.projectJobAssignment:id,project_job_id,user_id,sender_id,job_type,stage_id,work_item_type_id',
                'sourceEvent.projectJobAssignment.projectJob.coordinators:id',
                'sourceEvent.projectJobAssignment.projectJob:id,title,company_id,user_id,show_on_department_schedule',
            ])->get()->filter(function (PersonalWorkEntry $entry) use ($viewer) {
                if (! PersonalWorkActivities::allows($entry->work_category, $entry->activity_type)) return false;
                if ($entry->source_event_id) {
                    return $entry->sourceEvent && ! $this->schedule->eventForViewer(
                        $entry->sourceEvent, $viewer, $entry->work_date->toDateString(), (int) $entry->user_id, $entry
                    )['private'];
                }
                return ! $this->schedule->entryForViewer($entry, $viewer)['private'];
            })->values();
        $entryByEvent = $entries->whereNotNull('source_event_id')
            ->keyBy(fn ($entry) => $entry->user_id.'|'.$entry->source_event_id);

        $events = Event::query()
            ->whereBetween('starts_at', [
                $start->copy()->subDay()->toDateTimeString(),
                $end->copy()->addDays(2)->endOfDay()->toDateTimeString(),
            ])
            ->where(function ($query) use ($userIds) {
                $query->whereIn('user_id', $userIds)
                    ->orWhereHas('attendees', fn ($attendee) => $attendee->whereIn('user_id', $userIds)
                        ->where('status', '!=', 'declined'));
            })
            ->with([
                'user:id,company_id', 'eventItemType:id,slug',
                'projectJobAssignment:id,project_job_id,user_id,sender_id,job_type,stage_id,work_item_type_id',
                'projectJobAssignment.projectJob.coordinators:id',
                'projectJobAssignment.projectJob:id,title,company_id,user_id,show_on_department_schedule',
            ])->get();
        $attendees = DB::table('schedule_attendees')->whereIn('event_id', $events->pluck('id'))
            ->whereIn('user_id', $userIds)->where('status', '!=', 'declined')
            ->get(['event_id', 'user_id'])->groupBy('event_id');
        $copies = $events->filter(fn ($event) => $event->is_materialized_copy && $event->source_schedule_event_id)
            ->mapWithKeys(fn ($event) => [$event->user_id.'|'.$event->source_schedule_event_id => true]);

        $intervals = [];
        $recordKeys = [];
        $sourceRecords = [];
        $workUnits = [];
        $quickQuantities = [];
        foreach ($entries as $entry) {
            $user = $userById->get($entry->user_id);
            if (! $user) continue;
            $date = $entry->work_date->toDateString();
            $key = 'quick-'.$entry->id;
            $activity = $entry->activity_type ?: 'unspecified';
            if ($entry->source_event_id && $entry->sourceEvent) {
                $from = $this->resolveJstCarbon($entry->sourceEvent, 'starts_at');
                $to = $this->resolveJstCarbon($entry->sourceEvent, 'ends_at');
                $range = $this->dateInterval($from, $to, $date);
            } else {
                $range = $this->schedule->slotInterval($entry, $this->schedule->workWindow($user, $date));
            }
            if ($range && $date === $today) {
                $range[1] = min($range[1], $now->hour * 60 + $now->minute);
            }
            if (! $range || $range[1] <= $range[0]) continue;
            $intervals[$entry->user_id][$date][] = $this->item(
                $key, $range, $entry->work_category, $activity,
                $entry->sourceEvent && $this->isFixedAppointment($entry->sourceEvent)
            );
            $recordKeys[$entry->user_id][$key] = true;
            $assignment = $entry->sourceEvent?->projectJobAssignment;
            $source = $assignment
                ? ((int) $assignment->sender_id === (int) $entry->user_id ? 'my_job' : 'assigned_job')
                : 'quick';
            $sourceRecords[$entry->user_id][$source][$key] = true;
            $reference = $assignment
                ? $this->jobUnitReference($assignment)
                : ($entry->project_job_id ? 'project-'.$entry->project_job_id
                    : 'title-'.mb_strtolower(trim((string) ($entry->title ?: $entry->id))));
            $workUnits[$entry->user_id][$date.'|'.$entry->work_category.'|'.$activity.'|'.$reference] = true;
            if ($entry->actual_quantity !== null && filled($entry->actual_unit)) {
                $group = $entry->work_category.'|'.$activity;
                $unit = trim($entry->actual_unit);
                $quickQuantities[$entry->user_id][$group][$unit] =
                    ($quickQuantities[$entry->user_id][$group][$unit] ?? 0) + (float) $entry->actual_quantity;
            }
        }

        foreach ($events as $event) {
            $from = $this->resolveJstCarbon($event, 'starts_at');
            $to = $this->resolveJstCarbon($event, 'ends_at');
            if (! $from || ! $to || $to->lte($from) || $to->gt($now)) continue;
            $assignment = $event->projectJobAssignment;
            $isJob = $assignment && $assignment->user_id;
            $isMeeting = ! $isJob && $this->isFixedAppointment($event);
            if (! $isJob && ! $isMeeting) continue;
            $people = [(int) $event->user_id];
            if ($event->is_company_event && $isMeeting) {
                foreach ($attendees->get($event->id, collect()) as $attendee) $people[] = (int) $attendee->user_id;
            }
            foreach (array_unique($people) as $personId) {
                $user = $userById->get($personId);
                if (! $user || ($isJob && (int) $assignment->user_id !== $personId)) continue;
                if ($personId !== (int) $event->user_id && $copies->has($personId.'|'.$event->id)) continue;
                $result = $entryByEvent->get($personId.'|'.$event->id);
                if ($result) continue; // The result entry already represents this interval.
                $detail = $this->schedule->eventForViewer(
                    $event, $viewer, $from->toDateString(), $personId, null,
                    $personId === (int) $viewer->id && $attendees->get($event->id, collect())->contains('user_id', $viewer->id)
                );
                if ($detail['private']) continue;
                $category = $isJob ? $this->jobCategory($user, $assignment->job_type) : $this->roleCategory($user);
                $activity = $isJob ? 'my_job'
                    : (in_array($event->eventItemType?->slug, ['customer_visit', 'client_visit', 'outing'], true)
                        ? 'outing' : 'meeting');
                for ($day = $start->copy()->startOfDay(); $day->lte($end); $day->addDay()) {
                    $date = $day->toDateString();
                    $range = $this->dateInterval($from, $to, $date);
                    if (! $range) continue;
                    $key = ($isJob ? 'job-' : 'meeting-').$event->id.'-'.$personId;
                    $intervals[$personId][$date][] = $this->item($key, $range, $category, $activity, $isMeeting);
                    $recordKeys[$personId][$key] = true;
                    $source = $isJob
                        ? ((int) $assignment->sender_id === $personId ? 'my_job' : 'assigned_job')
                        : 'meeting';
                    $sourceRecords[$personId][$source][$key] = true;
                    $reference = $isJob ? $this->jobUnitReference($assignment) : 'meeting-'.$event->id;
                    $workUnits[$personId][$date.'|'.$category.'|'.$activity.'|'.$reference] = true;
                }
            }
        }

        $workRecords = WorkRecord::whereIn('user_id', $userIds)
            ->whereBetween('date', [$startDate, $endDate])->get()->groupBy('user_id');
        $createdProjects = ProjectJob::query()->where('company_id', $viewer->company_id)
            ->whereIn('created_by_user_id', $userIds)
            ->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->with('coordinators:id')->get()
            ->filter(fn ($project) => $project->show_on_department_schedule
                || (int) $project->created_by_user_id === (int) $viewer->id
                || (int) $project->user_id === (int) $viewer->id
                || $project->coordinators->contains('id', $viewer->id))
            ->groupBy('created_by_user_id');
        $activityStart = $start->copy()->subDays(15)->toDateTimeString();
        $activityEnd = $end->copy()->addDays(2)->endOfDay()->toDateTimeString();
        $quickActivityQuery = PersonalWorkEntry::query()->whereNotNull('project_job_id')
            ->where('company_id', $viewer->company_id)
            ->whereNull('source_event_id')
            ->whereNotNull('plan_slot')
            ->whereBetween('work_date', [$start->copy()->subDays(14)->toDateString(), $endDate])
            ->where('actual_minutes', '>', 0)
            ->where(function ($query) use ($today, $currentTime) {
                $query->where('work_date', '<', $today)
                    ->orWhere(function ($sameDay) use ($today, $currentTime) {
                        $sameDay->where('work_date', $today)
                            ->where(function ($started) use ($currentTime) {
                                $started->where('plan_slot', '!=', 'hour')
                                    ->orWhere('planned_start_time', '<', $currentTime);
                            });
                    });
            });
        $activeProjectIds = DB::table('events as e')
            ->join('project_job_assignments as a', 'a.id', '=', 'e.project_job_assignment_id')
            ->join('project_jobs as p', 'p.id', '=', 'a.project_job_id')
            ->where('p.company_id', $viewer->company_id)
            ->whereBetween('e.starts_at', [$activityStart, $activityEnd])
            ->whereNotNull('a.project_job_id')->distinct()->pluck('a.project_job_id')
            ->merge((clone $quickActivityQuery)->distinct()->pluck('project_job_id'))
            ->unique()->values()->all();
        $managedProjects = ProjectJob::query()->where('company_id', $viewer->company_id)
            ->whereIn('id', $activeProjectIds)
            ->where(function ($query) use ($userIds) {
                $query->whereIn('user_id', $userIds)
                    ->orWhereHas('coordinators', fn ($q) => $q->whereIn('users.id', $userIds));
            })
            ->with(['coordinators:id', 'teamMembers:id,project_job_id,user_id'])
            ->get()->filter(fn ($project) => $project->show_on_department_schedule
                || (int) $project->user_id === (int) $viewer->id
                || $project->coordinators->contains('id', $viewer->id));
        $managedIds = $managedProjects->pluck('id')->all();
        $activityDates = [];
        if ($managedIds) {
            $teamEvents = Event::query()
                ->whereHas('projectJobAssignment', fn ($q) => $q->whereIn('project_job_id', $managedIds))
                ->whereBetween('starts_at', [$activityStart, $activityEnd])
                ->with('projectJobAssignment:id,project_job_id,job_type')
                ->get(['id', 'project_job_assignment_id', 'starts_at', 'ends_at']);
            foreach ($teamEvents as $event) {
                $from = $this->resolveJstCarbon($event, 'starts_at');
                $to = $this->resolveJstCarbon($event, 'ends_at');
                if (! $from || ! $to || $to->gt($now) || $to->lte($from)) continue;
                $projectId = $event->projectJobAssignment?->project_job_id;
                if ($projectId) $activityDates[$projectId][$from->toDateString()] = true;
            }
            $teamEntries = (clone $quickActivityQuery)->whereIn('project_job_id', $managedIds)
                ->get(['project_job_id', 'work_date']);
            foreach ($teamEntries as $entry) {
                $activityDates[$entry->project_job_id][$entry->work_date->toDateString()] = true;
            }
        }

        $people = [];
        foreach ($users as $user) {
            $id = (int) $user->id;
            $dayItems = $intervals[$id] ?? [];
            $totals = [];
            $minutes = 0;
            $scheduledCoveredMinutes = 0;
            $basePoints = 0.0;
            $overtimePoints = 0.0;
            $unrecordedOvertime = 0;
            $recordedDays = 0;
            $daily = [];
            foreach ($dayItems as $date => $items) {
                $window = $this->schedule->workWindow($user, $date);
                $ledger = $this->minuteLedger($items, $window);
                if ($withDaily) {
                    $daily[$date] = [
                        'date' => $date,
                        'records' => count(array_unique(array_column($items, 'key'))),
                        'minutes' => $ledger['minutes'],
                        'base_points' => round($ledger['points'], 1),
                    ];
                }
                if ($ledger['minutes'] > 0) $recordedDays++;
                $minutes += $ledger['minutes'];
                if ($window) {
                    $scheduledCoveredMinutes += count(array_filter($ledger['covered'],
                        fn ($minute) => $minute >= $window['start'] && $minute < $window['end']));
                }
                $basePoints += $ledger['points'];
                foreach ($ledger['activities'] as $key => $activity) {
                    $totals[$key]['work_category'] = $activity['work_category'];
                    $totals[$key]['activity_type'] = $activity['activity_type'];
                    $totals[$key]['minutes'] = ($totals[$key]['minutes'] ?? 0) + $activity['minutes'];
                    $totals[$key]['points'] = ($totals[$key]['points'] ?? 0) + $activity['points'];
                    $totals[$key]['records'] = ($totals[$key]['records'] ?? 0) + count($activity['records']);
                    $totals[$key]['days'][$date] = true;
                }
                $work = $workRecords->get($id, collect())->first(fn ($row) => $row->date->toDateString() === $date);
                if ($work && $work->overtime_minutes > 0) {
                    $scheduledEnd = $work->scheduled_end
                        ? $this->clockMinutes($work->scheduled_end)
                        : ($window['end'] ?? 1050);
                    $actualEnd = $this->clockMinutes($work->end_time);
                    if ($actualEnd < $scheduledEnd) $actualEnd += 1440;
                    $covered = 0;
                    foreach ($ledger['covered'] as $minute) {
                        if ($minute >= $scheduledEnd && $minute < $actualEnd) $covered++;
                    }
                    $recognized = min((int) $work->overtime_minutes, $covered);
                    $unrecordedOvertime += max(0, (int) $work->overtime_minutes - $recognized);
                    $first = min(180, $recognized);
                    $later = max(0, $recognized - 180);
                    $overtimePoints += ($first * self::OVERTIME_BONUS_FIRST_THREE_HOURS
                        + $later * self::OVERTIME_BONUS_AFTER_THREE_HOURS)
                        / 60 * PersonalWorkPoints::POINTS_PER_HOUR;
                }
            }
            foreach ($workRecords->get($id, collect()) as $work) {
                if (! array_key_exists($work->date->toDateString(), $dayItems)) {
                    $unrecordedOvertime += max(0, (int) $work->overtime_minutes);
                }
            }
            $activities = [];
            foreach ($totals as $key => $activity) {
                $activity['minutes'] = (int) round($activity['minutes']);
                $activity['points'] = round($activity['points'], 1);
                $activity['days'] = count($activity['days']);
                $activity['coefficient'] = PersonalWorkPoints::coefficient($activity['activity_type']);
                $activity['quantities'] = $quickQuantities[$id][$key] ?? [];
                $activities[] = $activity;
            }
            $projects = $createdProjects->get($id, collect());
            $creationPoints = $projects->count() * self::CREATION_POINTS;
            $managementPoints = 0.0;
            $activeProjects = 0;
            $projectDays = 0;
            foreach ($managedProjects as $project) {
                $leader = (int) $project->user_id === $id;
                $deputy = $project->coordinators->contains('id', $id);
                if (! $leader && ! $deputy) continue;
                $days = 0;
                $dates = array_keys($activityDates[$project->id] ?? []);
                for ($day = $start->copy()->startOfDay(); $day->lte($end); $day->addDay()) {
                    if ($day->gt($now)) break;
                    $recent = $day->copy()->subDays(13)->toDateString();
                    $date = $day->toDateString();
                    if (collect($dates)->contains(fn ($worked) => $worked >= $recent && $worked <= $date)) $days++;
                }
                if ($days === 0) continue;
                $activeProjects++;
                $projectDays += $days;
                $managers = $project->coordinators->pluck('id')->map(fn ($managerId) => (int) $managerId)->all();
                $members = $project->teamMembers->pluck('user_id')->map(fn ($memberId) => (int) $memberId)
                    ->filter(fn ($memberId) => $memberId !== (int) $project->user_id && ! in_array($memberId, $managers, true))
                    ->unique()->count();
                $teamFactor = 1 + min(0.6, $members * 0.15);
                $managementPoints += $days * self::MANAGEMENT_POINTS_PER_ACTIVE_DAY
                    * ($leader ? 1 : self::DEPUTY_FACTOR) * $teamFactor;
            }
            $person = [
                'id' => $id, 'name' => $user->name, 'role' => PersonalWorkRole::forUser($user),
                'minutes' => $minutes, 'base_points' => round($basePoints, 1),
                'scheduled_covered_minutes' => $scheduledCoveredMinutes,
                'overtime_points' => round($overtimePoints, 1),
                'creation_points' => round($creationPoints, 1),
                'management_points' => round($managementPoints, 1),
                'points' => round($basePoints + $overtimePoints + $creationPoints + $managementPoints, 1),
                'unrecorded_overtime_minutes' => $unrecordedOvertime,
                'active_managed_projects' => $activeProjects,
                'active_project_days' => $projectDays,
                'created_projects' => $projects->count(),
                'legacy_created_projects' => $projects->where('creator_source', 'legacy_leader')->count(),
                'records' => count($recordKeys[$id] ?? []),
                'recorded_days' => $recordedDays,
                'attendance_days' => $workRecords->get($id, collect())
                    ->filter(fn ($row) => filled($row->start_time) && filled($row->end_time))
                    ->unique(fn ($row) => $row->date->toDateString())->count(),
                'source_counts' => collect($sourceRecords[$id] ?? [])
                    ->map(fn ($records) => count($records))->all(),
                'work_units' => count($workUnits[$id] ?? []),
                'activities' => $activities,
            ];
            if ($withDaily) {
                for ($day = $start->copy()->startOfDay(); $day->lte($end); $day->addDay()) {
                    $date = $day->toDateString();
                    $daily[$date] ??= ['date' => $date, 'records' => 0, 'minutes' => 0, 'base_points' => 0.0];
                }
                ksort($daily);
                $person['daily'] = array_values($daily);
            }
            $people[] = $person;
        }
        return $people;
    }

    private function item(string $key, array $range, string $category, string $activity, bool $fixedAppointment = false): array
    {
        return ['key' => $key, 'from' => max(0, $range[0]), 'to' => min(1440, $range[1]),
            'work_category' => $category, 'activity_type' => $activity,
            'coefficient' => PersonalWorkPoints::coefficient($activity),
            'priority' => $fixedAppointment || in_array($activity, ['meeting', 'sales_visit'], true) ? 1 : 0];
    }

    private function isFixedAppointment(Event $event): bool
    {
        return ! $event->project_job_assignment_id && ($event->is_company_event
            || $event->meeting_definition_id
            || in_array($event->eventItemType?->slug, [
                'meeting_internal', 'meeting_client', 'conference',
                'customer_visit', 'client_visit', 'outing',
            ], true));
    }

    private function jobUnitReference($assignment): string
    {
        return 'job-'.($assignment->project_job_id ?: 'assignment-'.$assignment->id)
            .'-stage-'.($assignment->stage_id ?: 'none')
            .'-type-'.($assignment->work_item_type_id ?: 'none');
    }

    private function minuteLedger(array $items, ?array $window): array
    {
        $lunchFrom = $window['break_start'] ?? 720;
        $lunchTo = $window['break_end'] ?? 780;
        $activities = [];
        $covered = [];
        $points = 0.0;
        for ($minute = 0; $minute < 1440; $minute++) {
            if ($minute >= $lunchFrom && $minute < $lunchTo) continue;
            $active = array_values(array_filter($items, fn ($item) => $item['from'] <= $minute && $item['to'] > $minute));
            if (! $active) continue;
            $priority = max(array_column($active, 'priority'));
            $active = array_values(array_filter($active, fn ($item) => $item['priority'] === $priority));
            $covered[] = $minute;
            $share = 1 / count($active);
            foreach ($active as $item) {
                $key = $item['work_category'].'|'.$item['activity_type'];
                $activities[$key]['work_category'] = $item['work_category'];
                $activities[$key]['activity_type'] = $item['activity_type'];
                $activities[$key]['minutes'] = ($activities[$key]['minutes'] ?? 0) + $share;
                $activities[$key]['points'] = ($activities[$key]['points'] ?? 0)
                    + $share / 60 * PersonalWorkPoints::POINTS_PER_HOUR * $item['coefficient'];
                $activities[$key]['records'][$item['key']] = true;
                $points += $share / 60 * PersonalWorkPoints::POINTS_PER_HOUR * $item['coefficient'];
            }
        }
        return ['minutes' => count($covered), 'points' => $points, 'activities' => $activities,
            'covered' => $covered];
    }

    private function dateInterval(?Carbon $from, ?Carbon $to, string $date): ?array
    {
        if (! $from || ! $to || $to->lte($from)) return null;
        $day = Carbon::parse($date, 'Asia/Tokyo')->startOfDay();
        $end = $day->copy()->addDay();
        if ($from->gte($end) || $to->lte($day)) return null;
        return [max(0, (int) floor(($from->timestamp - $day->timestamp) / 60)),
            min(1440, (int) ceil(($to->timestamp - $day->timestamp) / 60))];
    }

    private function clockMinutes(?string $time): int
    {
        [$hour, $minute] = array_pad(explode(':', substr((string) $time, 0, 5)), 2, 0);
        return (int) $hour * 60 + (int) $minute;
    }

    private function roleCategory(User $user): string
    {
        return match (PersonalWorkRole::forUser($user)['code'] ?? null) {
            'shinko' => 'shinko', 'eigyo' => 'eigyo', 'kousei' => 'kousei',
            'jimu' => 'jimu', 'operator', 'seisaku' => 'dtp', default => 'other',
        };
    }

    private function jobCategory(User $user, ?string $jobType): string
    {
        return $jobType === 'proof' ? 'kousei' : $this->roleCategory($user);
    }
}
