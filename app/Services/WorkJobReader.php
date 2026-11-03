<?php

namespace App\Services;

use App\Models\PersonalWorkEntry;
use App\Models\ProjectJobAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/** Reads existing work by an explicit source reference; never materializes copies. */
class WorkJobReader
{
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

    public function detail(User $user, int $id, ?int $context = null): array
    {
        $entry = $this->resolve($user, 'entry', $id, $context);
        $entry->load('broadProject.parent');
        if ($entry->broadProject && ((int) $entry->broadProject->company_id !== (int) $user->company_id
            || (int) $entry->broadProject->department_id !== (int) $user->department_id)) throw new AuthorizationException;
        $date = $entry->work_date->format('Y-m-d');
        $window = $this->schedule->workWindow($user, $date);
        $range = $this->schedule->slotInterval($entry, $window);
        $planned = $range ? max(0, $range[1] - $range[0]) : 0;
        $lunch = $range ? max(0, min($range[1], $window['break_end'] ?? 780) - max($range[0], $window['break_start'] ?? 720)) : 0;
        $recorded = 0.0;
        if ($date <= Carbon::now('Asia/Tokyo')->toDateString()) {
            $day = Carbon::parse($date, 'Asia/Tokyo');
            $people = $this->analysis->analyze($user, collect([$user]), $day->copy()->startOfDay(), $day->copy()->endOfDay(), true, 'trial_time_v3', true, true);
            $daily = collect($people[0]['daily'] ?? [])->firstWhere('date', $date);
            $recorded = (float) ($daily['record_minutes']['quick-'.$entry->id] ?? 0);
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
            'ancestors' => $this->ancestors($user, 'entry', $id, $context)];
    }
}
