<?php

namespace App\Services;

use App\Http\Controllers\Concerns\CalculatesEventTime;
use App\Models\Event;
use App\Models\PersonalWorkEntry;
use App\Models\ProjectJobAssignment;
use App\Models\User;
use App\Models\WorkflowCell;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;

/** Read-only projection. Never copies work into assignments or updates status. */
class UnifiedMyJobBox
{
    use CalculatesEventTime;

    public function __construct(private UserWorkHub $hub) {}

    public function available(?User $user, ?int $contextCompany = null): bool
    {
        return $this->hub->available($user)
            && ($contextCompany === null || $contextCompany === (int) $user->company_id);
    }

    public function rows(User $user, ?int $contextCompany = null): array
    {
        if (! $this->available($user, $contextCompany)) throw new AuthorizationException;

        $projectScope = fn ($q) => $q->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $user->company_id));
        $broadScope = fn ($q) => $q->where('company_id', $user->company_id)->where('department_id', $user->department_id)->with('parent');
        $assignments = ProjectJobAssignment::where('user_id', $user->id)
            ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $user->company_id))
            ->where(fn ($q) => $q->whereNull('department_id')->orWhere('department_id', $user->department_id))
            ->whereHas('projectJob', $projectScope)
            ->with(['projectJob' => $projectScope, 'projectJob.broadProject' => $broadScope])->orderBy('id')->get()->keyBy('id');
        $workflowIds = WorkflowCell::whereIn('assignment_id', $assignments->keys())->pluck('assignment_id')->flip();
        $events = Event::where('user_id', $user->id)->whereIn('project_job_assignment_id', $assignments->keys())
            ->with('projectJobAssignment:id,job_type')->orderBy('id')->get();
        // An explicit replacement is only valid within the same owner's project.
        // Continuations and proof coordinator references are intentionally not aliases.
        $replacements = [];
        foreach ($assignments as $a) {
            $original = $assignments->get($a->supersedes_assignment_id);
            if ((int) $a->sender_id === (int) $user->id && $original
                && (int) $original->project_job_id === (int) $a->project_job_id
                && (int) $original->sender_id !== (int) $user->id) {
                $replacements[$original->id][] = $a->id;
            }
        }
        // Ambiguous historical replacements remain separate rather than losing a job.
        $aliases = [];
        foreach ($replacements as $original => $ids) {
            if (count($ids) === 1) $aliases[$original] = $ids[0];
        }
        $slots = [];
        foreach ($events as $event) {
            $start = $this->resolveJstCarbon($event, 'starts_at');
            $end = $this->resolveJstCarbon($event, 'ends_at');
            if (! $start || ! $end || $end->lte($start)) continue;
            $id = $aliases[$event->project_job_assignment_id] ?? $event->project_job_assignment_id;
            $slots[$id][] = ['date' => $start->toDateString(), 'start' => $start->format('H:i'),
                'endDate' => $end->toDateString(), 'end' => $end->format('H:i')];
        }
        $rows = [];
        foreach ($assignments as $a) {
            if (isset($aliases[$a->id])) continue;
            $own = (int) $a->sender_id === (int) $user->id;
            $times = $slots[$a->id] ?? [];
            if (! $own && ! $times) continue;
            usort($times, fn ($a, $b) => [$a['date'], $a['start']] <=> [$b['date'], $b['start']]);
            $fallback = $a->desired_end_date?->format('Y-m-d') ?? $a->created_at?->format('Y-m-d');
            $rows[] = ['key' => 'assignment:'.$a->id, 'assignmentId' => $own ? (int) $a->id : null,
                'title' => $a->title, 'project' => $a->projectJob?->title ?? '',
                'classification' => $a->projectJob?->broadProject?->pathLabel() ?? '',
                'kind' => $a->job_type === 'proof' ? '校正' : ($a->supersedes_assignment_id ? '依頼から登録' : ($workflowIds->has($a->id) ? '管理表' : ($a->progress_cell_id ? '進行表' : ($own ? 'マイジョブ' : '依頼ジョブ')))),
                'completed' => (bool) $a->completed, 'status' => $a->completed ? '完了' : ($times ? '予定あり' : '未予定'),
                'slots' => $times, 'fallbackDate' => $fallback, 'quantity' => null,
                'months' => $this->months($times, $fallback), 'sortDate' => $times[0]['date'] ?? $fallback ?? ''];
        }
        $entries = PersonalWorkEntry::where('user_id', $user->id)->where('company_id', $user->company_id)
            ->where('department_id', $user->department_id)->whereNull('source_event_id')
            ->with(['projectJob' => $projectScope, 'broadProject' => $broadScope])->orderBy('id')->get();
        foreach ($entries as $entry) {
            $date = $entry->work_date->format('Y-m-d');
            $rows[] = ['key' => 'entry:'.$entry->id, 'assignmentId' => null, 'title' => $entry->title,
                'project' => $entry->projectJob?->title ?? $entry->project_name_text ?? '',
                'classification' => $entry->broadProject?->pathLabel() ?? '', 'kind' => '簡易入力',
                'completed' => false, 'status' => '登録済み', 'slots' => [['date' => $date,
                    'start' => substr($entry->planned_start_time ?? '', 0, 5), 'endDate' => $date,
                    'end' => substr($entry->planned_end_time ?? '', 0, 5)]],
                'fallbackDate' => null, 'quantity' => $entry->actual_quantity === null ? null : trim($entry->actual_quantity.' '.($entry->actual_unit ?? '')),
                'months' => [substr($date, 0, 7)], 'sortDate' => $date];
        }
        return $rows;
    }

    private function months(array $slots, ?string $fallback): array
    {
        if (! $slots) return $fallback ? [substr($fallback, 0, 7)] : [];
        $months = [];
        foreach ($slots as $slot) {
            $cursor = Carbon::parse($slot['date'], 'Asia/Tokyo')->startOfMonth();
            // End is exclusive: midnight on the first is not work in that month.
            $last = Carbon::parse($slot['endDate'].' '.$slot['end'], 'Asia/Tokyo')->subSecond()->format('Y-m');
            do {
                $months[] = $cursor->format('Y-m');
                $cursor->addMonth();
            } while ($cursor->format('Y-m') <= $last);
        }
        return array_values(array_unique($months));
    }

    public function listing(User $user, ?int $contextCompany, array $filters): array
    {
        $rows = collect($this->rows($user, $contextCompany));
        $months = $rows->flatMap(fn ($row) => $row['months'])->push(Carbon::now('Asia/Tokyo')->format('Y-m'))
            ->unique()->sortDesc()->values()->all();
        $q = trim($filters['q'] ?? '');
        $period = $filters['period'] ?? Carbon::now('Asia/Tokyo')->format('Y-m');
        $hide = (bool) ($filters['hide_completed'] ?? false);
        $filtered = $rows->filter(fn ($row) => ($period === 'all' || in_array($period, $row['months'], true))
            && (! $hide || ! $row['completed'])
            && ($q === '' || mb_stripos(implode(' ', [$row['title'], $row['project'], $row['classification']]), $q) !== false))
            ->sort(fn ($a, $b) => [$b['sortDate'], $b['key']] <=> [$a['sortDate'], $a['key']])->values();
        $total = $filtered->count();
        $last = max(1, (int) ceil($total / 50));
        $page = min($last, max(1, (int) ($filters['page'] ?? 1)));
        $createdKey = null;
        if (! empty($filters['created_entry'])) {
            $key = 'entry:'.(int) $filters['created_entry'];
            $index = $filtered->search(fn ($row) => $row['key'] === $key);
            if ($index !== false) {
                $page = (int) floor($index / 50) + 1;
                $createdKey = $key;
            }
        }
        return ['createdKey' => $createdKey, 'rows' => $filtered->slice(($page - 1) * 50, 50)->values()->all(), 'months' => $months,
            'filters' => ['q' => $q, 'period' => $period, 'hide_completed' => $hide],
            'pagination' => ['page' => $page, 'last' => $last, 'total' => $total]];
    }
}
