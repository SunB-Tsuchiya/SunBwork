<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\PersonalWorkEntry;
use App\Models\ProjectJob;
use App\Models\ProjectJobAssignment;
use App\Models\ProgressCell;
use App\Models\User;
use App\Services\PersonalWorkSchedule;
use App\Support\PersonalWorkActivities;
use App\Support\PersonalWorkPoints;
use App\Support\PersonalWorkRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Inertia\Inertia;

class DepartmentWorkController extends Controller
{
    public function __construct(private PersonalWorkSchedule $schedule) {}

    public function analysisPage(Request $request)
    {
        abort_unless($this->schedule->available($request->user()), 403);
        $date = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']])['date'] ?? now()->toDateString();

        return Inertia::render('Schedule/WorkAnalysis', ['initialDate' => $date]);
    }

    public function index(Request $request)
    {
        $viewer = $request->user();
        abort_unless($this->schedule->available($viewer), 403);
        $date = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']])['date'] ?? now()->toDateString();

        $users = User::where('company_id', $viewer->company_id)
            ->where('department_id', $viewer->department_id)
            ->with(['assignment:id,code', 'userSetting'])
            ->ordered()->get(['id', 'name', 'company_id', 'department_id', 'assignment_id', 'sort_order']);
        $userIds = $users->pluck('id')->all();
        $entries = PersonalWorkEntry::whereIn('user_id', $userIds)
            ->whereDate('work_date', $date)
            ->with(['projectJob.coordinators:id', 'projectJob:id,title,company_id,user_id,show_on_department_schedule'])
            ->get()->groupBy('user_id');
        $events = $this->schedule->eventsForUsers($userIds, $date);
        $progressAssignmentIds = ProgressCell::whereIn('assignment_id', $events->pluck('project_job_assignment_id')->filter()->unique())
            ->pluck('assignment_id')->map(fn ($id) => (int) $id)->all();
        $accepted = DB::table('schedule_attendees')
            ->whereIn('user_id', $userIds)->where('status', 'accepted')
            ->whereIn('event_id', $events->pluck('id'))
            ->get(['event_id', 'user_id'])->groupBy('event_id');

        $eventByUser = collect($userIds)->mapWithKeys(fn ($id) => [$id => collect()]);
        $materializedSources = $events->filter(fn ($event) => $event->is_materialized_copy && $event->source_schedule_event_id)
            ->groupBy('user_id')->map(fn ($copies) => $copies->pluck('source_schedule_event_id')->map(fn ($id) => (int) $id));
        foreach ($events as $event) {
            if ($eventByUser->has($event->user_id)) {
                $eventByUser[$event->user_id]->push($event);
            }
            if ($event->is_company_event) {
                foreach ($accepted->get($event->id, collect()) as $attendee) {
                    if ($attendee->user_id !== $event->user_id && $eventByUser->has($attendee->user_id)
                        && ! $materializedSources->get($attendee->user_id, collect())->contains((int) $event->id)) {
                        $eventByUser[$attendee->user_id]->push($event);
                    }
                }
            }
        }

        $people = $users->map(function (User $user) use ($viewer, $date, $entries, $eventByUser, $accepted, $progressAssignmentIds) {
            $personEvents = $eventByUser->get($user->id, collect())->unique('id')->values();
            $allEntries = $entries->get($user->id, collect());
            $personEntries = $allEntries->whereNull('source_event_id');
            $eventResults = $allEntries->whereNotNull('source_event_id')->keyBy('source_event_id');
            $occupancy = $this->schedule->occupancy($user, $date, $personEvents, $personEntries);
            $window = $this->schedule->workWindow($user, $date);
            $items = $personEvents->map(fn ($event) => $this->schedule->eventForViewer(
                $event, $viewer, $date, $user->id, $eventResults->get($event->id),
                $accepted->get($event->id, collect())->contains('user_id', $viewer->id),
                in_array((int) $event->project_job_assignment_id, $progressAssignmentIds, true)
            ))
                ->concat($personEntries->map(fn ($entry) => $this->schedule->entryForViewer($entry, $viewer, $window)))
                ->values();
            $results = $items->filter(fn ($item) => ! $item['private'] && filled($item['result_note']));
            $summary = $results->groupBy('work_category')->map(fn ($group) => [
                'count' => $group->count(),
                'quantities' => $group->filter(fn ($item) => $item['actual_quantity'] !== null && filled($item['actual_unit']))
                    ->groupBy('actual_unit')->map(fn ($unitItems) => $unitItems->sum('actual_quantity')),
            ]);
            return [
                'id' => $user->id,
                'name' => $user->name,
                'assignment' => $user->assignment?->code,
                'occupancy' => $occupancy,
                'items' => $items,
                'results' => $summary,
            ];
        });

        return response()->json([
            'date' => $date,
            'people' => $people,
        ]);
    }

