<?php

namespace App\Services;

use App\Models\PersonalWorkEntry;
use App\Models\Event;
use App\Http\Controllers\Concerns\CalculatesEventTime;
use App\Support\PersonalWorkRole;
use App\Models\ProjectJobAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/** Reads existing work by an explicit source reference; never materializes copies. */
class WorkJobReader
{
    use CalculatesEventTime;
    public function __construct(private UnifiedMyJobBox $box, private PersonalWorkSchedule $schedule, private WorkloadTrialAnalysis $analysis) {}

    public function resolve(User $user, string $type, int $id, ?int $context = null): PersonalWorkEntry|ProjectJobAssignment
    {
        if (! $this->box->available($user, $context)) throw new AuthorizationException;
        if ($type === 'entry') {
            $job = PersonalWorkEntry::where('user_id', $user->id)->where('company_id', $user->company_id)
                ->where('department_id', $user->department_id)->whereNull('source_event_id')->findOrFail($id);
        } elseif ($type === 'assignment') {
            $job = ProjectJobAssignment::where('user_id', $user->id)->selfAssigned()
                ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $user->company_id))
                ->where(fn ($q) => $q->whereNull('department_id')->orWhere('department_id', $user->department_id))->findOrFail($id);
        } else {
            throw ValidationException::withMessages(['source' => 'この仕事は参照できません。']);
        }
        $job->load('projectJob');
        if ($job instanceof PersonalWorkEntry) {
            $job->load('broadProject.parent');
            foreach ([$job->broadProject, $job->broadProject?->parent] as $broad) {
                if ($broad && ((int) $broad->company_id !== (int) $user->company_id
                    || (int) $broad->department_id !== (int) $user->department_id)) throw new AuthorizationException;
            }
        }
        if ($job->projectJob && $job->projectJob->company_id !== null && (int) $job->projectJob->company_id !== (int) $user->company_id) throw new AuthorizationException;
        return $job;
    }

    public function parent(PersonalWorkEntry|ProjectJobAssignment $job): ?array
    {
        $entry = $job instanceof PersonalWorkEntry;
        $entryId = $entry ? $job->source_entry_id : $job->source_personal_work_entry_id;
        if ($entryId && $job->source_assignment_id) throw ValidationException::withMessages(['source' => '続き元の関係を確認してください。']);
        if ($entryId) return ['type' => 'entry', 'id' => (int) $entryId];
        if ($job->source_assignment_id) return ['type' => 'assignment', 'id' => (int) $job->source_assignment_id];
        return null;
    }

    public function ancestors(User $user, string $type, int $id, ?int $context = null): array
    {
        $items = []; $seen = [];
        for ($depth = 0; $depth < 100; $depth++) {
            $key = $type.':'.$id;
            if (isset($seen[$key])) throw ValidationException::withMessages(['source' => '続き元が循環しています。']);
            $seen[$key] = true;
            $job = $this->resolve($user, $type, $id, $context);
            $items[] = ['type' => $type, 'id' => $id, 'title' => $job->title,
                'completed' => $job instanceof PersonalWorkEntry ? (bool) $job->completed_at : (bool) $job->completed];
            $parent = $this->parent($job);
            if (! $parent) return array_reverse($items);
            ['type' => $type, 'id' => $id] = $parent;
        }
        throw ValidationException::withMessages(['source' => '続き元が多いため確認が必要です。']);
    }

    public function hasEntryChildren(int $id): bool
    {
        return PersonalWorkEntry::where('source_entry_id', $id)->exists()
            || ProjectJobAssignment::where('source_personal_work_entry_id', $id)->exists();
    }

    /** Complete tree, with explicit source identities. No title-based merging. */
    public function series(User $user, int $id, ?int $context = null, bool $withTimes = true, string $type = 'entry'): array
    {
        $ancestors = $this->ancestors($user, $type, $id, $context);
        $queue = [$ancestors[0]]; $seen = []; $items = []; $recordDays = []; $mixed = false;
        while ($queue) {
            $node = array_shift($queue);
            $key = $node['type'].':'.$node['id'];
            if (isset($seen[$key]) || count($seen) >= 100) throw ValidationException::withMessages(['source' => '系列の関係・件数を確認してください。']);
            $seen[$key] = true;
            $job = $this->resolve($user, $node['type'], (int) $node['id'], $context);
            $parent = $this->parent($job);
            $item = ['key' => $key, 'type' => $node['type'], 'id' => (int) $job->id, 'title' => $job->title,
                'parentKey' => $parent ? $parent['type'].':'.$parent['id'] : null,
                'current' => $node['type'] === $type && (int) $job->id === $id,
                'completed' => $job instanceof PersonalWorkEntry ? (bool) $job->completed_at : (bool) $job->completed,
                'date' => null, 'start' => null, 'end' => null, 'time' => null, 'slots' => []];
            if ($job instanceof PersonalWorkEntry && $withTimes) {
                $detail = $this->detail($user, (int) $job->id, $context, $recordDays, false);
                $item['date'] = $detail['work']['work_date'];
                $item['start'] = $detail['work']['display_start'];
                $item['end'] = $detail['work']['display_end'];
                $item['time'] = $detail['time'];
                $item['slots'] = [['date' => $item['date'], 'start' => $item['start'], 'endDate' => $item['date'], 'end' => $item['end']]];
            } elseif (! ($job instanceof PersonalWorkEntry)) {
                $mixed = true;
                if ($withTimes) {
                    $old = $this->assignmentTime($user, $job, $recordDays);
                    $item['time'] = $old['time']; $item['slots'] = $old['slots'];
                    $item['date'] = $old['slots'][0]['date'] ?? null;
                    $item['start'] = $old['slots'][0]['start'] ?? null;
                    $item['end'] = $old['slots'][0]['end'] ?? null;
                }
            }
            $items[] = $item;
            $entryColumn = $job instanceof PersonalWorkEntry ? 'source_entry_id' : 'source_assignment_id';
            $assignmentColumn = $job instanceof PersonalWorkEntry ? 'source_personal_work_entry_id' : 'source_assignment_id';
            $children = PersonalWorkEntry::where($entryColumn, $job->id)->orderBy('id')->limit(101)->pluck('id');
            $oldChildren = ProjectJobAssignment::where($assignmentColumn, $job->id)->orderBy('id')->limit(101)->pluck('id');
            if (count($items) + count($queue) + $children->count() + $oldChildren->count() > 100) throw ValidationException::withMessages(['source' => '系列が多いため確認が必要です。']);
            foreach ($children as $child) $queue[] = ['type' => 'entry', 'id' => (int) $child];
            foreach ($oldChildren as $child) $queue[] = ['type' => 'assignment', 'id' => (int) $child];
        }
        usort($items, fn ($a, $b) => [$a['date'] ?? '', $a['start'] ?? '', $a['key']] <=> [$b['date'] ?? '', $b['start'] ?? '', $b['key']]);
        $totals = null;
        if ($withTimes) {
            $totals = [];
            foreach (['planned', 'lunch', 'afterLunch', 'recorded'] as $field) $totals[$field] = array_sum(array_column(array_column($items, 'time'), $field));
        }
        return ['items' => $items, 'totals' => $totals, 'allCompleted' => ! in_array(false, array_column($items, 'completed'), true),
            'operationsEnabled' => ! $mixed, 'canContinue' => true, 'mixed' => $mixed,
            'completionTargets' => array_map(function ($ancestor) use ($items) {
                $item = collect($items)->firstWhere('key', $ancestor['type'].':'.$ancestor['id']);
                return $ancestor + ['date' => $item['date'], 'start' => $item['start'], 'end' => $item['end']];
            }, $ancestors)];
    }

    private function recordsForDay(User $user, string $date, array &$days): array
    {
        if (! array_key_exists($date, $days)) {
            if (count($days) >= 366) throw ValidationException::withMessages(['source' => '対象の日数が多いため確認が必要です。']);
            if ($date > Carbon::now('Asia/Tokyo')->toDateString()) return $days[$date] = [];
            $day = Carbon::parse($date, 'Asia/Tokyo');
            $people = $this->analysis->analyze($user, collect([$user]), $day->copy()->startOfDay(), $day->copy()->endOfDay(), true, 'trial_time_v3', true, true);
            $days[$date] = collect($people[0]['daily'] ?? [])->firstWhere('date', $date)['record_minutes'] ?? [];
        }
        return $days[$date];
    }

    private function assignmentTime(User $user, ProjectJobAssignment $job, array &$days): array
    {
        $events = Event::where('project_job_assignment_id', $job->id)->whereNull('cancelled_at')
            ->with('projectJobAssignment:id,job_type')->orderBy('id')->limit(1001)->get();
        if ($events->count() > 1000) throw ValidationException::withMessages(['source' => '予定が多いため確認が必要です。']);
        $results = PersonalWorkEntry::where('user_id', $user->id)->whereIn('source_event_id', $events->modelKeys())->get()->groupBy('source_event_id');
        $time = ['planned' => 0, 'lunch' => 0, 'afterLunch' => 0, 'recorded' => 0.0]; $slots = [];
        foreach ($events as $event) {
            if ((int) $event->user_id !== (int) $user->id
                || ($event->company_id !== null && (int) $event->company_id !== (int) $user->company_id)
                || ($event->department_id !== null && (int) $event->department_id !== (int) $user->department_id)) throw new AuthorizationException;
            $from = $this->resolveJstCarbon($event, 'starts_at'); $to = $this->resolveJstCarbon($event, 'ends_at');
            if (! $from || ! $to || $to->lte($from)) continue;
            if ($from->diffInDays($to) > 366) throw ValidationException::withMessages(['source' => '予定の期間が長いため確認が必要です。']);
            $slots[] = ['date' => $from->toDateString(), 'start' => $from->format('H:i'), 'endDate' => $to->toDateString(), 'end' => $to->format('H:i')];
            for ($day = $from->copy()->startOfDay(); $day->lt($to); $day->addDay()) {
                $date = $day->toDateString();
                $start = $from->max($day)->copy(); $end = $to->min($day->copy()->addDay())->copy();
                $minutes = max(0, (int) $start->diffInMinutes($end));
                $startMinute = (int) $day->diffInMinutes($start); $endMinute = (int) $day->diffInMinutes($end);
                $window = $this->schedule->workWindow($user, $date);
                $lunch = max(0, min($endMinute, $window['break_end'] ?? 780) - max($startMinute, $window['break_start'] ?? 720));
                $time['planned'] += $minutes; $time['lunch'] += $lunch; $time['afterLunch'] += $minutes - $lunch;
                $records = $this->recordsForDay($user, $date, $days);
                $dayResults = $results->get($event->id, collect())->filter(fn ($entry) => $entry->work_date->format('Y-m-d') === $date);
                $time['recorded'] += $dayResults->isEmpty() ? ($records['job-'.$event->id.'-'.$user->id] ?? 0)
                    : $dayResults->sum(fn ($entry) => $records['quick-'.$entry->id] ?? 0);
            }
        }
        usort($slots, fn ($a, $b) => [$a['date'], $a['start']] <=> [$b['date'], $b['start']]);
        return ['time' => $time, 'slots' => $slots];
    }

    public function continuationPrefill(User $user, int $id, ?int $context): array
    {
        $job = $this->resolve($user, 'assignment', $id, $context);
        return $this->assignmentPrefill($user, $job, Event::where('project_job_assignment_id', $job->id)->where('visibility', 'private')->exists());
    }

    /** Metadata only, from an already authorized source; no quantity/result/time copying. */
    public function assignmentPrefill(User $user, ProjectJobAssignment $job, bool $private): array
    {
        $code = PersonalWorkRole::forUser($user)['code'] ?? 'other';
        $category = $job->job_type === 'proof' ? 'kousei' : match ($code) {
            'shinko', 'eigyo', 'kousei', 'jimu' => $code, 'operator', 'seisaku' => 'dtp', default => 'other',
        };
        return ['title' => preg_replace('/^【完了】\s*/u', '', (string) $job->title),
            'project_job_id' => $job->project_job_id, 'project_label' => $job->projectJob?->title,
            'work_category' => $category, 'activity_type' => '', 'actual_unit' => $job->amounts_unit === 'file' ? 'ファイル' : 'ページ',
            'entry_visibility' => $job->projectJob?->show_on_department_schedule
                && ! $private ? 'department' : 'private'];
    }

    public function detail(User $user, int $id, ?int $context = null, array &$recordDays = [], bool $withAncestors = true): array
    {
        $entry = $this->resolve($user, 'entry', $id, $context);
        if ($entry->broadProject && ((int) $entry->broadProject->company_id !== (int) $user->company_id
            || (int) $entry->broadProject->department_id !== (int) $user->department_id)) throw new AuthorizationException;
        $date = $entry->work_date->format('Y-m-d');
        $window = $this->schedule->workWindow($user, $date);
        $range = $this->schedule->slotInterval($entry, $window);
        $planned = $range ? max(0, $range[1] - $range[0]) : 0;
        $lunch = $range ? max(0, min($range[1], $window['break_end'] ?? 780) - max($range[0], $window['break_start'] ?? 720)) : 0;
        $recorded = 0.0;
        if ($date <= Carbon::now('Asia/Tokyo')->toDateString()) {
            $recorded = (float) ($this->recordsForDay($user, $date, $recordDays)['quick-'.$entry->id] ?? 0);
        }
        $clock = fn ($minute) => ($minute >= 1440 ? '翌日 ' : '').sprintf('%02d:%02d', intdiv($minute, 60) % 24, $minute % 60);
        return ['work' => [
            'id' => (int) $entry->id, 'title' => $entry->title,
            'project' => $entry->projectJob?->title ?? $entry->project_name_text,
            'classification' => $entry->broadProject?->pathLabel(),
            'display_start' => $range ? $clock($range[0]) : null, 'display_end' => $range ? $clock($range[1]) : null,
            'work_date' => $date, 'planned_start_time' => $entry->planned_start_time ? substr($entry->planned_start_time, 0, 5) : null,
            'planned_end_time' => $entry->planned_end_time ? substr($entry->planned_end_time, 0, 5) : null,
            'work_category' => $entry->work_category, 'activity_type' => $entry->activity_type, 'plan_slot' => $entry->plan_slot,
            'project_job_id' => $entry->project_job_id, 'broad_project_id' => $entry->broad_project_id, 'project_name_text' => $entry->project_name_text,
            'entry_visibility' => $entry->entry_visibility, 'result_note' => $entry->result_note,
            'actual_quantity' => $entry->actual_quantity, 'actual_unit' => $entry->actual_unit, 'diary_id' => $entry->diary_id,
            'completed' => (bool) $entry->completed_at,
        ], 'time' => ['planned' => $planned, 'lunch' => $lunch, 'afterLunch' => max(0, $planned - $lunch), 'recorded' => $recorded],
            'ancestors' => $withAncestors ? $this->ancestors($user, 'entry', $id, $context) : []];
    }
}
