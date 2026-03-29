<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProjectJob;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class ProjectJobController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $q = $request->input('q', '');
        $period = $request->input('period', '');

        $query = ProjectJob::with('client')
            ->where('user_id', $user->id);

        if ($q) {
            $query->where(function ($q2) use ($q) {
                $q2->where('title', 'like', "%{$q}%")
                    ->orWhereHas('client', fn($c) => $c->where('name', 'like', "%{$q}%"));
            });
        }

        if ($period && $period !== 'all') {
            [$y, $m] = explode('-', $period);
            $query->whereYear('created_at', $y)->whereMonth('created_at', $m);
        }

        $jobs = $query->orderBy('created_at', 'desc')->get();

        // 直近12ヶ月の月オプション
        $monthOptions = [];
        for ($i = 0; $i < 12; $i++) {
            $d = now()->subMonths($i);
            $monthOptions[] = [
                'value' => $d->format('Y-m'),
                'label' => $d->format('Y年n月'),
            ];
        }

        $jobid = session('jobid');
        $registerFlags = session('register_flags', []);
        return Inertia::render('Coordinator/ProjectJobs/Index', [
            'jobs' => $jobs,
            'jobid' => $jobid,
            'registerFlags' => $registerFlags,
            'monthOptions' => $monthOptions,
            'q' => $q,
            'period' => $period,
        ]);
    }

    public function complete(Request $request, ProjectJob $projectJob)
    {
        $user = $request->user();
        if (!$user || $projectJob->user_id !== $user->id) {
            return response()->json(['error' => 'Access denied'], 403);
        }
        $projectJob->completed = true;
        $projectJob->save();
        return response()->json(['success' => true, 'id' => $projectJob->id]);
    }

    public function create()
    {
        return Inertia::render('Coordinator/ProjectJobs/Create');
    }

    public function store(Request $request)
    {


        try {
            $data = $request->validate([
                'jobcode' => ['nullable', 'string', 'max:255', 'regex:/^[0-9\-]+$/'],
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

        // スケジュール一覧（Show に表として表示するため）
        $schedules = \App\Models\ProjectSchedule::where('project_job_id', $projectJob->id)
            ->orderBy('start_date')
            ->get(['id', 'name', 'description', 'start_date', 'end_date']);

        // ジョブ履歴: この案件に紐づく job_assignment_messages を全件取得
        $jobHistory = [];
        try {
            $jobHistory = \App\Models\JobAssignmentMessage::select('job_assignment_messages.*')
                ->join('project_job_assignments', 'job_assignment_messages.project_job_assignment_id', '=', 'project_job_assignments.id')
                ->where('project_job_assignments.project_job_id', $projectJob->id)
                ->with([
                    'sender',
                    'projectJobAssignment.projectJob.client',
                    'projectJobAssignment',
                    'projectJobAssignment.statusModel',
                    'message.recipients.user',
                    'message.fromUser',
                    'projectJobAssignment.user',
                ])
                ->orderBy('job_assignment_messages.created_at', 'desc')
                ->get();

            $jobHistory->transform(function ($msg) {
                try {
                    if (isset($msg->projectJobAssignment) && $msg->projectJobAssignment && isset($msg->projectJobAssignment->statusModel) && $msg->projectJobAssignment->statusModel) {
                        $sm = $msg->projectJobAssignment->statusModel;
                        $msg->projectJobAssignment->status = [
                            'id'   => $sm->id,
                            'key'  => $sm->key ?? $sm->slug ?? null,
                            'name' => $sm->name,
                        ];
                    }
                } catch (\Throwable $__e) {
                    // non-fatal
                }
                return $msg;
            });
        } catch (\Throwable $__e) {
            \Illuminate\Support\Facades\Log::warning('Failed to build jobHistory for project job show', ['error' => $__e->getMessage(), 'project_job_id' => $projectJob->id]);
            $jobHistory = [];
        }

        // 未発信の割当（assigned=false）
        $unsentAssignments = $projectJob->projectJobAssignments()
            ->with(['user'])
            ->where('assigned', false)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($a) => [
                'id'               => $a->id,
                'title'            => $a->title,
                'user_id'          => $a->user_id,
                'user_name'        => $a->user?->name,
                'detail'           => $a->detail,
                'desired_end_date' => $a->desired_end_date?->format('Y-m-d'),
                'estimated_hours'  => $a->estimated_hours,
                'created_at'       => $a->created_at?->format('Y-m-d'),
            ]);

        return Inertia::render('Coordinator/ProjectJobs/Show', [
            'job' => $projectJob,
            'members' => $members,
            'jobid' => $jobid,
            'registerFlags' => $registerFlags,
            'hasSchedule' => $hasSchedule,
            'assignmentEvents' => $assignmentEvents,
            'schedules' => $schedules,
            'jobHistory' => $jobHistory,
            'unsentAssignments' => $unsentAssignments,
        ]);
    }

    /**
     * ジョブ分析ページ
     */
    /**
     * ジョブ詳細（旧: ジョブ分析）
     *
     * ── 役割カテゴリの解決順序 ──────────────────────────────────────────
     * 1. assignments.job_role_category（Admin 設定可能カラム）※将来実装
     * 2. assignments.code による既定マッピング（現在はここで決定）
     *    shinko   → coordinator（進行管理）
     *    operator → production（組版・制作）
     *    kousei   → proofreading（校正）
     *    その他   → other
     * ─────────────────────────────────────────────────────────────────────
     */
    public function analysis(ProjectJob $projectJob)
    {
        $projectJob->load(['user', 'client']);

        $assignmentEvents = [];
        try {
            $assignments = \App\Models\ProjectJobAssignment::where('project_job_id', $projectJob->id)
                ->with(['user', 'statusModel', 'stage'])
                ->get();

            // ユーザーの role (assignments テーブル) の name と code を一括取得
            $userAssignmentIds = $assignments->map(fn ($a) => $a->user?->assignment_id)
                ->filter()->unique()->values()->all();

            $assignmentNameMap = [];
            $assignmentCodeMap = [];
            if (!empty($userAssignmentIds)) {
                $roleRecords = \App\Models\Assignment::whereIn('id', $userAssignmentIds)->get(['id', 'name', 'code']);
                $assignmentNameMap = $roleRecords->pluck('name', 'id')->toArray();
                $assignmentCodeMap = $roleRecords->pluck('code', 'id')->toArray();
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn('events', 'project_job_assignment_id')) {
                foreach ($assignments as $a) {
                    $events = \App\Models\Event::where('project_job_assignment_id', $a->id)
                        ->orderBy('starts_at')
                        ->get();

                    foreach ($events as $ev) {
                        $userAssignmentId   = $a->user?->assignment_id ?? null;
                        $userAssignmentName = $userAssignmentId ? ($assignmentNameMap[$userAssignmentId] ?? null) : null;
                        $userAssignmentCode = $userAssignmentId ? ($assignmentCodeMap[$userAssignmentId] ?? null) : null;

                        // 日付（グループキー用）
                        $startVal   = $ev->starts_at ?? null;
                        $eventDate  = null;
                        try {
                            if ($startVal) {
                                $eventDate = \Illuminate\Support\Carbon::parse($startVal)->toDateString();
                            }
                        } catch (\Throwable $_) {}

                        $assignmentEvents[] = [
                            'assignment_id'   => $a->id,
                            'user_id'         => $a->user?->id ?? $a->user_id ?? null,
                            'user_name'       => $a->user?->name ?? null,
                            'assignment_name' => $userAssignmentName ?? $a->title ?? null,
                            'assignment_code' => $userAssignmentCode,
                            // 役割カテゴリ: ① 将来は assignments.job_role_category ② code 既定値
                            'role_category'   => $this->toRoleCategory($userAssignmentCode),
                            'stage_id'        => $a->stage_id ?? null,
                            'stage_name'      => $a->stage?->name ?? null,
                            'stage_sort'      => $a->stage?->sort_order ?? 99,
                            'status_name'     => $a->statusModel?->name ?? null,
                            'date'            => $eventDate,
                            'start'           => $ev->start ?? $ev->starts_at ?? null,
                            'end'             => $ev->end ?? $ev->ends_at ?? null,
                        ];
                    }
                }
            }
        } catch (\Throwable $__e) {
            \Illuminate\Support\Facades\Log::warning('Failed to build assignmentEvents for job detail', [
                'error'          => $__e->getMessage(),
                'project_job_id' => $projectJob->id,
            ]);
            $assignmentEvents = [];
        }

        return Inertia::render('Coordinator/ProjectJobs/Analysis', [
            'job'              => $projectJob,
            'assignmentEvents' => $assignmentEvents,
            // 将来 Admin 設定で roles の label/表示順を変えられる設計にする
            'roleConfig' => [
                ['key' => 'coordinator',  'label' => '進行管理'],
                ['key' => 'production',   'label' => '組版・制作'],
                ['key' => 'proofreading', 'label' => '校正'],
                ['key' => 'other',        'label' => 'その他'],
            ],
        ]);
    }

    /**
     * assignment.code → 役割カテゴリへの既定マッピング
     * 将来: assignments.job_role_category カラム（nullable）を参照し、
     *       null の場合のみここにフォールバックする。
     */
    private function toRoleCategory(?string $code): string
    {
        return match ($code) {
            'shinko'   => 'coordinator',
            'operator' => 'production',
            'kousei'   => 'proofreading',
            default    => 'other',
        };
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
                'jobcode' => ['nullable', 'string', 'max:255', 'regex:/^[0-9\-]+$/'],
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
