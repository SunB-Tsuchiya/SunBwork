<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProjectJob;
use App\Models\User;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProjectJobController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $q = $request->input('q', '');
        $period = $request->input('period', '');

        $query = ProjectJob::with('client')
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereHas('coordinators', fn ($c) => $c->where('users.id', $user->id));
            });

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

    /**
     * ログインユーザーがこの案件のCoordinator（リーダーまたはサブCo）かどうか判定
     * Admin/SuperAdmin/Clerk は全案件許可
     */
    private function isJobCoordinator(ProjectJob $job, User $user): bool
    {
        if ($user->isAdmin() || $user->isSuperAdmin() || $user->isClerk()) {
            return true;
        }
        if ($job->user_id === $user->id) {
            return true;
        }
        return $job->coordinators()->where('users.id', $user->id)->exists();
    }

    /**
     * Coordinator候補:
     *   - user_role = coordinator または clerk、または
     *   - 担当(assignment.code) = 'shinko'（進行管理）のユーザー
     */
    private function coordinatorCandidates(): \Illuminate\Support\Collection
    {
        return User::where('user_role', 'coordinator')
            ->orWhere('user_role', 'clerk')
            ->orWhereHas('assignment', fn ($q) => $q->where('code', 'shinko'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function complete(Request $request, ProjectJob $projectJob)
    {
        $user = $request->user();
        if (!$user || !$this->isJobCoordinator($projectJob, $user)) {
            return response()->json(['error' => 'Access denied'], 403);
        }
        $projectJob->completed = true;
        $projectJob->save();
        return response()->json(['success' => true, 'id' => $projectJob->id]);
    }

    public function uncomplete(Request $request, ProjectJob $projectJob)
    {
        $user = $request->user();
        if (!$user || !$this->isJobCoordinator($projectJob, $user)) {
            return response()->json(['error' => 'Access denied'], 403);
        }
        $projectJob->completed = false;
        $projectJob->save();
        return response()->json(['success' => true, 'id' => $projectJob->id]);
    }

    public function create()
    {
        $sizes = \App\Models\Size::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group']);
        return Inertia::render('Coordinator/ProjectJobs/Create', [
            'coordinatorCandidates' => $this->coordinatorCandidates(),
            'sizes' => $sizes,
        ]);
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'jobcode'             => ['nullable', 'string', 'max:255', 'regex:/^[0-9\-]+$/'],
                'title'               => 'required|string|max:255',
                'user_id'             => 'required|exists:users,id',
                'client_id'           => 'required|exists:clients,id',
                'size_id'             => 'nullable|exists:sizes,id',
                'page_count'          => 'nullable|integer|min:1|max:99999',
                'detail'              => 'nullable|string',
                'sub_coordinator_ids' => 'nullable|array',
                'sub_coordinator_ids.*' => 'exists:users,id',
            ]);

            $subIds = Arr::pull($data, 'sub_coordinator_ids', []);
            $job = ProjectJob::create($data);

            // リーダー自身はピボットに入れない（重複回避）
            $syncIds = array_values(array_filter($subIds, fn ($id) => $id != $job->user_id));
            if (!empty($syncIds)) {
                $job->coordinators()->sync($syncIds);
            }

            return redirect()->route('coordinator.project_jobs.show', $job->id)
                ->with('jobid', $job->id)
                ->with('register_flags', ['teammember', 'schedule']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        }
    }

    public function show(Request $request, ProjectJob $projectJob)
    {
        $jobid = session('jobid');
        $registerFlags = session('register_flags', []);
        // reload projectJob with team members and their user relation, and also ensure user and client relations are loaded
        $projectJob->load(['teamMembers.user', 'user', 'client', 'coordinators', 'size']);
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

        $subCoordinators = $projectJob->coordinators->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]);

        // 進行管理表一覧
        $progressSheets = $projectJob->progressSheets()
            ->select(['id', 'name', 'sort_order', 'created_at'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($s) => [
                'id'         => $s->id,
                'name'       => $s->name,
                'sort_order' => $s->sort_order,
                'created_at' => $s->created_at?->format('Y-m-d'),
            ]);

        // 進行表に紐づいているアサインメント ID 一覧（ジョブ履歴の分類に使用）
        $sheetLinkedAssignmentIds = [];
        try {
            $sheetLinkedAssignmentIds = \Illuminate\Support\Facades\DB::table('progress_cells')
                ->join('progress_rows', 'progress_rows.id', '=', 'progress_cells.row_id')
                ->join('progress_sheets', 'progress_sheets.id', '=', 'progress_rows.sheet_id')
                ->where('progress_sheets.project_job_id', $projectJob->id)
                ->whereNotNull('progress_cells.assignment_id')
                ->pluck('progress_cells.assignment_id')
                ->unique()
                ->values()
                ->toArray();
        } catch (\Throwable $e) {
            $sheetLinkedAssignmentIds = [];
        }

        // 進行表リンク済みで jobHistory に未登録の割当を合成エントリとして追加
        // （進行表から登録された自己割当は job_assignment_messages を経由しないため jobHistory に出ない）
        try {
            $existingAids = collect(
                $jobHistory instanceof \Illuminate\Support\Collection ? $jobHistory->toArray() : (array) $jobHistory
            )->map(fn ($m) => (int) ($m['project_job_assignment_id'] ?? $m['project_job_assignment']['id'] ?? 0))
             ->filter()->unique()->toArray();

            // ① 進行表リンク済みで jobHistory に未登録
            $missingFromSheet = array_values(array_diff(
                array_map('intval', $sheetLinkedAssignmentIds),
                $existingAids
            ));

            // ② 独自ジョブ（自己割当: sender_id = user_id）で jobHistory に未登録
            //    進行表経由でカレンダーに登録したジョブはJAMを持たないためここで補完する
            $selfAssignedIds = \App\Models\ProjectJobAssignment::where('project_job_id', $projectJob->id)
                ->whereColumn('sender_id', 'user_id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->toArray();
            $missingFromSelf = array_values(array_diff($selfAssignedIds, $existingAids));

            $missingIds = array_values(array_unique(array_merge($missingFromSheet, $missingFromSelf)));

            if (!empty($missingIds)) {
                $missings = \App\Models\ProjectJobAssignment::whereIn('id', $missingIds)
                    ->with(['user', 'statusModel'])
                    ->get();

                // synth 作成時に直接イベントを引く（後段の event 付加に依存しない）
                $synthEventMap = DB::table('events')
                    ->whereIn('project_job_assignment_id', $missingIds)
                    ->whereNotNull('starts_at')
                    ->orderBy('starts_at')
                    ->get(['id', 'project_job_assignment_id', 'starts_at', 'ends_at'])
                    ->keyBy('project_job_assignment_id');

                $synths = $missings->map(function ($a) use ($synthEventMap) {
                    $sm = $a->statusModel;
                    $ev = $synthEventMap[$a->id] ?? null;
                    return [
                        'id'                        => null,
                        'event_id'                  => $ev ? $ev->id : null,
                        'event_starts_at'           => $ev ? $ev->starts_at : null,
                        'event_ends_at'             => $ev ? $ev->ends_at : null,
                        'project_job_assignment_id' => $a->id,
                        'subject'                   => $a->title,
                        'body'                      => null,
                        'created_at'                => $a->created_at?->toISOString(),
                        'read_at'                   => $a->read_at,
                        'sender'                    => $a->user
                            ? ['id' => $a->user->id, 'name' => $a->user->name]
                            : null,
                        'message'                   => null,
                        'project_job_assignment'    => [
                            'id'               => $a->id,
                            'title'            => $a->title,
                            'user_id'          => $a->user_id,
                            'sender_id'        => $a->sender_id,
                            'desired_end_date' => $a->desired_end_date?->format('Y-m-d'),
                            'start_time'       => $a->start_time,
                            'completed'        => (bool) $a->completed,
                            'scheduled'        => (bool) ($a->scheduled ?? false),
                            'scheduled_at'     => $a->scheduled_at,
                            'read_at'          => $a->read_at,
                            'status'           => $sm
                                ? ['id' => $sm->id, 'key' => $sm->key ?? $sm->slug ?? null, 'name' => $sm->name]
                                : null,
                            'user'             => $a->user
                                ? ['id' => $a->user->id, 'name' => $a->user->name]
                                : null,
                        ],
                    ];
                })->toArray();

                $base = $jobHistory instanceof \Illuminate\Support\Collection
                    ? $jobHistory->toArray()
                    : (array) $jobHistory;
                $jobHistory = array_merge($base, $synths);
            }
        } catch (\Throwable $_) {
            // non-fatal
        }

        // jobHistory の各エントリにカレンダーイベント情報を付加（作業日表示用）
        try {
            $jhArr = $jobHistory instanceof \Illuminate\Support\Collection ? $jobHistory->toArray() : (array) $jobHistory;
            $assignmentIds = collect($jhArr)
                ->map(fn ($m) => (int) ($m['project_job_assignment_id'] ?? $m['project_job_assignment']['id'] ?? 0))
                ->filter()->unique()->values()->toArray();

            if (!empty($assignmentIds)) {
                $eventsByAssignment = DB::table('events')
                    ->whereIn('project_job_assignment_id', $assignmentIds)
                    ->whereNotNull('starts_at')
                    ->orderBy('starts_at')
                    ->get(['id', 'project_job_assignment_id', 'starts_at', 'ends_at'])
                    ->keyBy('project_job_assignment_id');

                $jobHistory = array_map(function ($m) use ($eventsByAssignment) {
                    $aid = (int) ($m['project_job_assignment_id'] ?? $m['project_job_assignment']['id'] ?? 0);
                    if ($aid && isset($eventsByAssignment[$aid])) {
                        $ev = $eventsByAssignment[$aid];
                        $m['event_id']        = $ev->id;
                        $m['event_starts_at'] = $ev->starts_at ? \Carbon\Carbon::parse($ev->starts_at)->utc()->toIso8601String() : null;
                        $m['event_ends_at']   = $ev->ends_at   ? \Carbon\Carbon::parse($ev->ends_at)->utc()->toIso8601String()   : null;
                    }
                    return $m;
                }, $jhArr);
            } else {
                $jobHistory = $jhArr;
            }
        } catch (\Throwable $_) {
            // non-fatal
        }

        // 自己割当と依頼割当の重複除外
        // 優先度1: supersedes_assignment_id による明示的な紐付けがあれば依頼割当を除外
        // 優先度2: 同一ユーザー・同一タイトルの自己割当がある場合も除外（タイトル一致 fallback）
        try {
            // ① 全エントリの assignment_id を収集し supersedes_assignment_id を一括取得
            $allAids = collect((array) $jobHistory)
                ->map(fn ($m) => (int) ($m['project_job_assignment_id'] ?? ($m['project_job_assignment']['id'] ?? 0)))
                ->filter()->unique()->values()->toArray();

            $supersededIds = [];
            if (!empty($allAids)) {
                $supersededIds = \App\Models\ProjectJobAssignment::whereIn('supersedes_assignment_id', $allAids)
                    ->whereColumn('sender_id', 'user_id')
                    ->pluck('supersedes_assignment_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()->values()->toArray();
            }

            // ② タイトル一致 fallback: 自己割当キーセット（user_id:title）を収集
            $selfKeys = [];
            foreach ((array) $jobHistory as $entry) {
                $pja      = is_array($entry) ? ($entry['project_job_assignment'] ?? []) : [];
                $uid      = (int) ($pja['user_id']   ?? 0);
                $sid      = (int) ($pja['sender_id'] ?? 0);
                $title    = mb_strtolower(trim((string) ($pja['title'] ?? $entry['subject'] ?? '')));
                if ($uid && $sid && $uid === $sid && $title !== '') {
                    $selfKeys["{$uid}:{$title}"] = true;
                }
            }

            $jobHistory = array_values(array_filter((array) $jobHistory, function ($entry) use ($supersededIds, $selfKeys) {
                $pja   = is_array($entry) ? ($entry['project_job_assignment'] ?? []) : [];
                $uid   = (int) ($pja['user_id']   ?? 0);
                $sid   = (int) ($pja['sender_id'] ?? 0);
                $aid   = (int) ($pja['id'] ?? $entry['project_job_assignment_id'] ?? 0);
                $title = mb_strtolower(trim((string) ($pja['title'] ?? $entry['subject'] ?? '')));
                $isRequest = $uid && $sid && $uid !== $sid; // 依頼割当（sender≠user）
                if (!$isRequest) return true;
                // 優先度1: supersedes_assignment_id で明示的に置き換え済み
                if ($aid && in_array($aid, $supersededIds, true)) return false;
                // 優先度2: 同一ユーザー・同一タイトルの自己割当が存在（タイトル一致 fallback）
                if ($uid && $title !== '' && isset($selfKeys["{$uid}:{$title}"])) return false;
                return true;
            }));
        } catch (\Throwable $_) {
            // non-fatal
        }

        // テンプレート一覧（シート作成モーダル用）
        $userId = $request->user()->id;
        $sheetTemplates = \App\Models\ProgressTemplate::where('is_shared', true)
            ->orWhere('created_by', $userId)
            ->orderByDesc('updated_at')
            ->get(['id', 'name']);

        // ステージ一覧（組版・校正セット方式モーダル用）
        $stages = \App\Models\Stage::orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'sort_order']);

        // 校正依頼履歴
        $proofHistory = [];
        try {
            $proofHistory = \App\Models\ProofRequest::with(['requester', 'proofreader', 'proofCoordinator'])
                ->where('project_job_id', $projectJob->id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn ($pr) => [
                    'id'               => $pr->id,
                    'title'            => $pr->title,
                    'status'           => $pr->status,
                    'deadline'         => $pr->deadline?->toIso8601String(),
                    'requester_name'   => $pr->requester?->name,
                    'proofreader_name' => $pr->proofreader?->name,
                    'coordinator_name' => $pr->proofCoordinator?->name,
                    'created_at'       => $pr->created_at?->toIso8601String(),
                ])->toArray();
        } catch (\Throwable $_) {
            $proofHistory = [];
        }

        return Inertia::render('Coordinator/ProjectJobs/Show', [
            'job' => $projectJob,
            'subCoordinators' => $subCoordinators,
            'members' => $members,
            'jobid' => $jobid,
            'registerFlags' => $registerFlags,
            'hasSchedule' => $hasSchedule,
            'assignmentEvents' => $assignmentEvents,
            'schedules' => $schedules,
            'jobHistory' => $jobHistory,
            'progressSheets' => $progressSheets,
            'sheetTemplates' => $sheetTemplates,
            'stages' => $stages,
            'sheetLinkedAssignmentIds' => $sheetLinkedAssignmentIds,
            'proofHistory' => $proofHistory,
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
        $projectJob->load(['teamMembers.user', 'user', 'client', 'coordinators']);

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

        $jobArray = array_merge($projectJob->toArray(), [
            'teammember'          => $members,
            'sub_coordinator_ids' => $projectJob->coordinators->pluck('id')->toArray(),
        ]);

        $sizes = \App\Models\Size::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group']);
        return Inertia::render('Coordinator/ProjectJobs/Edit', [
            'job'                  => $jobArray,
            'coordinatorCandidates' => $this->coordinatorCandidates(),
            'sizes' => $sizes,
        ]);
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
                'jobcode'               => ['nullable', 'string', 'max:255', 'regex:/^[0-9\-]+$/'],
                'title'                 => 'required|string|max:255',
                'user_id'               => 'required|exists:users,id',
                'client_id'             => 'required|exists:clients,id',
                'size_id'               => 'nullable|exists:sizes,id',
                'page_count'            => 'nullable|integer|min:1|max:99999',
                'detail'                => 'nullable|string',
                'schedule'              => 'nullable|array',
                'sub_coordinator_ids'   => 'nullable|array',
                'sub_coordinator_ids.*' => 'exists:users,id',
            ]);

            $subIds = Arr::pull($data, 'sub_coordinator_ids', []);
            Arr::pull($data, 'schedule'); // project_jobs テーブルに schedule カラムなし
            $projectJob->update($data);

            // リーダー自身はピボットに入れない
            $syncIds = array_values(array_filter($subIds, fn ($id) => $id != $projectJob->user_id));
            $projectJob->coordinators()->sync($syncIds);

            return redirect()->route('coordinator.project_jobs.index');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        }
    }

    public function destroy(ProjectJob $projectJob)
    {
        // 関連レコードがある場合は削除不可
        $assignmentCount = \App\Models\ProjectJobAssignment::where('project_job_id', $projectJob->id)->count();
        if ($assignmentCount > 0) {
            if (request()->expectsJson()) {
                return response()->json(['error' => "この案件には {$assignmentCount} 件のジョブ割り当てがあるため削除できません。先に割り当てを削除してください。"], 422);
            }
            return redirect()->back()->with('error', "この案件には {$assignmentCount} 件のジョブ割り当てがあるため削除できません。");
        }

        $sheetCount = \App\Models\ProgressSheet::where('project_job_id', $projectJob->id)->count();
        if ($sheetCount > 0) {
            if (request()->expectsJson()) {
                return response()->json(['error' => "この案件には {$sheetCount} 件の進行管理表があるため削除できません。先に進行管理表を削除してください。"], 422);
            }
            return redirect()->back()->with('error', "この案件には {$sheetCount} 件の進行管理表があるため削除できません。");
        }

        $projectJob->delete();

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }
        return redirect()->route('coordinator.project_jobs.index')->with('reload', true);
    }

    /**
     * 案件名の重複チェック（登録前のフロント呼び出し用 JSON エンドポイント）
     * 同一クライアントで類似タイトルが存在する場合に返す（警告のみ、保存は任意）
     */
    public function checkDuplicate(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'title'      => 'required|string|max:255',
            'client_id'  => 'required|integer|exists:clients,id',
            'exclude_id' => 'nullable|integer',
        ]);

        $inputNormalized = $this->normalizeTitle($request->title);
        $clientId        = (int) $request->client_id;

        $query = ProjectJob::where('client_id', $clientId)
            ->select('id', 'title', 'created_at');

        if ($request->filled('exclude_id')) {
            $query->where('id', '!=', (int) $request->exclude_id);
        }

        $duplicates = $query->get()
            ->filter(fn($j) => $this->normalizeTitle($j->title) === $inputNormalized)
            ->map(fn($j) => [
                'id'         => $j->id,
                'title'      => $j->title,
                'created_at' => $j->created_at?->format('Y年n月'),
            ])
            ->values();

        return response()->json(['duplicates' => $duplicates]);
    }

    /**
     * 案件タイトルを正規化して重複比較用文字列を返す。
     * - 全角英数字・スペース → 半角
     * - スペース・中黒・記号を除去
     * - 小文字化
     */
    private function normalizeTitle(string $title): string
    {
        // 全角英数字・スペース → 半角
        $title = mb_convert_kana($title, 'as', 'UTF-8');
        // スペース・全角スペース・中黒を除去
        $title = preg_replace('/[\s　・\-_]+/u', '', $title);
        // 小文字化
        return mb_strtolower($title, 'UTF-8');
    }
}