    public function options(Request $request)
    {
        $viewer = $request->user();
        abort_unless($this->schedule->available($viewer), 403);
        $params = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'selected_id' => ['nullable', 'integer'],
        ]);
        $term = trim($params['q'] ?? '');
        $base = fn () => ProjectJob::query()
            ->where('company_id', $viewer->company_id)
            ->where(function ($query) use ($viewer) {
                $query->where('show_on_department_schedule', true)
                    ->orWhere('user_id', $viewer->id)
                    ->orWhereHas('coordinators', fn ($q) => $q->where('users.id', $viewer->id))
                    ->orWhereHas('teamMembers', fn ($q) => $q->where('user_id', $viewer->id))
                    ->orWhereHas('projectJobAssignments', fn ($q) => $q->where('user_id', $viewer->id));
            });
        $matching = function ($query) use ($term) {
            if ($term === '') return;
            foreach (preg_split('/\s+/u', $term) as $word) {
                $like = '%'.addcslashes($word, '%_\\').'%';
                $query->where(function ($q) use ($like) {
                    $q->where('title', 'like', $like)
                        ->orWhere('jobcode', 'like', $like)
                        ->orWhereHas('client', fn ($client) => $client->where('name', 'like', $like));
                });
            }
        };
        $fields = ['id', 'title', 'jobcode', 'client_id', 'show_on_department_schedule'];
        $load = fn ($query) => $query->with('client:id,name')->get($fields);
        $team = $load($base()->whereHas('teamMembers', fn ($q) => $q->where('user_id', $viewer->id))
            ->when($term !== '', $matching)->orderBy('completed')->orderByDesc('updated_at')->limit(10));

        $recentIds = PersonalWorkEntry::where('user_id', $viewer->id)->whereNotNull('project_job_id')
            ->orderByDesc('work_date')->orderByDesc('id')->limit(100)
            ->pluck('project_job_id')
            ->merge(ProjectJobAssignment::where('user_id', $viewer->id)->whereNotNull('project_job_id')
                ->orderByDesc('created_at')->limit(100)->pluck('project_job_id'))
            ->unique()->take(20)->values();
        $recent = $recentIds->isEmpty() ? collect() : $load($base()->whereIn('id', $recentIds)
            ->when($term !== '', $matching)->limit(20))->sortBy(fn ($project) => $recentIds->search($project->id))->values();

        $others = $term === '' ? collect() : $load($base()->whereNotIn('id', $team->pluck('id')->merge($recent->pluck('id')))
            ->where($matching)->orderBy('completed')->orderByDesc('updated_at')->limit(20));
        $selected = empty($params['selected_id']) ? collect() : $load($base()->whereKey($params['selected_id']));
        $projects = $selected->concat($team)->concat($recent)->concat($others)->unique('id')->take(30)
            ->map(function ($project) use ($team, $recent) {
                return [
                    'id' => $project->id,
                    'title' => $project->title,
                    'jobcode' => $project->jobcode,
                    'client_name' => $project->client?->name,
                    'group' => $team->contains('id', $project->id) ? 'team'
                        : ($recent->contains('id', $project->id) ? 'recent' : 'search'),
                ];
            })->values();

        $recentTitles = PersonalWorkEntry::where('user_id', $viewer->id)
            ->whereNull('source_event_id')->whereNotNull('title')
            ->orderByDesc('work_date')->orderByDesc('id')->limit(100)
            ->get(['title', 'project_job_id'])
            ->concat(ProjectJobAssignment::where('user_id', $viewer->id)
                ->whereColumn('sender_id', 'user_id')->whereNotNull('title')
                ->orderByDesc('created_at')->limit(100)->get(['title', 'project_job_id']))
            ->map(fn ($entry) => ['title' => trim($entry->title), 'project_job_id' => $entry->project_job_id])
            ->filter(fn ($entry) => $entry['title'] !== '')
            ->unique('title')->take(15)->values();

        return response()->json([
            'projects' => $projects,
            'recent_titles' => $recentTitles,
            'recent_units' => PersonalWorkEntry::where('user_id', $viewer->id)
                ->whereNotNull('actual_unit')->orderByDesc('updated_at')->orderByDesc('id')
                ->limit(100)->pluck('actual_unit')->map(fn ($unit) => trim($unit))
                ->filter()->unique()->take(10)->values(),
            'default_category' => PersonalWorkEntry::where('user_id', $viewer->id)
                ->orderByDesc('work_date')->orderByDesc('id')->value('work_category')
                ?? ($viewer->isClerk() ? 'jimu' : match ($viewer->assignment?->code) {
                'shinko' => 'shinko', 'eigyo' => 'eigyo', 'kousei' => 'kousei',
                'operator', 'seisaku' => 'dtp', default => 'other',
            }),
        ]);
    }

    public function mine(Request $request)
    {
        $user = $request->user();
        abort_unless($this->schedule->available($user), 403);
        $range = $request->validate([
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
        ]);
        abort_if(Carbon::parse($range['start'])->diffInDays(Carbon::parse($range['end'])) > 62, 422);
        $entries = PersonalWorkEntry::where('user_id', $user->id)
            ->whereBetween('work_date', [$range['start'], $range['end']])
            ->whereNull('source_event_id')->with('projectJob:id,title')
            ->orderBy('work_date')->get();

        $windows = [];
        $events = $entries->map(function (PersonalWorkEntry $entry) use ($user, &$windows) {
            $date = $entry->work_date->toDateString();
            if (! array_key_exists($date, $windows)) $windows[$date] = $this->schedule->workWindow($user, $date);
            $interval = $this->schedule->slotInterval($entry, $windows[$date]);
            if (! $interval || $interval[1] <= $interval[0]) return null;
            $day = Carbon::parse($date, 'Asia/Tokyo')->startOfDay();
            return [
                'id' => 'personal-work-'.$entry->id,
                'title' => $this->schedule->entryCalendarTitle($entry),
                'starts_at' => $day->copy()->addMinutes($interval[0])->format('Y-m-d\TH:i:s'),
                'ends_at' => $day->copy()->addMinutes($interval[1])->format('Y-m-d\TH:i:s'),
                'is_own' => true,
                '_source' => 'personal-work',
                '_custom_color' => PersonalWorkActivities::COLOR,
                '_custom_text_color' => PersonalWorkActivities::TEXT_COLOR,
                '_custom_border_color' => PersonalWorkActivities::BORDER_COLOR,
                '_work_entry' => [
                    'id' => $entry->id, 'work_date' => $date,
                    'work_category' => $entry->work_category, 'plan_slot' => $entry->plan_slot,
                    'activity_type' => $entry->activity_type,
                    'planned_start_time' => $entry->planned_start_time ? substr($entry->planned_start_time, 0, 5) : null,
                    'planned_end_time' => $entry->planned_end_time ? substr($entry->planned_end_time, 0, 5) : null,
                    'project_job_id' => $entry->project_job_id, 'project_name_text' => $entry->project_name_text,
                    'title' => $entry->title, 'entry_visibility' => $entry->entry_visibility,
                    'actual_quantity' => $entry->actual_quantity, 'actual_unit' => $entry->actual_unit,
                    'actual_minutes' => $entry->actual_minutes, 'result_note' => $entry->result_note,
                    'diary_id' => $entry->diary_id,
                ],
            ];
        })->filter()->values();

        return response()->json(['events' => $events]);
    }

    public function analysis(Request $request)
    {
        $viewer = $request->user();
        abort_unless($this->schedule->available($viewer), 403);
        $params = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'period' => ['required', Rule::in(['day', 'week', 'month'])],
        ]);
        $day = Carbon::createFromFormat('!Y-m-d', $params['date'], 'Asia/Tokyo');
        $start = match ($params['period']) {
            'week' => $day->copy()->startOfWeek(),
            'month' => $day->copy()->startOfMonth(),
            default => $day->copy(),
        };
        $end = match ($params['period']) {
            'week' => $day->copy()->endOfWeek(),
            'month' => $day->copy()->endOfMonth(),
            default => $day->copy(),
        };
        $users = User::where('company_id', $viewer->company_id)
            ->where('department_id', $viewer->department_id)
            ->with('assignment:id,code,name')->ordered()->get(['id', 'name', 'user_role', 'assignment_id', 'sort_order']);
        $entries = PersonalWorkEntry::where('company_id', $viewer->company_id)
            ->where('department_id', $viewer->department_id)
            ->whereIn('user_id', $users->pluck('id'))
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->where('actual_minutes', '>', 0)
            ->whereNotNull('activity_type')->where('activity_type', '!=', '')
            ->whereNotNull('result_note')->whereRaw('TRIM(result_note) <> ?', [''])
            ->with([
                'projectJob.coordinators:id', 'projectJob:id,title,company_id,user_id,show_on_department_schedule',
                'sourceEvent.user:id,company_id',
                'sourceEvent.projectJobAssignment.projectJob.coordinators:id',
                'sourceEvent.projectJobAssignment.projectJob:id,title,company_id,user_id,show_on_department_schedule',
            ])
            ->get()->filter(function (PersonalWorkEntry $entry) use ($viewer) {
                if ($entry->source_event_id) {
                    if (! $entry->sourceEvent) return false;
                    return ! $this->schedule->eventForViewer(
                        $entry->sourceEvent, $viewer, $entry->work_date->toDateString(), (int) $entry->user_id, $entry
                    )['private'];
                }
                return ! $this->schedule->entryForViewer($entry, $viewer)['private'];
            })->filter(fn (PersonalWorkEntry $entry) => PersonalWorkPoints::points($entry) > 0);

        $people = $users->map(function (User $user) use ($entries) {
            $own = $entries->where('user_id', $user->id);
            $activities = $own->groupBy(fn ($entry) => $entry->work_category.'|'.($entry->activity_type ?: 'legacy'))
                ->map(function ($group) {
                    $first = $group->first();
                    return [
                        'work_category' => $first->work_category,
                        'activity_type' => $first->activity_type,
                        'minutes' => $group->sum('actual_minutes'),
                        'points' => round($group->sum(fn ($entry) => PersonalWorkPoints::points($entry)), 1),
                        'coefficient' => PersonalWorkPoints::coefficient($first->activity_type),
                        'records' => $group->count(),
                        'days' => $group->pluck('work_date')->map(fn ($date) => $date->toDateString())->unique()->count(),
                        'quantities' => $group->filter(fn ($entry) => $entry->actual_quantity !== null && filled($entry->actual_unit))
                            ->groupBy('actual_unit')->map(fn ($unitRows) => $unitRows->sum('actual_quantity')),
                    ];
                })->values();
            return [
                'id' => $user->id, 'name' => $user->name,
                'role' => PersonalWorkRole::forUser($user),
                'minutes' => $own->sum('actual_minutes'),
                'points' => round($own->sum(fn ($entry) => PersonalWorkPoints::points($entry)), 1),
                'records' => $own->count(),
                'recorded_days' => $own->pluck('work_date')->map(fn ($date) => $date->toDateString())->unique()->count(),
                'activities' => $activities,
            ];
        });
        return response()->json([
            'period' => $params['period'],
            'start' => $start->toDateString(), 'end' => $end->toDateString(),
            'point_rule' => [
                'points_per_hour' => PersonalWorkPoints::POINTS_PER_HOUR,
                'default_coefficient' => PersonalWorkPoints::DEFAULT_COEFFICIENT,
                'coefficients' => PersonalWorkPoints::COEFFICIENTS,
            ],
            'people' => $people,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($this->schedule->available($user), 403);
        $data = $this->validatedEntry($request, $user);
        $entry = PersonalWorkEntry::create($data + [
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'department_id' => $user->department_id,
        ]);
        return response()->json(['id' => $entry->id], 201);
    }

    public function update(Request $request, PersonalWorkEntry $entry)
    {
        $user = $request->user();
        abort_unless($this->schedule->available($user) && (int) $entry->user_id === (int) $user->id, 403);
        abort_if($entry->source_event_id, 422);
        $data = $this->validatedEntry($request, $user);
        abort_if($entry->diary_id && $entry->work_date->toDateString() !== $data['work_date'], 422, '日報へ取り込んだ作業の日付は変更できません。');
        $entry->update($data);
        return response()->json(['id' => $entry->id]);
    }

    public function destroy(Request $request, PersonalWorkEntry $entry)
    {
        $user = $request->user();
        abort_unless($this->schedule->available($user) && (int) $entry->user_id === (int) $user->id, 403);
        abort_if($entry->diary_id, 422, '日報へ取り込んだ記録は日報編集画面で変更してください。');
        $entry->delete();
        return response()->noContent();
    }

    private function validatedEntry(Request $request, User $user): array
    {
        $data = $request->validate([
            'work_date' => ['required', 'date_format:Y-m-d'],
            'work_category' => ['required', Rule::in(['shinko', 'eigyo', 'dtp', 'kousei', 'jimu', 'other'])],
            'activity_type' => ['nullable', Rule::in(PersonalWorkActivities::codes())],
            'plan_slot' => ['required', Rule::in(['am', 'pm', 'full', 'hour'])],
            'planned_start_time' => ['required_if:plan_slot,hour', 'nullable', 'date_format:H:i'],
            'planned_end_time' => ['required_if:plan_slot,hour', 'nullable', 'date_format:H:i', 'after:planned_start_time'],
            'title' => ['nullable', 'string', 'max:255'],
            'project_name_text' => ['nullable', 'string', 'max:255'],
            'project_job_id' => ['nullable', 'integer', 'exists:project_jobs,id'],
            'entry_visibility' => ['required', Rule::in(['department', 'private'])],
            'actual_quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'actual_unit' => ['nullable', 'string', 'max:30'],
            'result_note' => ['nullable', 'string', 'max:2000'],
        ]);
        if (isset($data['actual_quantity']) && blank($data['actual_unit'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['actual_unit' => '数量を入力した場合は単位も入力してください。']);
        }
        if (isset($data['actual_unit'])) $data['actual_unit'] = trim($data['actual_unit']);
        $data['actual_minutes'] = $this->schedule->plannedMinutes(
            new PersonalWorkEntry($data), $this->schedule->workWindow($user, $data['work_date'])
        );
        if (filled($data['result_note'] ?? null)) {
            if (blank($data['activity_type'] ?? null) || ! $data['actual_minutes']) {
                throw \Illuminate\Validation\ValidationException::withMessages(['result_note' => '結果を記録する場合は作業種類と有効な予定時間を指定してください。']);
            }
            $data['result_note'] = trim($data['result_note']);
        }
        if (! PersonalWorkActivities::allows($data['work_category'], $data['activity_type'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['activity_type' => '作業区分に合う作業種類を選んでください。']);
        }
        if ($data['plan_slot'] !== 'hour') {
            $data['planned_start_time'] = null;
            $data['planned_end_time'] = null;
        } else {
            foreach (['planned_start_time', 'planned_end_time'] as $field) {
                [$hour, $minute] = array_map('intval', explode(':', $data[$field]));
                if ($minute % 15 !== 0) {
                    throw \Illuminate\Validation\ValidationException::withMessages([$field => '15分単位で指定してください。']);
                }
            }
        }
        if (! empty($data['project_job_id'])) {
            $permitted = ProjectJob::whereKey($data['project_job_id'])
                ->where('company_id', $user->company_id)
                ->where(function ($query) use ($user) {
                    $query->where('show_on_department_schedule', true)
                        ->orWhere('user_id', $user->id)
                        ->orWhereHas('coordinators', fn ($q) => $q->where('users.id', $user->id))
                        ->orWhereHas('teamMembers', fn ($q) => $q->where('user_id', $user->id))
                        ->orWhereHas('projectJobAssignments', fn ($q) => $q->where('user_id', $user->id));
                })->exists();
            if (! $permitted) abort(403);
            $data['project_name_text'] = null;
        }
        return $data;
    }
}
