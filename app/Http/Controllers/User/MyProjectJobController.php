<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProjectJob;
use Inertia\Inertia;
use App\Models\ProjectJobAssignmentByMyself;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Log;

class MyProjectJobController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $q = $request->input('q');
        $periodParam = $request->query('period');
        $usePeriodFilter = true;
        $periodModel = $periodParam;
        if ($periodParam === null) {
            $periodModel = now()->format('Y-m');
        } elseif ($periodParam === '' || $periodParam === 'all') {
            $usePeriodFilter = false;
        }

        $periodStart = null;
        $periodEnd = null;
        if ($usePeriodFilter) {
            try {
                $periodStart = Carbon::createFromFormat('Y-m', $periodModel)->startOfMonth();
                $periodEnd = Carbon::createFromFormat('Y-m', $periodModel)->endOfMonth();
            } catch (\Throwable $__e) {
                $periodModel = now()->format('Y-m');
                $periodStart = Carbon::createFromFormat('Y-m', $periodModel)->startOfMonth();
                $periodEnd = Carbon::createFromFormat('Y-m', $periodModel)->endOfMonth();
            }
        }

        $jobs = ProjectJob::with('client')
            ->where('user_id', $user->id)
            ->get();
        // ユーザー自身が登録した「自分用割当」を取得（ページネーション）
        $baseAssignments = ProjectJobAssignmentByMyself::where('user_id', $user->id)
            ->with(['projectJob.client', 'user', 'statusModel', 'events' => function ($q) {
                $q->orderBy('starts_at');
            }])
            ->orderBy('created_at', 'desc');

        if ($q) {
            $baseAssignments->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('detail', 'like', "%{$q}%");
            });
        }

        if ($usePeriodFilter && $periodStart && $periodEnd) {
            $baseAssignments->whereBetween(
                DB::raw('COALESCE(desired_end_date, created_at)'),
                [$periodStart, $periodEnd]
            );
        }

        $appendParams = ['period' => $periodModel];
        if ($q !== null && $q !== '') {
            $appendParams['q'] = $q;
        }

        $myAssignments = $baseAssignments->paginate(20)->appends($appendParams);

        $monthValues = ProjectJobAssignmentByMyself::where('user_id', $user->id)
            ->selectRaw("DATE_FORMAT(COALESCE(desired_end_date, created_at), '%Y-%m') as ym")
            ->groupBy('ym')
            ->orderBy('ym', 'desc')
            ->pluck('ym');

        $monthOptions = $monthValues
            ->filter()
            ->map(function ($ym) {
                try {
                    $label = Carbon::createFromFormat('Y-m', $ym)->format('Y年n月');
                } catch (\Throwable $__e) {
                    $label = $ym;
                }
                return ['value' => $ym, 'label' => $label];
            })
            ->values();
        // フラッシュデータからjobid/register_flagsを取得
        $jobid = session('jobid');
        $registerFlags = session('register_flags', []);
        return Inertia::render('MyJobBox/Index', [
            'jobs' => $jobs,
            'myAssignments' => $myAssignments,
            'monthOptions' => $monthOptions,
            'period' => $periodModel,
            'q' => $q,
            'jobid' => $jobid,
            'registerFlags' => $registerFlags,
        ]);
    }

    /**
     * Show a single ProjectJobAssignmentByMyself assignment for the current user.
     */
    public function showAssignment(\App\Models\ProjectJobAssignmentByMyself $assignment, Request $request)
    {
        $user = $request->user();
        if (! $user || $assignment->user_id !== $user->id) {
            abort(403, 'Access denied.');
        }

        // eager load relations used by the frontend
        $assignment->load(['projectJob.client', 'user', 'size', 'stage', 'workItemType', 'statusModel']);

        $projectJob = $assignment->projectJob ?? null;

        return Inertia::render('MyJobBox/Show', [
            'projectJob' => $projectJob,
            'assignment' => $assignment,
        ]);
    }

    public function create()
    {
        return Inertia::render('Coordinator/ProjectJobs/Create');
    }

    public function store(Request $request)
    {


        try {
            $data = $request->validate([
                'jobcode' => ['required', 'string', 'max:255', 'regex:/^[0-9\-]+$/'],
                'title' => 'required|string|max:255',
                'user_id' => 'required|exists:users,id',
                'client_id' => 'required|exists:clients,id',
                'detail' => 'nullable|string',
            ]);
            // detailはプレーンテキストで保存
            $job = ProjectJob::create($data);
            // 新規作成時はメンバー/スケジュール未設定のため案内を出す
            $registerFlags = ['teammember', 'schedule'];
            return redirect()->route('coordinator.project_jobs.show', $job->id)
                ->with('jobid', $job->id)
                ->with('register_flags', $registerFlags);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        }
    }

    public function show(ProjectJob $projectJob)
    {
        $jobid = session('jobid');
        $registerFlags = session('register_flags', []);
        // reload projectJob with team members and their user relation, and also ensure user and client relations are loaded
        $projectJob->load(['teamMembers.user', 'user', 'client']);
        $members = $projectJob->teamMembers->map(function ($m) {
            return [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'user' => $m->user ? [
                    'id' => $m->user->id,
                    'name' => $m->user->name,
                    'department_id' => $m->user->department_id,
                    'assignment_id' => $m->user->assignment_id,
                ] : null,
            ];
        });

        // Determine if this project has any schedules defined (project_schedules)
        $hasSchedule = \App\Models\ProjectSchedule::where('project_job_id', $projectJob->id)->exists();

        // Build a flattened list of actual performed work rows by joining
        // ProjectJobAssignment -> Event when events are linked via
        // events.project_job_assignment_id. Each row represents one event
        // performed for an assignment and includes user/assignment/status/start/end.
        $assignmentEvents = [];
        try {
            // load assignments with user and status relation
            $assignments = \App\Models\ProjectJobAssignment::where('project_job_id', $projectJob->id)
                ->with(['user', 'statusModel'])
                ->get();

            // gather assignment ids referenced by users (users.assignment_id) so we can bulk load names
            $userAssignmentIds = $assignments->map(function ($a) {
                return $a->user?->assignment_id ?? null;
            })->filter()->unique()->values()->all();

            $assignmentNameMap = [];
            if (!empty($userAssignmentIds)) {
                $assignmentNameMap = \App\Models\Assignment::whereIn('id', $userAssignmentIds)->pluck('name', 'id')->toArray();
            }

            foreach ($assignments as $a) {
                // only attempt to fetch events if the events table has the linking column
                if (\Illuminate\Support\Facades\Schema::hasColumn('events', 'project_job_assignment_id')) {
                    $events = \App\Models\Event::where('project_job_assignment_id', $a->id)
                        ->orderBy('starts_at')
                        ->get();
                    foreach ($events as $ev) {
                        // prefer the user's assignment name (from assignments table) when available
                        $userAssignmentName = null;
                        try {
                            $userAssignmentId = $a->user?->assignment_id ?? null;
                            if ($userAssignmentId && isset($assignmentNameMap[$userAssignmentId])) {
                                $userAssignmentName = $assignmentNameMap[$userAssignmentId];
                            }
                        } catch (\Throwable $_) {
                            // ignore and fallback below
                        }

                        $assignmentEvents[] = [
                            'assignment_id' => $a->id,
                            'project_job_id' => $a->project_job_id,
                            'user_id' => $a->user?->id ?? $a->user_id ?? null,
                            'user_name' => $a->user?->name ?? null,
                            // Use assignments.name (user's assignment) when present; fallback to project job assignment title
                            'assignment_name' => $userAssignmentName ?? $a->title ?? null,
                            'status_name' => $a->statusModel?->name ?? null,
                            // Event model exposes start/end accessors which return ISO strings
                            'start' => $ev->start ?? $ev->starts_at ?? null,
                            'end' => $ev->end ?? $ev->ends_at ?? null,
                        ];
                    }
                }
            }
        } catch (\Throwable $__e) {
            // be defensive: do not break the show page if this fails; log and continue with empty array
            try {
                \Illuminate\Support\Facades\Log::warning('Failed to build assignmentEvents for project job show', ['error' => $__e->getMessage(), 'project_job_id' => $projectJob->id]);
            } catch (\Throwable $_) {
            }
            $assignmentEvents = [];
        }

        // hasSchedule computed and returned to Inertia props

        return Inertia::render('Coordinator/ProjectJobs/Show', [
            'job' => $projectJob,
            'members' => $members,
            'jobid' => $jobid,
            'registerFlags' => $registerFlags,
            'hasSchedule' => $hasSchedule,
            'assignmentEvents' => $assignmentEvents,
        ]);
    }

    /**
     * ジョブ分析ページ
     */
    public function analysis(ProjectJob $projectJob)
    {
        // we'll reuse the same assignmentEvents building logic as show
        $projectJob->load(['teamMembers.user', 'user', 'client']);
        $members = $projectJob->teamMembers->map(function ($m) {
            return [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'user' => $m->user ? [
                    'id' => $m->user->id,
                    'name' => $m->user->name,
                    'department_id' => $m->user->department_id,
                    'assignment_id' => $m->user->assignment_id,
                ] : null,
            ];
        });

        $assignmentEvents = [];
        try {
            $assignments = \App\Models\ProjectJobAssignment::where('project_job_id', $projectJob->id)
                ->with(['user', 'statusModel'])
                ->get();

            $userAssignmentIds = $assignments->map(function ($a) {
                return $a->user?->assignment_id ?? null;
            })->filter()->unique()->values()->all();

            $assignmentNameMap = [];
            if (!empty($userAssignmentIds)) {
                $assignmentNameMap = \App\Models\Assignment::whereIn('id', $userAssignmentIds)->pluck('name', 'id')->toArray();
            }

            foreach ($assignments as $a) {
                if (\Illuminate\Support\Facades\Schema::hasColumn('events', 'project_job_assignment_id')) {
                    $events = \App\Models\Event::where('project_job_assignment_id', $a->id)->orderBy('starts_at')->get();
                    foreach ($events as $ev) {
                        $userAssignmentName = null;
                        try {
                            $userAssignmentId = $a->user?->assignment_id ?? null;
                            if ($userAssignmentId && isset($assignmentNameMap[$userAssignmentId])) {
                                $userAssignmentName = $assignmentNameMap[$userAssignmentId];
                            }
                        } catch (\Throwable $_) {
                        }

                        $assignmentEvents[] = [
                            'assignment_id' => $a->id,
                            'project_job_id' => $a->project_job_id,
                            'user_id' => $a->user?->id ?? $a->user_id ?? null,
                            'user_name' => $a->user?->name ?? null,
                            'assignment_name' => $userAssignmentName ?? $a->title ?? null,
                            // include stage info from ProjectJobAssignment (may be null)
                            'stage_id' => $a->stage_id ?? null,
                            'stage_name' => $a->stage?->name ?? null,
                            'status_name' => $a->statusModel?->name ?? null,
                            'start' => $ev->start ?? $ev->starts_at ?? null,
                            'end' => $ev->end ?? $ev->ends_at ?? null,
                        ];
                    }
                }
            }
        } catch (\Throwable $__e) {
            try {
                \Illuminate\Support\Facades\Log::warning('Failed to build assignmentEvents for project job analysis', ['error' => $__e->getMessage(), 'project_job_id' => $projectJob->id]);
            } catch (\Throwable $_) {
            }
            $assignmentEvents = [];
        }

        return Inertia::render('Coordinator/ProjectJobs/Analysis', [
            'job' => $projectJob,
            'members' => $members,
            'assignmentEvents' => $assignmentEvents,
        ]);
    }

    public function edit(ProjectJob $projectJob)
    {
        // Ensure team members (and their user relation) are loaded so the Edit page
        // receives the same `teammember` shape as the Show page expects.
        $projectJob->load(['teamMembers.user', 'user', 'client']);

        $members = $projectJob->teamMembers->map(function ($m) {
            return [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'user' => $m->user ? [
                    'id' => $m->user->id,
                    'name' => $m->user->name,
                    'department_id' => $m->user->department_id,
                    'assignment_id' => $m->user->assignment_id,
                ] : null,
            ];
        });

        // pass job as an array merged with teammember so client sees `job.teammember`
        $jobArray = array_merge($projectJob->toArray(), ['teammember' => $members]);

        return Inertia::render('Coordinator/ProjectJobs/Edit', ['job' => $jobArray]);
    }

    /**
     * Shortcut: redirect to the ProjectSchedules calendar view for the given project.
     *
     * This keeps frontend route calls like route('coordinator.project_jobs.schedule', { projectJob: id })
     * working while centralizing redirect logic in the controller (consistent with other routes).
     */
    public function schedule(ProjectJob $projectJob)
    {
        return redirect()->route('coordinator.project_schedules.calendar', ['project_job_id' => $projectJob->id]);
    }

    public function update(Request $request, ProjectJob $projectJob)
    {
        try {
            $data = $request->validate([
                'jobcode' => ['required', 'string', 'max:255', 'regex:/^[0-9\-]+$/'],
                'title' => 'required|string|max:255',
                'user_id' => 'required|exists:users,id',
                'client_id' => 'required|exists:clients,id',
                'detail' => 'nullable|string',
                'schedule' => 'nullable|array',
            ]);
            // detailはプレーンテキストで保存
            $projectJob->update($data);
            return redirect()->route('coordinator.project_jobs.index');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        }
    }

    public function destroy(ProjectJob $projectJob)
    {
        $projectJob->delete();
        // Inertiaリダイレクト時にフロントでリロードを促すため、フラッシュメッセージを渡す
        return redirect()->route('coordinator.project_jobs.index')->with('reload', true);
    }
}
