<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\CalculatesEventTime;
use App\Models\Client;
use App\Models\PrepresSalesRep;
use App\Services\OcrSpaceService;
use App\Services\PrepressImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\ProjectJob;
use App\Models\ProjectSchedule;
use App\Models\ProgressSheet;
use App\Models\ProgressRow;
use App\Models\ProgressCell;
use App\Models\ProjectTeamMember;
use App\Models\User;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProjectJobController extends Controller
{
    use CalculatesEventTime;

    public function __construct(
        private PrepressImageService $imageService,
        private OcrSpaceService $ocrService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $q = $request->input('q', '');
        $period = $request->input('period', '');

        // SuperAdmin: グローバルモードでは会社を選択させる
        if ($user->isSuperAdmin()) {
            $contextId = session('superadmin_context.company_id');
            if ($contextId === null) {
                return Inertia::render('Coordinator/ProjectJobs/Index', [
                    'jobs'          => [],
                    'favoriteJobs'  => [],
                    'jobid'         => null,
                    'registerFlags' => [],
                    'monthOptions'  => [],
                    'q'             => $q,
                    'period'        => $period,
                    'isGlobalMode'  => true,
                ]);
            }
            // SuperAdmin + 会社選択: 通常の coordinator フィルターを適用（全件ではなく自分が関わる案件のみ）
            $query = ProjectJob::with('client')
                ->where('company_id', $contextId)
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->orWhereHas('coordinators', fn ($c) => $c->where('users.id', $user->id));
                });
        } else {
            $query = ProjectJob::with('client')
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->orWhereHas('coordinators', fn ($c) => $c->where('users.id', $user->id));
                });
        }

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

        $favoriteIds = \App\Models\CoordinatorProjectJobFavorite::where('user_id', $user->id)
            ->pluck('project_job_id')
            ->toArray();

        $jobs = $query->orderBy('created_at', 'desc')->get()->map(function ($job) use ($favoriteIds) {
            $job->is_favorite = in_array($job->id, $favoriteIds);
            return $job;
        });

        $favoriteJobs = $jobs->filter(fn($j) => $j->is_favorite)->values();

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
            'jobs'          => $jobs,
            'favoriteJobs'  => $favoriteJobs,
            'jobid'         => $jobid,
            'registerFlags' => $registerFlags,
            'monthOptions'  => $monthOptions,
            'q'             => $q,
            'period'        => $period,
            'isGlobalMode'  => false,
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
     * company_id が指定された場合は同一会社のユーザーのみ返す。
     */
    private function coordinatorCandidates(?int $companyId = null): \Illuminate\Support\Collection
    {
        if (!$companyId) {
            return collect();
        }

        return User::where('company_id', $companyId)
            ->ordered()
            ->get(['id', 'name']);
    }

    public function clone(Request $request, ProjectJob $projectJob)
    {
        $user = $request->user();
        if (!$this->isJobCoordinator($projectJob, $user)) {
            abort(403);
        }

        $newJob = null;
        DB::transaction(function () use ($projectJob, &$newJob) {
            $data = $projectJob->only([
                'user_id', 'client_id', 'company_id', 'size_id', 'page_count', 'detail',
            ]);
            $data['title']   = 'コピー - ' . $projectJob->title;
            $data['jobcode'] = null;
            Arr::pull($data, 'schedule'); // さくら本番: project_jobs.schedule カラムなし

            $newJob = ProjectJob::create($data);

            // サブCo 複製
            $subIds  = $projectJob->coordinators()->pluck('users.id')->toArray();
            $syncIds = array_values(array_filter($subIds, fn($id) => $id != $newJob->user_id));
            if (!empty($syncIds)) {
                $newJob->coordinators()->sync($syncIds);
            }

            // チームメンバー複製
            foreach ($projectJob->teamMembers as $member) {
                \App\Models\ProjectTeamMember::create([
                    'project_job_id' => $newJob->id,
                    'user_id'        => $member->user_id,
                ]);
            }

            // スケジュール複製（日付は空にする）
            $schedules = ProjectSchedule::where('project_job_id', $projectJob->id)->orderBy('order')->get();
            $scheduleIdMap = [];
            // parent_id=null のスケジュールを先に処理
            foreach ($schedules->where('parent_id', null) as $schedule) {
                $newSchedule = ProjectSchedule::create([
                    'project_job_id' => $newJob->id,
                    'parent_id'      => null,
                    'color'          => $schedule->color,
                    'name'           => $schedule->name,
                    'description'    => $schedule->description,
                    'start_date'     => null,
                    'end_date'       => null,
                    'progress'       => 0,
                    'status'         => null,
                    'order'          => $schedule->order,
                    'metadata'       => $schedule->metadata,
                    'created_by'     => $schedule->created_by,
                ]);
                $scheduleIdMap[$schedule->id] = $newSchedule->id;
            }
            // parent_id がある子スケジュールを処理
            foreach ($schedules->where('parent_id', '!=', null) as $schedule) {
                $newParentId = $scheduleIdMap[$schedule->parent_id] ?? null;
                $newSchedule = ProjectSchedule::create([
                    'project_job_id' => $newJob->id,
                    'parent_id'      => $newParentId,
                    'color'          => $schedule->color,
                    'name'           => $schedule->name,
                    'description'    => $schedule->description,
                    'start_date'     => null,
                    'end_date'       => null,
                    'progress'       => 0,
                    'status'         => null,
                    'order'          => $schedule->order,
                    'metadata'       => $schedule->metadata,
                    'created_by'     => $schedule->created_by,
                ]);
                $scheduleIdMap[$schedule->id] = $newSchedule->id;
            }

            // 進行管理表（ProgressSheet）複製
            $sheets = ProgressSheet::where('project_job_id', $projectJob->id)->orderBy('sort_order')->get();
            foreach ($sheets as $sheet) {
                $newSheet = ProgressSheet::create([
                    'project_job_id' => $newJob->id,
                    'template_id'    => $sheet->template_id,
                    'name'           => $sheet->name,
                    'column_config'  => $sheet->column_config,
                    'created_by'     => $sheet->created_by,
                    'sort_order'     => $sheet->sort_order,
                ]);

                // 台割行（ProgressRow）複製：2パス（親→子の順）
                $rows = ProgressRow::where('sheet_id', $sheet->id)->orderBy('order')->get();
                $rowIdMap = [];

                // 1パス目: parent_id=null のルート行
                foreach ($rows->where('parent_id', null) as $row) {
                    $newRow = ProgressRow::create([
                        'sheet_id'  => $newSheet->id,
                        'label'     => $row->label,
                        'order'     => $row->order,
                        'parent_id' => null,
                    ]);
                    $rowIdMap[$row->id] = $newRow->id;

                    // セル複製（担当者・割り当ては除外）
                    foreach ($row->cells as $cell) {
                        ProgressCell::create([
                            'row_id'                 => $newRow->id,
                            'col_key'                => $cell->col_key,
                            'value_text'             => $cell->value_text,
                            'value_date'             => $cell->value_date,
                            'value_bool'             => $cell->value_bool,
                            'cell_type'              => $cell->cell_type,
                            'value_user_id'          => null,
                            'value_subcontractor_id' => null,
                            'assignment_id'          => null,
                            'proof_assignment_id'    => null,
                        ]);
                    }
                }

                // 2パス目: 子行（parent_id あり）
                foreach ($rows->where('parent_id', '!=', null) as $row) {
                    $newParentId = $rowIdMap[$row->parent_id] ?? null;
                    $newRow = ProgressRow::create([
                        'sheet_id'  => $newSheet->id,
                        'label'     => $row->label,
                        'order'     => $row->order,
                        'parent_id' => $newParentId,
                    ]);
                    $rowIdMap[$row->id] = $newRow->id;

                    // セル複製（担当者・割り当ては除外）
                    foreach ($row->cells as $cell) {
                        ProgressCell::create([
                            'row_id'                 => $newRow->id,
                            'col_key'                => $cell->col_key,
                            'value_text'             => $cell->value_text,
                            'value_date'             => $cell->value_date,
                            'value_bool'             => $cell->value_bool,
                            'cell_type'              => $cell->cell_type,
                            'value_user_id'          => null,
                            'value_subcontractor_id' => null,
                            'assignment_id'          => null,
                            'proof_assignment_id'    => null,
                        ]);
                    }
                }
            }

            // 項目リスト複製
            $itemEntries = \App\Models\ProjectItemEntry::where('project_job_id', $projectJob->id)->orderBy('sort_order')->get();
            foreach ($itemEntries as $entry) {
                \App\Models\ProjectItemEntry::create([
                    'project_job_id' => $newJob->id,
                    'name'           => $entry->name,
                    'sort_order'     => $entry->sort_order,
                ]);
            }

            // 管理シート複製（行のみ・セルデータは除外）
            $wSheets = \App\Models\WorkflowSheet::where('project_job_id', $projectJob->id)->orderBy('sort_order')->get();
            foreach ($wSheets as $wSheet) {
                $newWSheet = \App\Models\WorkflowSheet::create([
                    'project_job_id' => $newJob->id,
                    'template_id'    => $wSheet->template_id,
                    'name'           => $wSheet->name,
                    'stage_config'   => $wSheet->stage_config,
                    'sort_order'     => $wSheet->sort_order,
                    'created_by'     => $wSheet->created_by,
                ]);
                $wRows = \App\Models\WorkflowRow::where('sheet_id', $wSheet->id)->orderBy('sort_order')->get();
                foreach ($wRows as $wRow) {
                    \App\Models\WorkflowRow::create([
                        'sheet_id'   => $newWSheet->id,
                        'label'      => $wRow->label,
                        'sort_order' => $wRow->sort_order,
                    ]);
                }
            }
        });

        return redirect()
            ->route('coordinator.project_jobs.edit', $newJob->id)
            ->with('success', '案件を複製しました。タイトル・伝票番号・クライアントを確認・修正してください。');
    }

    /**
     * 他部署へ案件を共有（新規案件として登録）
     * 共有対象フィールド: jobcode, title, client_id, size_id, page_count, detail
     * 指定したリーダー/コーディネーターが user_id として登録される
     */
    public function share(Request $request, ProjectJob $projectJob)
    {
        $user = $request->user();
        if (!$this->isJobCoordinator($projectJob, $user)) {
            abort(403);
        }

        $data = $request->validate([
            'target_user_id' => ['required', 'exists:users,id'],
        ]);

        $targetUser = \App\Models\User::findOrFail($data['target_user_id']);

        // 自分の部署と異なる部署のユーザーのみ許可
        if ($targetUser->department_id === $user->department_id) {
            return back()->withErrors(['target_user_id' => '自部署のユーザーへの共有はできません。']);
        }

        $newJob = null;
        DB::transaction(function () use ($projectJob, $targetUser, &$newJob) {
            $newJob = ProjectJob::create([
                'jobcode'           => $projectJob->jobcode,
                'title'             => $projectJob->title,
                'user_id'           => $targetUser->id,
                'client_id'         => $projectJob->client_id,
                'company_id'        => $targetUser->company_id,
                'size_id'           => $projectJob->size_id,
                'page_count'        => $projectJob->page_count,
                'detail'            => $projectJob->detail,
                'shared_from_id'    => $projectJob->id,
                'image_path'        => $projectJob->image_path,
                'original_filename' => $projectJob->original_filename,
            ]);

            // 共有先部署にクライアントが未登録の場合、自動で登録する
            if ($projectJob->client_id && $targetUser->department_id) {
                DB::table('client_departments')->insertOrIgnore([
                    'client_id'     => $projectJob->client_id,
                    'department_id' => $targetUser->department_id,
                ]);
            }
        });

        return redirect()
            ->route('coordinator.project_jobs.show', $projectJob->id)
            ->with('success', "「{$projectJob->title}」を {$targetUser->name} さんの部署に共有しました。");
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

    public function toggleFavorite(ProjectJob $projectJob)
    {
        $user = auth()->user();
        $existing = \App\Models\CoordinatorProjectJobFavorite::where('user_id', $user->id)
            ->where('project_job_id', $projectJob->id)
            ->first();
        if ($existing) {
            $existing->delete();
            $isFavorite = false;
        } else {
            \App\Models\CoordinatorProjectJobFavorite::create([
                'user_id' => $user->id,
                'project_job_id' => $projectJob->id,
            ]);
            $isFavorite = true;
        }
        return response()->json(['is_favorite' => $isFavorite]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $companyId = $user->isSuperAdmin()
            ? (int) (session('superadmin_context.company_id') ?? $user->company_id ?? 0)
            : (int) ($user->company_id ?? 0);

        $sizes = \App\Models\Size::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group']);

        // チームメンバー選択モーダル用のデータ（同一会社のみ）
        $departments = $companyId
            ? \App\Models\Department::where('company_id', $companyId)->orderBy('name')->get()
            : \App\Models\Department::all();
        $assignments = \App\Models\Assignment::all();
        $members = $companyId
            ? \App\Models\User::where('company_id', $companyId)->ordered()->with(['department', 'assignment'])->get()
            : \App\Models\User::ordered()->with(['department', 'assignment'])->get();
        $salesReps = PrepresSalesRep::orderBy('sort_order')->get(['id', 'name', 'company']);

        return Inertia::render('Coordinator/ProjectJobs/Create', [
            'coordinatorCandidates' => $this->coordinatorCandidates($companyId ?: null),
            'sizes' => $sizes,
            'departments' => $departments,
            'assignments' => $assignments,
            'members' => $members,
            'salesReps' => $salesReps,
        ]);
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'jobcode'                => ['nullable', 'string', 'max:255', 'regex:/^[0-9\-]+$/'],
                'title'                  => 'required|string|max:255',
                'user_id'                => 'required|exists:users,id',
                'client_id'              => 'nullable|exists:clients,id',
                'size_id'                => 'nullable|exists:sizes,id',
                'page_count'             => 'nullable|integer|min:1|max:99999',
                'detail'                 => 'nullable|string',
                'sales_rep'              => 'nullable|string|max:100',
                'sales_rep_id'           => 'nullable|integer|exists:prepress_sales_reps,id',
                'plate_submission_date'  => 'nullable|date',
                'plate_down_date'        => 'nullable|date',
                'sub_coordinator_ids'    => 'nullable|array',
                'sub_coordinator_ids.*'  => 'exists:users,id',
                'team_members'           => 'nullable|array',
                'team_members.*.user_id' => 'required|integer|exists:users,id',
                'image'                  => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,heic,heif,pdf', 'max:20480'],
                'tmp_ocr_image_path'     => ['nullable', 'string', 'max:500'],
            ]);

            if (!empty($data['jobcode'])) {
                if (ProjectJob::where('jobcode', $data['jobcode'])->exists()) {
                    return back()->withErrors(['jobcode' => 'この受注番号はすでに登録されています。'])->withInput();
                }
            }

            $subIds = Arr::pull($data, 'sub_coordinator_ids', []);
            $teamMembers = Arr::pull($data, 'team_members', []);
            Arr::pull($data, 'image');
            $tmpOcrPath = Arr::pull($data, 'tmp_ocr_image_path');

            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                $imageMeta = $this->imageService->convertAndStore($request->file('image'));
                if ($imageMeta) {
                    $data['image_path']        = $imageMeta['path'];
                    $data['original_filename'] = $imageMeta['original_filename'];
                }
            } elseif ($tmpOcrPath
                && str_starts_with($tmpOcrPath, 'prepress/jobticker/')
                && Storage::disk('public')->exists($tmpOcrPath)
            ) {
                $data['image_path']        = $tmpOcrPath;
                $data['original_filename'] = basename($tmpOcrPath);
            }

            $actor = $request->user();
            $data['company_id'] = $actor->isSuperAdmin()
                ? ((int) (session('superadmin_context.company_id') ?? $actor->company_id ?? 0) ?: null)
                : ($actor->company_id ?? null);

            $job = ProjectJob::create($data);

            // リーダー自身はピボットに入れない（重複回避）
            $syncIds = array_values(array_filter($subIds, fn ($id) => $id != $job->user_id));
            if (!empty($syncIds)) {
                $job->coordinators()->sync($syncIds);
            }

            // チームメンバーの作成
            foreach ($teamMembers as $member) {
                ProjectTeamMember::firstOrCreate([
                    'project_job_id' => $job->id,
                    'user_id'        => (int) $member['user_id'],
                ]);
            }

            // リーダー・副リーダーをチームメンバーに自動追加（追加のみ・削除なし）
            foreach (array_unique(array_merge([(int) $job->user_id], $syncIds)) as $userId) {
                ProjectTeamMember::firstOrCreate([
                    'project_job_id' => $job->id,
                    'user_id'        => $userId,
                ]);
            }

            return redirect()->route('coordinator.project_jobs.show', $job->id);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        }
    }

    public function storeFromTemplate(Request $request)
    {
        $data = $request->validate([
            'template_id' => 'required|exists:project_job_templates,id',
            'title'       => 'required|string|max:255',
            'jobcode'     => ['nullable', 'string', 'max:255', 'regex:/^[0-9\-]+$/'],
            'client_id'   => 'nullable|exists:clients,id',
            'user_id'     => 'nullable|exists:users,id',
            'size_id'     => 'nullable|exists:sizes,id',
            'page_count'  => 'nullable|integer|min:1|max:99999',
            'detail'      => 'nullable|string',
        ]);

        $template = \App\Models\ProjectJobTemplate::findOrFail($data['template_id']);
        $fixed = $template->fixed_fields ?? [];

        $actor = $request->user();
        $companyId = $actor->isSuperAdmin()
            ? ((int) (session('superadmin_context.company_id') ?? $actor->company_id ?? 0) ?: null)
            : ($actor->company_id ?? null);

        $jobData = [
            'title'      => $data['title'],
            'jobcode'    => $data['jobcode'] ?? null,
            'user_id'    => $fixed['user_id']    ?? $data['user_id']    ?? null,
            'client_id'  => $fixed['client_id']  ?? $data['client_id']  ?? null,
            'company_id' => $companyId,
            'size_id'    => $fixed['size_id']    ?? $data['size_id']    ?? null,
            'page_count' => isset($fixed['page_count']) ? $fixed['page_count'] : ($data['page_count'] ?? null),
            'detail'     => !empty($fixed['detail']) ? $fixed['detail'] : ($data['detail'] ?? null),
        ];

        if (empty($jobData['user_id']) || empty($jobData['client_id'])) {
            return back()->withErrors(['general' => 'リーダーとクライアントは必須です'])->withInput();
        }

        $job = ProjectJob::create($jobData);

        $subIds = $fixed['sub_coordinator_ids'] ?? [];
        $syncIds = array_values(array_filter($subIds, fn ($id) => $id != $job->user_id));
        if (!empty($syncIds)) {
            $job->coordinators()->sync($syncIds);
        }

        foreach ($template->team_members ?? [] as $member) {
            ProjectTeamMember::firstOrCreate([
                'project_job_id' => $job->id,
                'user_id'        => (int) $member['user_id'],
            ]);
        }

        foreach (array_unique(array_merge([(int) $job->user_id], $syncIds)) as $userId) {
            ProjectTeamMember::firstOrCreate([
                'project_job_id' => $job->id,
                'user_id'        => $userId,
            ]);
        }

        return redirect()->route('coordinator.project_jobs.show', $job->id);
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
            ->get(['id', 'name', 'description', 'start_date', 'end_date', 'progress', 'completed_at']);

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

        // 管理シート一覧
        $workflowSheets = $projectJob->workflowSheets()
            ->select(['id', 'name', 'sort_order', 'created_at'])
            ->get()
            ->map(fn ($s) => [
                'id'         => $s->id,
                'name'       => $s->name,
                'sort_order' => $s->sort_order,
                'created_at' => $s->created_at?->format('Y-m-d'),
            ]);

        // 項目リスト
        $itemEntries = $projectJob->itemEntries()
            ->get(['id', 'name', 'sort_order']);

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
                            'id'                   => $a->id,
                            'title'                => $a->title,
                            'user_id'              => $a->user_id,
                            'sender_id'            => $a->sender_id,
                            'source_assignment_id' => $a->source_assignment_id,
                            'desired_end_date'     => $a->desired_end_date?->format('Y-m-d'),
                            'start_time'           => $a->start_time,
                            'completed'            => (bool) $a->completed,
                            'scheduled'            => (bool) ($a->scheduled ?? false),
                            'scheduled_at'         => $a->scheduled_at,
                            'read_at'              => $a->read_at,
                            'status'               => $sm
                                ? ['id' => $sm->id, 'key' => $sm->key ?? $sm->slug ?? null, 'name' => $sm->name]
                                : null,
                            'user'                 => $a->user
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
                // Q-07: Eloquent + with() で job_type をロードし resolveJstCarbon を使えるようにする
                $eventGroupsByAssignment = \App\Models\Event::whereIn('project_job_assignment_id', $assignmentIds)
                    ->whereNotNull('starts_at')
                    ->orderBy('starts_at')
                    ->with('projectJobAssignment:id,job_type')
                    ->get(['id', 'project_job_assignment_id', 'starts_at', 'ends_at', 'interruption_minutes'])
                    ->groupBy('project_job_assignment_id');

                $jobHistory = array_map(function ($m) use ($eventGroupsByAssignment) {
                    $aid = (int) ($m['project_job_assignment_id'] ?? $m['project_job_assignment']['id'] ?? 0);
                    if ($aid && $eventGroupsByAssignment->has($aid)) {
                        $evs = $eventGroupsByAssignment->get($aid);
                        $first = $evs->first();
                        $firstS = $this->resolveJstCarbon($first, 'starts_at');
                        $firstE = $this->resolveJstCarbon($first, 'ends_at');
                        $m['event_id']        = $first->id;
                        $m['event_starts_at'] = $firstS?->toIso8601String();
                        $m['event_ends_at']   = $firstE?->toIso8601String();
                        // all events for this assignment
                        $m['all_events'] = $evs->map(function ($ev) {
                            $s = $this->resolveJstCarbon($ev, 'starts_at');
                            $e = $this->resolveJstCarbon($ev, 'ends_at');
                            $totalMins = ($s && $e) ? max(0, (int) $s->diffInMinutes($e, false)) : 0;
                            $interrupt = (int) ($ev->interruption_minutes ?? 0);
                            return [
                                'id'      => $ev->id,
                                'date'    => $s ? $s->toDateString() : null,
                                'start'   => $s ? $s->format('H:i') : null,
                                'end'     => $e ? $e->format('H:i') : null,
                                'minutes' => max(0, $totalMins - $interrupt),
                            ];
                        })->values()->toArray();
                        $m['total_minutes'] = array_sum(array_column($m['all_events'], 'minutes'));
                    } else {
                        $m['all_events']    = [];
                        $m['total_minutes'] = 0;
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

        // 他部署共有モーダル用: 現在ユーザーの部署以外の部署と、そのLeader/Coordinator
        $currentDeptId = $request->user()->department_id;
        $departmentCandidates = \App\Models\Department::where('active', true)
            ->when($currentDeptId, fn ($q) => $q->where('id', '!=', $currentDeptId))
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name'])
            ->map(function ($dept) {
                $users = \App\Models\User::where('department_id', $dept->id)
                    ->whereIn('user_role', ['leader', 'coordinator', 'clerk'])
                    ->ordered()
                    ->get(['id', 'name', 'user_role', 'department_id']);
                return [
                    'id'    => $dept->id,
                    'name'  => $dept->name,
                    'users' => $users->map(fn ($u) => [
                        'id'        => $u->id,
                        'name'      => $u->name,
                        'user_role' => $u->user_role,
                    ])->values()->toArray(),
                ];
            })
            ->filter(fn ($d) => count($d['users']) > 0)
            ->values();

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
            'progressSheets'  => $progressSheets,
            'sheetTemplates'  => $sheetTemplates,
            'workflowSheets'  => $workflowSheets,
            'itemEntries'     => $itemEntries,
            'workflowTemplates' => \App\Models\WorkflowTemplate::orderByDesc('updated_at')->get(['id', 'name']),
            'stages' => $stages,
            'sheetLinkedAssignmentIds' => $sheetLinkedAssignmentIds,
            'proofHistory' => $proofHistory,
            'departmentCandidates' => $departmentCandidates,
            'sharedJobs'           => $projectJob->sharedJobs()->with(['user', 'user.department'])->get()->map(fn ($j) => [
                'id'              => $j->id,
                'title'           => $j->title,
                'user_id'         => $j->user_id,
                'user_name'       => $j->user?->name,
                'department_name' => $j->user?->department?->name,
            ]),
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

            // ユーザーごとの昼休憩デフォルト設定を一括取得（user_settings.lunch_start/lunch_end）
            $userIds = $assignments->map(fn ($a) => $a->user?->id ?? $a->user_id)
                ->filter()->unique()->values()->all();
            $userSettingMap = [];
            if (!empty($userIds)) {
                \App\Models\UserSetting::whereIn('user_id', $userIds)->get()
                    ->each(function ($s) use (&$userSettingMap) {
                        $userSettingMap[$s->user_id] = $s;
                    });
            }
            // ユーザーごとの昼休憩キャッシュ（日付別）: [userId => [date => ['start','end']|null]]
            $breakDateCache = [];

            if (\Illuminate\Support\Facades\Schema::hasColumn('events', 'project_job_assignment_id')) {
                foreach ($assignments as $a) {
                    // Q-04+Q-07: with() で job_type をロードし resolveJstCarbon を使えるようにする
                    $events = \App\Models\Event::where('project_job_assignment_id', $a->id)
                        ->orderBy('starts_at')
                        ->with('projectJobAssignment:id,job_type')
                        ->get();

                    foreach ($events as $ev) {
                        $userAssignmentId   = $a->user?->assignment_id ?? null;
                        $userAssignmentName = $userAssignmentId ? ($assignmentNameMap[$userAssignmentId] ?? null) : null;
                        $userAssignmentCode = $userAssignmentId ? ($assignmentCodeMap[$userAssignmentId] ?? null) : null;

                        // Q-07: JST 解決（proof=UTC / 通常=JST）
                        $evStart   = $this->resolveJstCarbon($ev, 'starts_at');
                        $evEnd     = $this->resolveJstCarbon($ev, 'ends_at');
                        $eventDate = $evStart?->toDateString();

                        // ── 時間計算 ──────────────────────────────────────────
                        $totalMins        = 0;
                        $interruptionMins = (int)($ev->interruption_minutes ?? 0);
                        $lunchMins        = 0;
                        $actualMins       = 0;
                        try {
                            if ($evStart && $evEnd) {
                                $totalMins = max(0, (int)$evStart->diffInMinutes($evEnd, false));

                                // Q-04: 昼休憩計算を共通メソッドに委譲
                                $userId = $a->user?->id ?? $a->user_id ?? null;
                                if ($userId) {
                                    if (!isset($breakDateCache[$userId])) $breakDateCache[$userId] = [];
                                    $lunchMins = $this->computeLunchMinutes($evStart, $evEnd, (int)$userId, $breakDateCache[$userId]);
                                }

                                $actualMins = max(0, $totalMins - $interruptionMins - $lunchMins);
                            }
                        } catch (\Throwable $_) {}

                        $assignmentEvents[] = [
                            'assignment_id'        => $a->id,
                            'user_id'              => $a->user?->id ?? $a->user_id ?? null,
                            'user_name'            => $a->user?->name ?? null,
                            'assignment_name'      => $userAssignmentName ?? $a->title ?? null,
                            'assignment_code'      => $userAssignmentCode,
                            // 役割カテゴリ: ① 将来は assignments.job_role_category ② code 既定値
                            'role_category'        => $this->toRoleCategory($userAssignmentCode),
                            'stage_id'             => $a->stage_id ?? null,
                            'stage_name'           => $a->stage?->name ?? null,
                            'stage_sort'           => $a->stage?->sort_order ?? 99,
                            'status_name'          => $a->statusModel?->name ?? null,
                            'date'                 => $eventDate,
                            'start'                => $ev->start ?? $ev->starts_at ?? null,
                            'end'                  => $ev->end ?? $ev->ends_at ?? null,
                            'total_minutes'        => $totalMins,
                            'interruption_minutes' => $interruptionMins,
                            'lunch_minutes'        => $lunchMins,
                            'actual_minutes'       => $actualMins,
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

    public function edit(Request $request, ProjectJob $projectJob)
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

        $user = $request->user();
        $companyId = $user->isSuperAdmin()
            ? (int) (session('superadmin_context.company_id') ?? $user->company_id ?? 0)
            : (int) ($user->company_id ?? 0);

        $sizes = \App\Models\Size::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group']);
        return Inertia::render('Coordinator/ProjectJobs/Edit', [
            'job'                  => $jobArray,
            'coordinatorCandidates' => $this->coordinatorCandidates($companyId ?: null),
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
                'client_id'             => 'nullable|exists:clients,id',
                'size_id'               => 'nullable|exists:sizes,id',
                'page_count'            => 'nullable|integer|min:1|max:99999',
                'detail'                => 'nullable|string',
                'schedule'              => 'nullable|array',
                'sub_coordinator_ids'   => 'nullable|array',
                'sub_coordinator_ids.*' => 'exists:users,id',
            ]);

            if (!empty($data['jobcode'])) {
                if (ProjectJob::where('jobcode', $data['jobcode'])->where('id', '!=', $projectJob->id)->exists()) {
                    return back()->withErrors(['jobcode' => 'この受注番号はすでに登録されています。'])->withInput();
                }
            }

            $subIds = Arr::pull($data, 'sub_coordinator_ids', []);
            Arr::pull($data, 'schedule'); // project_jobs テーブルに schedule カラムなし
            $projectJob->update($data);

            // リーダー自身はピボットに入れない
            $syncIds = array_values(array_filter($subIds, fn ($id) => $id != $projectJob->user_id));
            $projectJob->coordinators()->sync($syncIds);

            // リーダー・副リーダーをチームメンバーに自動追加（追加のみ・削除なし）
            foreach (array_unique(array_merge([(int) $projectJob->user_id], $syncIds)) as $userId) {
                ProjectTeamMember::firstOrCreate([
                    'project_job_id' => $projectJob->id,
                    'user_id'        => $userId,
                ]);
            }

            return redirect()->route('coordinator.project_jobs.index');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        }
    }

    public function storeImage(Request $request, ProjectJob $projectJob)
    {
        $user = $request->user();
        if (!$this->isJobCoordinator($projectJob, $user)) {
            abort(403);
        }

        $request->validate([
            'image'              => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,heic,heif,pdf', 'max:20480'],
            'tmp_ocr_image_path' => ['nullable', 'string', 'max:500'],
        ]);

        // 既存画像を削除
        if ($projectJob->image_path) {
            $this->imageService->delete($projectJob->image_path);
        }

        $imageMeta = null;
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $imageMeta = $this->imageService->convertAndStore($request->file('image'));
        } elseif ($request->filled('tmp_ocr_image_path')) {
            $tmpPath = $request->input('tmp_ocr_image_path');
            if (str_starts_with($tmpPath, 'prepress/jobticker/')
                && Storage::disk('public')->exists($tmpPath)
            ) {
                $imageMeta = ['path' => $tmpPath, 'original_filename' => basename($tmpPath)];
            }
        }

        if (!$imageMeta) {
            return back()->withErrors(['image' => '画像の保存に失敗しました。']);
        }

        $projectJob->update([
            'image_path'        => $imageMeta['path'],
            'original_filename' => $imageMeta['original_filename'],
        ]);

        return back()->with('success', '伝票画像を保存しました。');
    }

    public function deleteImage(Request $request, ProjectJob $projectJob)
    {
        $user = $request->user();
        if (!$this->isJobCoordinator($projectJob, $user)) {
            abort(403);
        }

        if ($projectJob->image_path) {
            // 同じ image_path を参照している共有先案件がある場合はファイルを削除しない
            $otherJobs = ProjectJob::where('image_path', $projectJob->image_path)
                ->where('id', '!=', $projectJob->id)
                ->exists();
            if (!$otherJobs) {
                $this->imageService->delete($projectJob->image_path);
            }
        }

        $projectJob->update([
            'image_path'        => null,
            'original_filename' => null,
        ]);

        return back()->with('success', '伝票画像を削除しました。');
    }

    /**
     * POST coordinator/project_jobs/ocr/analyze
     * 伝票画像をOCR解析し JSON を返す（画像は tmp 保存のみ・DB変更なし）。
     */
    public function analyzeOcr(Request $request)
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,heic,heif,pdf', 'max:20480'],
        ]);

        $file = $request->file('image');
        $imageMeta = $this->imageService->convertAndStore($file);

        if (!$imageMeta || empty($imageMeta['path'])) {
            return response()->json([
                'error'           => '画像の変換に失敗しました。',
                'jobcode'         => '',
                'client_name'     => '',
                'title'           => '',
                'matched_clients' => [],
                'image_url'       => null,
                'tmp_image_path'  => null,
            ], 422);
        }

        $storagePath = $imageMeta['path'];
        $ocrResult   = $this->ocrService->analyze($storagePath);

        return response()->json([
            'jobcode'           => $ocrResult['jobcode']     ?? '',
            'client_name'       => $ocrResult['client_name'] ?? '',
            'title'             => $ocrResult['title']       ?? '',
            'matched_clients'   => $ocrResult['matched_clients'] ?? [],
            'image_url'         => Storage::disk('public')->url($storagePath),
            'tmp_image_path'    => $storagePath,
            'original_filename' => $imageMeta['original_filename'] ?? $file->getClientOriginalName(),
        ]);
    }

    /**
     * PATCH coordinator/project_jobs/{projectJob}/ocr-apply
     * OCR 結果を案件に適用する：伝票画像保存 + jobcode/title/client_id の任意更新。
     */
    public function applyOcrResult(Request $request, ProjectJob $projectJob)
    {
        $user = $request->user();
        if (!$this->isJobCoordinator($projectJob, $user)) {
            abort(403);
        }

        $validated = $request->validate([
            'tmp_image_path'    => ['required', 'string', 'max:500'],
            'original_filename' => ['nullable', 'string', 'max:255'],
            'jobcode'           => ['nullable', 'string', 'max:255', 'regex:/^[0-9\-]+$/'],
            'title'             => ['nullable', 'string', 'max:255'],
            'client_id'         => ['nullable', 'integer', 'exists:clients,id'],
            'update_fields'     => ['nullable', 'boolean'],
        ]);

        $tmpPath = $validated['tmp_image_path'];
        if (!str_starts_with($tmpPath, 'prepress/jobticker/')
            || !Storage::disk('public')->exists($tmpPath)
        ) {
            return response()->json(['error' => '画像ファイルが見つかりません。'], 422);
        }

        // 既存画像の削除（共有参照がない場合のみ）
        if ($projectJob->image_path && $projectJob->image_path !== $tmpPath) {
            $otherJobs = ProjectJob::where('image_path', $projectJob->image_path)
                ->where('id', '!=', $projectJob->id)
                ->exists();
            if (!$otherJobs) {
                $this->imageService->delete($projectJob->image_path);
            }
        }

        $updateData = [
            'image_path'        => $tmpPath,
            'original_filename' => $validated['original_filename'] ?? basename($tmpPath),
        ];

        // フィールド更新が要求された場合のみ上書き
        if (!empty($validated['update_fields'])) {
            if (isset($validated['jobcode'])) {
                $updateData['jobcode'] = $validated['jobcode'] ?: null;
            }
            if (!empty($validated['title'])) {
                $updateData['title'] = $validated['title'];
            }
            if (!empty($validated['client_id'])) {
                $updateData['client_id'] = $validated['client_id'];
            }
        }

        $projectJob->update($updateData);

        return response()->json([
            'ok'       => true,
            'message'  => '伝票画像とフィールドを更新しました。',
            'image_url' => Storage::disk('public')->url($tmpPath),
            'original_filename' => $updateData['original_filename'],
        ]);
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
