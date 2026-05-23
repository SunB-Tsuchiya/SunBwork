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

        $myAssignments = $baseAssignments->paginate($usePeriodFilter ? 500 : 50)->appends($appendParams);

        // Attach a has_progress_cell attribute to each assignment for frontend classification.
        try {
            $myAssignments->getCollection()->transform(function ($item) {
                $has = false;
                try {
                    if (\Illuminate\Support\Facades\Schema::hasTable('progress_cells')) {
                        $has = \App\Models\ProgressCell::where('assignment_id', $item->id)->exists();
                    }
                } catch (\Throwable $_e) {
                    $has = false;
                }
                // If not directly linked, check ancestor chain (source_assignment_id)
                if (!$has) {
                    $cur = $item;
                    for ($i = 0; $i < 20 && $cur && empty($has); $i++) {
                        if (empty($cur->source_assignment_id)) break;
                        $parent = \App\Models\ProjectJobAssignment::find($cur->source_assignment_id);
                        if (!$parent) break;
                        try {
                            if (\Illuminate\Support\Facades\Schema::hasTable('progress_cells')) {
                                $has = \App\Models\ProgressCell::where('assignment_id', $parent->id)->exists();
                            }
                        } catch (\Throwable $_) {
                            $has = false;
                        }
                        $cur = $parent;
                    }
                }
                $item->setAttribute('has_progress_cell', (bool)$has);

                // Attach has_workflow_cell for frontend classification (管理表ジョブ判定)
                $hasWf = false;
                try {
                    if (\Illuminate\Support\Facades\Schema::hasTable('workflow_cells')) {
                        $hasWf = \App\Models\WorkflowCell::where('assignment_id', $item->id)->exists();
                    }
                } catch (\Throwable $_e) {
                    $hasWf = false;
                }
                if (!$hasWf) {
                    $cur = $item;
                    for ($i = 0; $i < 20 && $cur && empty($hasWf); $i++) {
                        if (empty($cur->source_assignment_id)) break;
                        $parent = \App\Models\ProjectJobAssignment::find($cur->source_assignment_id);
                        if (!$parent) break;
                        try {
                            if (\Illuminate\Support\Facades\Schema::hasTable('workflow_cells')) {
                                $hasWf = \App\Models\WorkflowCell::where('assignment_id', $parent->id)->exists();
                            }
                        } catch (\Throwable $_) {
                            $hasWf = false;
                        }
                        $cur = $parent;
                    }
                }
                $item->setAttribute('has_workflow_cell', (bool)$hasWf);

                // 先頭イベントのJST日時を付加（Vue側でのタイムゾーン変換の代わり）
                // 校正ジョブ（job_type='proof'）は starts_at が UTC 保存のため UTC として解釈する
                try {
                    $firstEv = $item->events->first();
                    if ($firstEv) {
                        $raw = $firstEv->getRawOriginal('starts_at');
                        if ($raw) {
                            $isProof = ($item->job_type ?? null) === 'proof';
                            $jst = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $raw, $isProof ? 'UTC' : 'Asia/Tokyo')
                                ->setTimezone('Asia/Tokyo');
                            $item->setAttribute('event_date_jst',  $jst->toDateString());
                            $item->setAttribute('event_start_jst', $jst->format('H:i'));
                        }
                    }
                } catch (\Throwable $_ev) {}

                return $item;
            });
        } catch (\Throwable $_exx) {
            // ignore
        }

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
     * Mark a MyJobBox assignment as completed.
     * Accepts both self-assigned and coordinator-assigned jobs (user must be the assignee).
     */
    public function completeAssignment(Request $request, \App\Models\ProjectJobAssignment $assignment)
    {
        $user = $request->user();
        if (!$user || $assignment->user_id !== $user->id) {
            return response()->json(['error' => 'Access denied'], 403);
        }
        try {
            // completed カラムが存在する場合のみセット（カラム不存在時の SQL エラーを防ぐ）
            if (\Illuminate\Support\Facades\Schema::hasColumn('project_job_assignments', 'completed')) {
                $assignment->completed = true;
            }
            // status_id を 完了 ステータスに更新
            if (\Illuminate\Support\Facades\Schema::hasColumn('project_job_assignments', 'status_id')) {
                try {
                    $status = \Illuminate\Support\Facades\DB::table('statuses')
                        ->where('key', 'completed')
                        ->orWhere('slug', 'completed')
                        ->first();
                    if (!$status) {
                        $statusId = \Illuminate\Support\Facades\DB::table('statuses')->insertGetId([
                            'key' => 'completed', 'slug' => 'completed', 'name' => '完了',
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    } else {
                        $statusId = $status->id;
                    }
                    $assignment->status_id = $statusId;
                } catch (\Throwable $__e) {}
            }
            $assignment->save();

            // ジョブ通知（進行管理表リンクあり → リーダーへ / なし → Coordinator依頼分のみ依頼主＋リーダーへ）
            try {
                $projectJob = $assignment->projectJob
                    ?? \App\Models\ProjectJob::find($assignment->project_job_id);
                if ($projectJob) {
                    $hasProgressLink = \App\Models\ProgressCell::where('assignment_id', $assignment->id)->exists();
                    if (!$hasProgressLink && !empty($assignment->source_assignment_id)) {
                        // 祖先を辿って ProgressCell を探す（深さ最大 20）
                        $cur = $assignment;
                        for ($__i = 0; $__i < 20 && !$hasProgressLink; $__i++) {
                            if (empty($cur->source_assignment_id)) break;
                            $parent = \App\Models\ProjectJobAssignment::find($cur->source_assignment_id);
                            if (!$parent) break;
                            $hasProgressLink = \App\Models\ProgressCell::where('assignment_id', $parent->id)->exists();
                            $cur = $parent;
                        }
                    }
                    if ($hasProgressLink) {
                        \App\Services\JobNotificationService::notifyProgressCompleted($user, $projectJob, $assignment);
                    } else {
                        \App\Services\JobNotificationService::notifyCompleted($user, $assignment, $projectJob);
                    }
                }
            } catch (\Throwable $__eNotify) {
                \Illuminate\Support\Facades\Log::warning('JobNotification dispatch error in completeAssignment', ['error' => $__eNotify->getMessage()]);
            }

            // チェーン上のすべての元ジョブ（祖先）も完了にする
            $current = $assignment;
            $maxDepth = 20;
            for ($i = 0; $i < $maxDepth; $i++) {
                if (empty($current->source_assignment_id)) break;
                $parent = \App\Models\ProjectJobAssignment::find($current->source_assignment_id);
                if (!$parent) break;
                if (!$parent->completed) {
                    $parent->completed = true;
                    $parent->save();
                }
                $current = $parent;
            }

            // progress_cells.completed_at を更新（ジョブ完了と進行表を同期）
            try {
                \App\Models\ProgressCell::where('assignment_id', $assignment->id)
                    ->whereNull('completed_at')
                    ->update(['completed_at' => now()]);
            } catch (\Throwable $__ePc) {
                // non-fatal
            }

            // イベントも完了にする（進行表→イベント同期）
            try {
                $prefix = '【完了】';
                $eventsToComplete = \App\Models\Event::where('project_job_assignment_id', $assignment->id)->get();
                foreach ($eventsToComplete as $evt) {
                    if (strpos($evt->title, $prefix) !== 0) {
                        $evt->title = $prefix . $evt->title;
                        $evt->save();
                    }
                }
            } catch (\Throwable $__eEvtMy) {
                // non-fatal
            }
        } catch (\Throwable $__e) {
            return response()->json(['error' => $__e->getMessage()], 500);
        }
        return response()->json(['success' => true, 'assignment_id' => $assignment->id]);
    }

    /**
     * 続きジョブのチェーン全体を返す（root から末端まで）
     */
    public function chainAssignments(Request $request, \App\Models\ProjectJobAssignment $assignment)
    {
        $user = $request->user();
        if (!$user || $assignment->user_id !== $user->id) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        // 先祖をたどってルートを探す
        $root = $assignment;
        $maxDepth = 20;
        for ($i = 0; $i < $maxDepth; $i++) {
            if (empty($root->source_assignment_id)) break;
            $parent = \App\Models\ProjectJobAssignment::find($root->source_assignment_id);
            if (!$parent) break;
            $root = $parent;
        }

        // ルートから全子孫を収集（BFS）
        $allIds = collect([$root->id]);
        $toProcess = collect([$root->id]);
        for ($i = 0; $i < $maxDepth && $toProcess->isNotEmpty(); $i++) {
            $children = \App\Models\ProjectJobAssignment::whereIn('source_assignment_id', $toProcess->toArray())
                ->pluck('id');
            $children->each(fn($id) => $allIds->push($id));
            $toProcess = $children;
        }

        $chain = \App\Models\ProjectJobAssignment::whereIn('id', $allIds->unique()->toArray())
            ->select(['id', 'title', 'created_at', 'completed', 'desired_end_date', 'source_assignment_id'])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'chain' => $chain,
            'current_id' => $assignment->id,
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
        $assignment->load(['projectJob.client', 'user', 'sender', 'size', 'stage', 'workItemType', 'statusModel', 'difficultyModel']);

        $projectJob = $assignment->projectJob ?? null;

        // 本人または Admin 以上が削除可能
        $canDelete = $assignment->user_id === $user->id
            || $user->isSuperAdmin()
            || $user->isAdmin();

        // 進行管理表との紐付け件数（削除確認UI用）
        $linkedProgressCellCount = 0;
        try {
            $linkedProgressCellCount = \App\Models\ProgressCell::where('assignment_id', $assignment->id)->count();
        } catch (\Throwable $e) {
            // progress_cells テーブルが存在しない場合は無視
        }

        $proofRequested = false;
        try {
            $proofRequested = \App\Models\ProofRequest::where('project_job_assignment_id', $assignment->id)
                ->whereNotIn('status', ['completed'])
                ->exists();
        } catch (\Throwable $e) {}

        return Inertia::render('MyJobBox/Show', [
            'projectJob'              => $projectJob,
            'assignment'              => $assignment,
            'canDelete'               => $canDelete,
            'linkedProgressCellCount' => $linkedProgressCellCount,
            'proofRequested'          => $proofRequested,
        ]);
    }

    /**
     * Delete a MyJobBox assignment.
     */
    public function destroyAssignment(Request $request, \App\Models\ProjectJobAssignmentByMyself $assignment)
    {
        $user = $request->user();
        if (! $user || ($assignment->user_id !== $user->id && ! $user->isSuperAdmin() && ! $user->isAdmin())) {
            abort(403, '削除する権限がありません。');
        }

        // ProgressCell の assignment_id と completed_at をクリア
        try {
            \App\Models\ProgressCell::where('assignment_id', $assignment->id)
                ->update(['assignment_id' => null, 'completed_at' => null]);
        } catch (\Throwable $e) {
            // progress_cells テーブルが存在しない場合は無視
        }

        // WorkflowCell の assignment_id と関連フィールドをクリア
        \App\Models\WorkflowCell::where('assignment_id', $assignment->id)
            ->update([
                'assignment_id'    => null,
                'completed_at'     => null,
                'assigned_user_id' => null,
                'value_user_id'    => null,
            ]);

        $assignment->delete();

        return redirect()->route('user.myjobbox.index')
            ->with('success', 'ジョブ割り当てを削除しました。');
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

    /**
     * Return past MyJob records for the reuse modal (JSON).
     * Filters: mode=date|project, date_range=yesterday|7days|30days,
     *          client_id, project_job_id, hide_completed=1
     */
    public function pastData(Request $request)
    {
        $user = $request->user();
        $mode = $request->query('mode', 'date');
        $hideCompleted = (bool) $request->query('hide_completed', 0);
        $clientId = $request->query('client_id');
        $projectJobId = $request->query('project_job_id');

        $query = ProjectJobAssignmentByMyself::where('user_id', $user->id)
            ->with(['projectJob.client', 'workItemType', 'size', 'stage']);

        if ($hideCompleted) {
            $query->where(function ($q) {
                $q->whereNull('completed')->orWhere('completed', false);
            });
        }

        if ($mode === 'date') {
            $range = $request->query('date_range', 'yesterday');
            $now = Carbon::now();
            if ($range === 'yesterday') {
                $from = $now->copy()->subDay()->startOfDay();
                $to   = $now->copy()->subDay()->endOfDay();
            } elseif ($range === '7days') {
                $from = $now->copy()->subDays(7)->startOfDay();
                $to   = $now->copy()->endOfDay();
            } else { // 30days
                $from = $now->copy()->subDays(30)->startOfDay();
                $to   = $now->copy()->endOfDay();
            }
            $query->where(function ($q) use ($from, $to) {
                $q->whereBetween('desired_end_date', [$from->toDateString(), $to->toDateString()])
                  ->orWhereBetween('created_at', [$from, $to]);
            });
        } else {
            // project mode
            if ($projectJobId) {
                $query->where('project_job_id', $projectJobId);
            } elseif ($clientId) {
                $query->whereHas('projectJob', function ($q) use ($clientId) {
                    $q->where('client_id', $clientId);
                });
            }
        }

        $records = $query->orderBy('created_at', 'desc')->limit(100)->get()
            ->map(function ($m) {
                return [
                    'id'               => $m->id,
                    'title'            => $m->title,
                    'detail'           => $m->detail,
                    'project_job_id'   => $m->project_job_id,
                    'project_job_name' => $m->projectJob?->title ?? $m->projectJob?->name ?? '-',
                    'client_id'        => $m->projectJob?->client_id,
                    'client_name'      => $m->projectJob?->client?->name ?? '-',
                    'work_item_type_id'=> $m->work_item_type_id,
                    'work_item_type'   => $m->workItemType?->name ?? '-',
                    'size_id'          => $m->size_id,
                    'size'             => $m->size?->name ?? '-',
                    'stage_id'         => $m->stage_id,
                    'stage'            => $m->stage?->name ?? '-',
                    'difficulty_id'    => $m->difficulty_id,
                    'estimated_hours'  => $m->estimated_hours,
                    'desired_end_date' => $m->desired_end_date,
                    'desired_time'     => $m->desired_time,
                    'completed'        => (bool) $m->completed,
                    'created_at'       => $m->created_at?->format('Y-m-d'),
                ];
            });

        // Build distinct clients from user's MyJob records (all time)
        $clients = ProjectJobAssignmentByMyself::where('user_id', $user->id)
            ->with('projectJob.client')
            ->get()
            ->map(fn($m) => $m->projectJob?->client)
            ->filter()
            ->unique('id')
            ->map(fn($c) => ['id' => $c->id, 'name' => $c->name])
            ->values();

        // If client_id given, return projects for that client
        $projects = collect();
        if ($clientId) {
            $projects = ProjectJobAssignmentByMyself::where('user_id', $user->id)
                ->whereHas('projectJob', fn($q) => $q->where('client_id', $clientId))
                ->with('projectJob')
                ->get()
                ->map(fn($m) => $m->projectJob)
                ->filter()
                ->unique('id')
                ->map(fn($j) => ['id' => $j->id, 'title' => $j->title ?? $j->name ?? '-'])
                ->values();
        }

        return response()->json([
            'records'  => $records,
            'clients'  => $clients,
            'projects' => $projects,
        ]);
    }

    /**
     * 自分宛の未対応依頼ジョブ一覧を返す（supersede 選択 UI 用）
     * 未対応 = 対応するマイジョブ（supersedes_assignment_id = this.id）がまだ存在しない
     *
     * GET /myjobbox/pending-requests?project_job_id=X（省略可）
     */
    public function pendingRequests(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $projectJobId = $request->query('project_job_id');

        $query = \App\Models\ProjectJobAssignment::where('user_id', $user->id)
            ->where(function ($q) {
                $q->whereColumn('sender_id', '!=', 'user_id')
                  ->orWhereNull('sender_id');
            })
            // まだ superseded されていない（対応するマイジョブが存在しない）
            ->whereDoesntHave('supersededBy')
            ->with(['projectJob.client', 'sender']);

        if ($projectJobId) {
            $query->where('project_job_id', $projectJobId);
        }

        $records = $query->orderBy('created_at', 'desc')->limit(100)->get()
            ->map(fn($a) => [
                'id'               => $a->id,
                'title'            => $a->title,
                'project_job_id'   => $a->project_job_id,
                'project_job_name' => $a->projectJob?->title ?? $a->projectJob?->name ?? '-',
                'client_name'      => $a->projectJob?->client?->name ?? '-',
                'sender_name'      => $a->sender?->name ?? '-',
                'desired_end_date' => $a->desired_end_date?->format('Y-m-d'),
                'completed'        => (bool) $a->completed,
            ]);

        return response()->json(['records' => $records]);
    }
}
