<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\ProjectJob;
use App\Models\ProjectJobAssignment;
use App\Models\WorkRecordTrial;
use App\Models\WorkRecordTrialDay;
use App\Services\PersonalWorkSchedule;
use App\Support\PersonalWorkActivities;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class WorkRecordTrialController extends Controller
{
    private const STATES = ['worked', 'handed_off', 'waiting'];

    public function __construct(private PersonalWorkSchedule $schedule) {}

    public function page(Request $request)
    {
        $this->authorizeTrial($request);
        $date = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']])['date'] ?? now('Asia/Tokyo')->toDateString();

        return Inertia::render('Schedule/WorkRecordTrial', ['initialDate' => $date]);
    }

    public function index(Request $request)
    {
        $this->authorizeTrial($request);
        $date = $request->validate(['date' => ['required', 'date_format:Y-m-d']])['date'];
        $user = $request->user();
        $rows = WorkRecordTrial::where('user_id', $user->id)->whereDate('work_date', $date)
            ->with(['projectJob:id,title', 'projectJobAssignment:id,title'])
            ->orderBy('id')->get();
        $recent = WorkRecordTrial::where('user_id', $user->id)
            ->with(['projectJob:id,title', 'projectJobAssignment:id,title'])
            ->orderByDesc('work_date')->orderByDesc('id')
            ->limit(100)->get()->unique('series_id')->take(12)->values();
        $myJobs = ProjectJobAssignment::selfAssigned()->where('user_id', $user->id)
            ->with(['projectJob:id,title,client_id', 'projectJob.client:id,name'])
            ->orderByDesc('id')->limit(80)->get();
        $referencedIds = $rows->concat($recent)->pluck('project_job_assignment_id')->filter()->unique();
        $missingIds = $referencedIds->diff($myJobs->pluck('id'));
        if ($missingIds->isNotEmpty()) {
            $olderJobs = ProjectJobAssignment::selfAssigned()->where('user_id', $user->id)
                ->whereIn('id', $missingIds)->with(['projectJob:id,title,client_id', 'projectJob.client:id,name'])->get();
            $myJobs = $myJobs->concat($olderJobs);
        }
        $day = WorkRecordTrialDay::where('user_id', $user->id)->where('work_date', $date)->first();

        return response()->json([
            'date' => $date,
            'records' => $rows->map(fn (WorkRecordTrial $row) => $this->present($row))->values(),
            'recent' => $recent->map(fn (WorkRecordTrial $row) => $this->present($row))->values(),
            'my_jobs' => $myJobs->map(fn (ProjectJobAssignment $job) => $this->presentMyJob($job))->values(),
            'day_review' => [
                'reviewed' => $day?->reviewed_at !== null,
                'missing_minutes_count' => $rows->filter(fn (WorkRecordTrial $row) => $row->approximate_minutes === null)->count(),
                'entered_minutes_total' => $rows->sum('approximate_minutes'),
            ],
        ]);
    }

    public function reviewDay(Request $request)
    {
        $this->authorizeTrial($request);
        $date = $request->validate([
            'work_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Tokyo')->toDateString()],
        ])['work_date'];
        $userId = $request->user()->id;
        if (! WorkRecordTrial::where('user_id', $userId)->where('work_date', $date)->exists()) {
            throw ValidationException::withMessages(['work_date' => 'この日の作業記録を入力してから確認してください。']);
        }

        WorkRecordTrialDay::updateOrCreate(
            ['user_id' => $userId, 'work_date' => $date],
            ['reviewed_at' => now('Asia/Tokyo')],
        );

        return response()->json(['reviewed' => true]);
    }

    public function myJobOptions(Request $request)
    {
        $this->authorizeTrial($request);
        $term = trim($request->validate(['q' => ['required', 'string', 'max:100']])['q']);
        if ($term === '') return response()->json(['my_jobs' => []]);

        $query = ProjectJobAssignment::selfAssigned()->where('user_id', $request->user()->id);
        foreach (preg_split('/\s+/u', $term) as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';
            $query->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhereHas('projectJob', fn ($p) => $p->where('title', 'like', $like))
                    ->orWhereHas('projectJob.client', fn ($c) => $c->where('name', 'like', $like));
            });
        }

        $myJobs = $query->with(['projectJob:id,title,client_id', 'projectJob.client:id,name'])
            ->orderBy('completed')->orderByDesc('id')->limit(30)->get();

        return response()->json(['my_jobs' => $myJobs
            ->map(fn (ProjectJobAssignment $job) => $this->presentMyJob($job))->values()]);
    }

    public function store(Request $request)
    {
        $this->authorizeTrial($request);
        $user = $request->user();
        $data = $this->validatedRecord($request);
        $this->authorizeMyJob($data, $user);
        if (! $data['project_job_assignment_id']) {
            $this->authorizeProject($data, $user);
        }
        $continueFromId = $data['continue_from_id'] ?? null;
        unset($data['continue_from_id']);
        $previous = $continueFromId ? WorkRecordTrial::where('user_id', $user->id)->findOrFail($continueFromId) : null;
        $sameWork = $previous
            && (int) ($previous->project_job_assignment_id ?? 0) === (int) ($data['project_job_assignment_id'] ?? 0)
            && (int) ($previous->project_job_id ?? 0) === (int) ($data['project_job_id'] ?? 0)
            && trim($previous->project_name_text ?? '') === trim($data['project_name_text'] ?? '')
            && $previous->work_category === $data['work_category']
            && $previous->activity_type === $data['activity_type']
            && trim($previous->title ?? '') === trim($data['title'] ?? '');
        if ($previous && ! $sameWork) {
            throw ValidationException::withMessages(['continue_from_id' => '続きとして記録する作業が変わっています。もう一度選んでください。']);
        }
        $entry = DB::transaction(function () use ($data, $user, $previous) {
            $entry = WorkRecordTrial::create($data + [
                'user_id' => $user->id,
                'company_id' => $user->company_id,
                'department_id' => $user->department_id,
                'series_id' => $previous?->series_id ?? (string) Str::uuid(),
            ]);
            $this->reopenDay($user->id, $data['work_date']);

            return $entry;
        });

        return response()->json(['id' => $entry->id], 201);
    }

    public function update(Request $request, WorkRecordTrial $record)
    {
        $this->authorizeOwner($request, $record);
        $data = $this->validatedRecord($request);
        $this->authorizeMyJob($data, $request->user());
        if (! $data['project_job_assignment_id']) {
            $this->authorizeProject($data, $request->user());
        }
        unset($data['continue_from_id']);
        $previousDate = $record->work_date->toDateString();
        DB::transaction(function () use ($record, $data, $request, $previousDate) {
            $record->update($data);
            $this->reopenDay($request->user()->id, $previousDate);
            if ($previousDate !== $data['work_date']) {
                $this->reopenDay($request->user()->id, $data['work_date']);
            }
        });

        return response()->json(['id' => $record->id]);
    }

    public function destroy(Request $request, WorkRecordTrial $record)
    {
        $this->authorizeOwner($request, $record);
        DB::transaction(function () use ($request, $record) {
            $date = $record->work_date->toDateString();
            $record->delete();
            $this->reopenDay($request->user()->id, $date);
        });

        return response()->noContent();
    }

    private function authorizeTrial(Request $request): void
    {
        abort_unless($request->user() && $this->schedule->available($request->user()), 403);
    }

    private function authorizeOwner(Request $request, WorkRecordTrial $record): void
    {
        $this->authorizeTrial($request);
        abort_unless((int) $record->user_id === (int) $request->user()->id, 403);
    }

    private function reopenDay(int $userId, string $date): void
    {
        WorkRecordTrialDay::where('user_id', $userId)->where('work_date', $date)
            ->whereNotNull('reviewed_at')->update(['reviewed_at' => null]);
    }

    private function validatedRecord(Request $request): array
    {
        $data = $request->validate([
            'work_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Tokyo')->toDateString()],
            'recorded_time' => ['nullable', 'date_format:H:i'],
            'project_job_id' => ['nullable', 'integer', 'exists:project_jobs,id'],
            'project_job_assignment_id' => ['nullable', 'integer', 'exists:project_job_assignments,id'],
            'project_name_text' => ['nullable', 'string', 'max:255'],
            'work_category' => ['required', Rule::in(['shinko', 'eigyo', 'dtp', 'kousei', 'jimu', 'other'])],
            'activity_type' => ['required', Rule::in(PersonalWorkActivities::codes())],
            'title' => ['nullable', 'string', 'max:255'],
            'progress_state' => ['nullable', Rule::in(self::STATES)],
            'approximate_minutes' => ['nullable', 'integer', 'min:1', 'max:720'],
            'note' => ['nullable', 'string', 'max:2000'],
            'continue_from_id' => ['nullable', 'integer'],
        ]);
        $data = array_merge([
            'recorded_time' => null, 'project_job_id' => null, 'project_job_assignment_id' => null,
            'project_name_text' => null,
            'title' => null, 'progress_state' => null, 'approximate_minutes' => null, 'note' => null,
            'continue_from_id' => null,
        ], $data);
        if (! PersonalWorkActivities::allows($data['work_category'], $data['activity_type'])) {
            throw ValidationException::withMessages(['activity_type' => '作業区分に合う作業種類を選んでください。']);
        }
        $data['title'] = trim($data['title'] ?? '') ?: null;
        $data['note'] = trim($data['note'] ?? '') ?: null;
        $data['project_name_text'] = trim($data['project_name_text'] ?? '') ?: null;
        if (! $data['project_job_assignment_id'] && ! $data['project_job_id'] && ! $data['project_name_text'] && ! $data['title']) {
            throw ValidationException::withMessages(['title' => '案件または作業名を入力してください。']);
        }
        if ($data['project_job_id']) $data['project_name_text'] = null;
        $data['recorded_time'] = $data['recorded_time'] ?: null;

        return $data;
    }

    private function authorizeMyJob(array &$data, $user): void
    {
        if (! $data['project_job_assignment_id']) return;
        $job = ProjectJobAssignment::selfAssigned()->where('user_id', $user->id)
            ->find($data['project_job_assignment_id']);
        abort_unless($job, 403);
        $data['project_job_id'] = $job->project_job_id;
        $data['project_name_text'] = null;
    }

    private function authorizeProject(array $data, $user): void
    {
        if (! $data['project_job_id']) return;
        $allowed = ProjectJob::whereKey($data['project_job_id'])
            ->where('company_id', $user->company_id)
            ->where(function ($query) use ($user) {
                $query->where('show_on_department_schedule', true)
                    ->orWhere('user_id', $user->id)
                    ->orWhereHas('coordinators', fn ($q) => $q->where('users.id', $user->id))
                    ->orWhereHas('teamMembers', fn ($q) => $q->where('user_id', $user->id))
                    ->orWhereHas('projectJobAssignments', fn ($q) => $q->where('user_id', $user->id));
            })->exists();
        abort_unless($allowed, 403);
    }

    private function present(WorkRecordTrial $row): array
    {
        return [
            'id' => $row->id,
            'work_date' => $row->work_date->toDateString(),
            'recorded_time' => $row->recorded_time ? substr($row->recorded_time, 0, 5) : null,
            'project_job_id' => $row->project_job_id,
            'project_job_assignment_id' => $row->project_job_assignment_id,
            'my_job_title' => $row->projectJobAssignment?->title,
            'project_name' => $row->projectJob?->title ?: $row->project_name_text,
            'project_name_text' => $row->project_name_text,
            'work_category' => $row->work_category,
            'activity_type' => $row->activity_type,
            'title' => $row->title,
            'progress_state' => $row->progress_state,
            'approximate_minutes' => $row->approximate_minutes,
            'note' => $row->note,
            'series_id' => $row->series_id,
        ];
    }

    private function presentMyJob(ProjectJobAssignment $job): array
    {
        return [
            'id' => $job->id,
            'title' => $job->title,
            'project_job_id' => $job->project_job_id,
            'project_name' => $job->projectJob?->title,
            'client_name' => $job->projectJob?->client?->name,
            'completed' => (bool) $job->completed,
        ];
    }
}
