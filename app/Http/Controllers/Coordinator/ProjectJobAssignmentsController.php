<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\JobAssignmentMessage;
use App\Models\ProjectJob;
use App\Models\ProjectJobAssignment;
use App\Models\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProjectJobAssignmentsController extends Controller
{
    public function index(Request $request, ProjectJob $projectJob)
    {
        $allowedSorts = [
            'title',
            'user', // special-case: users.name
            'desired_end_date',
            'estimated_hours',
            'assigned',
            'created_at',
        ];

        $sortBy  = $request->query('sort_by', 'created_at');
        $sortDir = strtolower($request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }

        // 年月フィルター
        $period      = $request->query('period', '');
        $periodStart = null;
        $periodEnd   = null;
        if ($period && $period !== 'all') {
            try {
                $periodStart = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
                $periodEnd   = Carbon::createFromFormat('Y-m', $period)->endOfMonth();
            } catch (\Throwable $e) {
                $period = '';
            }
        }

        $hideCompleted = $request->boolean('hide_completed');

        // base query（マイジョブに置き換えられた依頼ジョブは除外）
        $query = $projectJob->projectJobAssignments()->with(['user', 'statusModel'])
            ->whereDoesntHave('supersededBy');

        // 検索
        $q = $request->query('q', null);
        if ($q) {
            $query = $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', '%' . $q . '%')
                    ->orWhere('detail', 'like', '%' . $q . '%');
            })->orWhereHas('user', function ($uq) use ($q) {
                $uq->where('name', 'like', '%' . $q . '%');
            });
        }

        // 年月フィルター
        if ($periodStart && $periodEnd) {
            $query->where(function ($sub) use ($periodStart, $periodEnd) {
                $sub->whereBetween('project_job_assignments.desired_end_date', [$periodStart, $periodEnd])
                    ->orWhereBetween('project_job_assignments.created_at', [$periodStart, $periodEnd]);
            });
        }

        // 完了非表示
        if ($hideCompleted) {
            $query->where(function ($sub) {
                $sub->where('completed', false)->orWhereNull('completed');
            });
        }

        // ソート
        if ($sortBy === 'user') {
            $query = $query->leftJoin('users', 'project_job_assignments.user_id', '=', 'users.id')
                ->select('project_job_assignments.*')
                ->orderBy('users.name', $sortDir);
        } else {
            if (Schema::hasColumn('project_job_assignments', $sortBy)) {
                $query = $query->orderBy('project_job_assignments.' . $sortBy, $sortDir);
            } else {
                $query = $query->orderBy('project_job_assignments.created_at', 'desc');
            }
        }

        $list = $query->limit(500)->get();

        // transform items
        $assignments = $list->map(function ($a) {
            $statusObj = null;
            if (isset($a->statusModel) && $a->statusModel) {
                $statusObj = [
                    'id'  => $a->statusModel->id,
                    'key' => $a->statusModel->key ?? $a->statusModel->slug ?? null,
                    'name' => $a->statusModel->name,
                ];
            }

            return [
                'id'                  => $a->id,
                'project_job_id'      => $a->project_job_id,
                'user_id'             => $a->user_id,
                'title'               => $a->title,
                'detail'              => $a->detail,
                'difficulty_id'       => $a->difficulty_id ?? null,
                'difficulty_label'    => $a->difficultyModel?->name ?? null,
                'desired_start_date'  => $a->desired_start_date ? $a->desired_start_date->format('Y-m-d') : null,
                'desired_end_date'    => $a->desired_end_date ? $a->desired_end_date->format('Y-m-d') : null,
                'desired_time'        => $a->desired_time,
                'estimated_hours'     => isset($a->estimated_hours) ? (float) $a->estimated_hours : null,
                'assigned'            => (bool) $a->assigned,
                'accepted'            => (bool) $a->accepted,
                'completed'           => (bool) ($a->completed ?? false),
                'scheduled'           => (bool) ($a->scheduled ?? false),
                'scheduled_at'        => $a->scheduled_at ? $a->scheduled_at->toISOString() : null,
                'read_at'             => $a->read_at ? $a->read_at->toISOString() : null,
                'status_id'           => $a->status_id ?? null,
                'status_model'        => $statusObj,
                'created_at'          => $a->created_at ? $a->created_at->toISOString() : null,
                'proof_completed_at'  => $a->proof_completed_at ? $a->proof_completed_at->toISOString() : null,
                'user' => $a->user ? [
                    'id'                    => $a->user->id,
                    'name'                  => $a->user->name,
                    'employment_type'       => $a->user->employment_type ?? 'regular',
                    'employment_type_label' => $a->user->employmentTypeLabel(),
                ] : null,
            ];
        })->values()->toArray();

        // 年月セレクター用オプション
        $monthOptions = $projectJob->projectJobAssignments()
            ->whereDoesntHave('supersededBy')
            ->where(function ($q2) {
                $q2->whereNotNull('desired_end_date')->orWhereNotNull('created_at');
            })
            ->selectRaw("DATE_FORMAT(COALESCE(desired_end_date, created_at), '%Y-%m') as ym")
            ->groupBy('ym')
            ->orderByRaw("DATE_FORMAT(COALESCE(desired_end_date, created_at), '%Y-%m') DESC")
            ->pluck('ym')
            ->filter()
            ->unique()
            ->map(function ($ym) {
                try {
                    $label = Carbon::createFromFormat('Y-m', $ym)->format('Y年n月');
                } catch (\Throwable $e) {
                    $label = $ym;
                }
                return ['value' => $ym, 'label' => $label];
            })
            ->values()
            ->toArray();

        if (method_exists($projectJob, 'load')) {
            $projectJob->load('client');
        }

        return Inertia::render('Coordinator/ProjectJobs/JobAssign/Index', [
            'projectJob'    => $projectJob,
            'assignments'   => $assignments,
            'sort_by'       => $sortBy,
            'sort_dir'      => $sortDir,
            'q'             => $q,
            'period'        => $period,
            'hideCompleted' => $hideCompleted,
            'monthOptions'  => $monthOptions,
        ]);
    }

    public function create(Request $request, ProjectJob $projectJob)
    {
        // 完了済み案件へのジョブ割り当て作成は禁止
        if ($projectJob->completed) {
            return redirect()->route('coordinator.project_jobs.show', ['projectJob' => $projectJob->id])
                ->with('error', 'この案件は完了済みのため、新しいジョブを割り当てることはできません。');
        }

        $prefillTitle = $request->query('title'); // 進行管理表joblink経由の場合にタイトルをprefill
        $prefillStageId = $request->query('stage_id');
        $prefillSizeId = $request->query('size_id');
        $prefillWorkItemTypeId = $request->query('work_item_type_id');
        $prefillUserId = $request->query('user_id') ? (int) $request->query('user_id') : null;
        // 進行管理表セルリンク用（joblink登録後に assignment_id を紐付ける）
        $prefillProgressSheetId = $request->query('progress_sheet_id') ? (int) $request->query('progress_sheet_id') : null;
        $prefillRowId = $request->query('row_id') ? (int) $request->query('row_id') : null;
        $prefillColKey = $request->query('col_key');
        // send available team members for selection (exclude self for normal jobs)
        $selfId  = Auth::id();
        $members = $projectJob->teamMembers()->with(['user', 'user.assignment'])->get()->map(function ($m) {
            return [
                'id'                    => $m->user?->id,
                'name'                  => $m->user?->name,
                'assignment_name'       => $m->user?->assignment?->name,
                'employment_type'       => $m->user?->employment_type ?? 'regular',
                'employment_type_label' => $m->user ? $m->user->employmentTypeLabel() : '',
            ];
        })->filter(function ($item) use ($selfId) {
            return $item['id'] !== null && $item['id'] !== $selfId;
        })->values();

        // ensure projectJob has client relation loaded so the create/edit pages can display client name
        if (method_exists($projectJob, 'load')) {
            $projectJob->load('client');
        }

        // prepare companies list based on user role (superadmin/admin can select companies/departments)
        $companies = collect();
        $userRole = null;
        $userCompanyId = null;
        $userDepartmentId = null;
        $user = request()->user();
        if ($user) {
            $userRole = $user->user_role ?? null;
            $userCompanyId = $user->company_id ?? null;
            $userDepartmentId = $user->department_id ?? null;
        }

        if ($userRole === 'superadmin') {
            $companies = \App\Models\Company::with(['departments' => function ($q) {
                $q->orderBy('sort_order');
            }])->orderBy('name')->get();
        } elseif ($userRole === 'admin') {
            if ($userCompanyId) {
                $companies = \App\Models\Company::where('id', $userCompanyId)->with(['departments' => function ($q) {
                    $q->orderBy('sort_order');
                }])->get();
            }
        } else {
            // leader/coordinator: no companies list (they only see their department)
            $companies = collect();
        }

        // lookup lists for modal (types, sizes, stages, statuses)
        $types = \App\Models\WorkItemType::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group', 'company_id', 'department_id']);
        $sizes = \App\Models\Size::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group', 'width', 'height', 'unit', 'company_id', 'department_id']);
        $stages = \App\Models\Stage::orderBy('sort_order')->orderBy('order_index')->get(['id', 'name', 'company_id', 'department_id']);
        // Request key column as well when available so we can include canonical status.key in payload
        $statusCols = ['id', 'name', 'slug', 'company_id', 'department_id'];
        try {
            if (Schema::hasColumn('statuses', 'key')) $statusCols[] = 'key';
        } catch (\Throwable $__e) {
            // ignore introspection errors
        }
        $statuses = \App\Models\Status::orderBy('sort_order')->get($statusCols);
        // build difficulties list with either (id,name,slug) when slug exists, or (id,name) otherwise
        $difficultySelect = ['id', 'name'];
        try {
            if (Schema::hasColumn('difficulties', 'slug')) $difficultySelect[] = 'slug';
        } catch (\Throwable $__e) {
            // if introspection fails, default to id,name
        }
        try {
            $difficulties = \App\Models\Difficulty::orderBy('sort_order')->get($difficultySelect);
        } catch (\Throwable $__e) {
            $difficulties = collect();
        }

        return Inertia::render('Coordinator/ProjectJobs/JobAssign/Edit', [
            'projectJob' => $projectJob,
            'members' => $members,
            'assignments' => [],
            'editMode' => false,
            'companies' => $companies,
            'prefill_title' => $prefillTitle,
            'prefill_stage_id' => $prefillStageId,
            'prefill_size_id' => $prefillSizeId,
            'prefill_work_item_type_id' => $prefillWorkItemTypeId,
            'prefill_user_id' => $prefillUserId,
            'prefill_progress_sheet_id' => $prefillProgressSheetId,
            'prefill_row_id' => $prefillRowId,
            'prefill_col_key' => $prefillColKey,
            'types' => $types,
            'sizes' => $sizes,
            'stages' => $stages,
            'statuses' => $statuses,
            'difficulties' => $difficulties,
            'user_role' => $userRole,
            'user_company_id' => $userCompanyId,
            'user_department_id' => $userDepartmentId,
        ]);
    }

    public function edit(ProjectJob $projectJob, ProjectJobAssignment $assignment)
    {
        $selfId  = Auth::id();
        $members = $projectJob->teamMembers()->with(['user', 'user.assignment'])->get()->map(function ($m) {
            return [
                'id'                    => $m->user?->id,
                'name'                  => $m->user?->name,
                'assignment_name'       => $m->user?->assignment?->name,
                'employment_type'       => $m->user?->employment_type ?? 'regular',
                'employment_type_label' => $m->user ? $m->user->employmentTypeLabel() : '',
            ];
        })->filter(function ($item) use ($selfId) {
            return $item['id'] !== null && $item['id'] !== $selfId;
        })->values();

        $a = $assignment;

        // ensure projectJob has client relation loaded for the edit page
        if (method_exists($projectJob, 'load')) {
            $projectJob->load('client');
        }

        // prepare companies list based on user role (same rules as WorkItemController)
        $companies = collect();
        $userRole = null;
        $userCompanyId = null;
        $userDepartmentId = null;
        $user = request()->user();
        if ($user) {
            $userRole = $user->user_role ?? null;
            $userCompanyId = $user->company_id ?? null;
            $userDepartmentId = $user->department_id ?? null;
        }

        if ($userRole === 'superadmin') {
            $companies = \App\Models\Company::with(['departments' => function ($q) {
                $q->orderBy('sort_order');
            }])->orderBy('name')->get();
        } elseif ($userRole === 'admin') {
            if ($userCompanyId) {
                $companies = \App\Models\Company::where('id', $userCompanyId)->with(['departments' => function ($q) {
                    $q->orderBy('sort_order');
                }])->get();
            }
        } else {
            $companies = collect();
        }

        // lookup lists for modal (types, sizes, stages, statuses)
        $types = \App\Models\WorkItemType::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group', 'company_id', 'department_id']);
        $sizes = \App\Models\Size::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group', 'width', 'height', 'unit', 'company_id', 'department_id']);
        $stages = \App\Models\Stage::orderBy('sort_order')->orderBy('order_index')->get(['id', 'name', 'company_id', 'department_id']);
        $statuses = \App\Models\Status::orderBy('sort_order')->get(['id', 'name', 'slug', 'company_id', 'department_id']);

        // build labels from lookups so the edit form can display current selection
        $typeLabel = null;
        $sizeLabel = null;
        $stageLabel = null;
        $statusLabel = null;
        if ($a->work_item_type_id) {
            $t = $types->firstWhere('id', $a->work_item_type_id);
            if ($t) $typeLabel = 'type: ' . $t->name;
        }
        if ($a->size_id) {
            $s = $sizes->firstWhere('id', $a->size_id);
            if ($s) {
                $sizeLabel = $s->name;
                if (isset($s->width) && isset($s->height)) {
                    $sizeLabel .= sprintf(' (%s×%s%s)', $s->width, $s->height, $s->unit ?? '');
                }
            }
        }
        if ($a->stage_id) {
            $st = $stages->firstWhere('id', $a->stage_id);
            if ($st) $stageLabel = $st->name;
        }
        if ($a->status_id) {
            $stt = $statuses->firstWhere('id', $a->status_id);
            if ($stt) $statusLabel = $stt->name;
        }

        $assignmentPayload = [
            'id' => $a->id,
            'project_job_id' => $a->project_job_id,
            'user_id' => $a->user_id,
            'title' => $a->title,
            'title_suffix' => $a->title, // AssignmentForm の assembleTitleCoord() が参照するキー
            'detail' => $a->detail,
            'difficulty_id' => $a->difficulty_id ?? null,
            'difficulty_label' => $a->difficultyModel?->name ?? null,
            'desired_end_date' => $a->desired_end_date ? $a->desired_end_date->format('Y-m-d') : null,
            'desired_time' => $a->desired_time,
            'estimated_hours' => isset($a->estimated_hours) ? (float) $a->estimated_hours : null,
            'assigned' => (bool) $a->assigned,
            'accepted' => (bool) $a->accepted,
            // lookup ids migrated from WorkItem
            'work_item_type_id' => $a->work_item_type_id,
            'size_id' => $a->size_id,
            'stage_id' => $a->stage_id,
            'status_id' => $a->status_id,
            // canonical status object for frontend convenience
            'status' => $a->statusModel ? [
                'id' => $a->statusModel->id,
                'key' => $a->statusModel->key ?? $a->statusModel->slug ?? null,
                'name' => $a->statusModel->name,
            ] : null,
            'company_id' => $a->company_id,
            'department_id' => $a->department_id,
            // UX labels for display in the edit modal
            'type_label' => $typeLabel,
            'size_label' => $sizeLabel,
            'stage_label' => $stageLabel,
            'status_label' => $statusLabel,
            'amounts' => $a->amounts ?? null,
            'amounts_unit' => $a->amounts_unit ?? null,
            'job_type' => $a->job_type ?? null,
            'file_info' => $a->file_info ?? null,
        ];

        // build difficulties list
        $difficultySelect = ['id', 'name'];
        try {
            if (Schema::hasColumn('difficulties', 'slug')) $difficultySelect[] = 'slug';
        } catch (\Throwable $__e) {
        }
        try {
            $difficulties = \App\Models\Difficulty::orderBy('sort_order')->get($difficultySelect);
        } catch (\Throwable $__e) {
            $difficulties = collect();
        }

        return Inertia::render('Coordinator/ProjectJobs/JobAssign/Edit', [
            'projectJob' => $projectJob,
            'members' => $members,
            'assignments' => [$assignmentPayload],
            'editMode' => true,
            'companies' => $companies,
            'types' => $types,
            'sizes' => $sizes,
            'stages' => $stages,
            'statuses' => $statuses,
            'difficulties' => $difficulties,
            'user_role' => $userRole,
            'user_company_id' => $userCompanyId,
            'user_department_id' => $userDepartmentId,
        ]);
    }

    public function show(ProjectJob $projectJob, ProjectJobAssignment $assignment)
    {
        // Build display payload similar to edit(), including resolved labels and user info
        $a = $assignment;
        // prepare members and lookup lists (same as edit) so frontend can resolve names
        $members = $projectJob->teamMembers()->with(['user', 'user.assignment'])->get()->map(function ($m) {
            return [
                'id'                    => $m->user?->id,
                'name'                  => $m->user?->name,
                'assignment_name'       => $m->user?->assignment?->name,
                'employment_type'       => $m->user?->employment_type ?? 'regular',
                'employment_type_label' => $m->user ? $m->user->employmentTypeLabel() : '',
            ];
        })->filter(function ($item) {
            return $item['id'] !== null;
        })->values();

        // ensure projectJob has client relation loaded for the show page
        if (method_exists($projectJob, 'load')) {
            $projectJob->load('client');
        }

        // prepare companies list based on user role
        $companies = collect();
        $userRole = null;
        $userCompanyId = null;
        $userDepartmentId = null;
        $user = request()->user();
        if ($user) {
            $userRole = $user->user_role ?? null;
            $userCompanyId = $user->company_id ?? null;
            $userDepartmentId = $user->department_id ?? null;
        }

        if ($userRole === 'superadmin') {
            $companies = \App\Models\Company::with(['departments' => function ($q) {
                $q->orderBy('sort_order');
            }])->orderBy('name')->get();
        } elseif ($userRole === 'admin') {
            if ($userCompanyId) {
                $companies = \App\Models\Company::where('id', $userCompanyId)->with(['departments' => function ($q) {
                    $q->orderBy('sort_order');
                }])->get();
            }
        } else {
            $companies = collect();
        }

        // lookup lists for modal (types, sizes, stages, statuses)
        $types = \App\Models\WorkItemType::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group', 'company_id', 'department_id']);
        $sizes = \App\Models\Size::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group', 'width', 'height', 'unit', 'company_id', 'department_id']);
        $stages = \App\Models\Stage::orderBy('sort_order')->orderBy('order_index')->get(['id', 'name', 'company_id', 'department_id']);
        $statuses = \App\Models\Status::orderBy('sort_order')->get(['id', 'name', 'slug', 'company_id', 'department_id']);

        $typeLabel = null;
        $sizeLabel = null;
        $stageLabel = null;
        $statusLabel = null;
        if ($a->work_item_type_id) {
            $t = $types->firstWhere('id', $a->work_item_type_id);
            if ($t) $typeLabel = 'type: ' . $t->name;
        }
        if ($a->size_id) {
            $s = $sizes->firstWhere('id', $a->size_id);
            if ($s) {
                $sizeLabel = $s->name;
                if (isset($s->width) && isset($s->height)) {
                    $sizeLabel .= sprintf(' (%s×%s%s)', $s->width, $s->height, $s->unit ?? '');
                }
            }
        }
        if ($a->stage_id) {
            $st = $stages->firstWhere('id', $a->stage_id);
            if ($st) $stageLabel = $st->name;
        }
        if ($a->status_id) {
            $stt = $statuses->firstWhere('id', $a->status_id);
            if ($stt) $statusLabel = $stt->name;
        }

        $userInfo = $a->user ? ['id' => $a->user->id, 'name' => $a->user->name] : null;

        $assignmentPayload = [
            'id' => $a->id,
            'project_job_id' => $a->project_job_id,
            'user_id' => $a->user_id,
            'title' => $a->title,
            'detail' => $a->detail,
            'difficulty_id' => $a->difficulty_id ?? null,
            'difficulty_label' => $a->difficultyModel?->name ?? null,
            'desired_end_date' => $a->desired_end_date ? $a->desired_end_date->format('Y-m-d') : null,
            'desired_time' => $a->desired_time,
            'estimated_hours' => isset($a->estimated_hours) ? (float) $a->estimated_hours : null,
            'assigned' => (bool) $a->assigned,
            'accepted' => (bool) $a->accepted,
            // lookup ids migrated from WorkItem
            'work_item_type_id' => $a->work_item_type_id,
            'size_id' => $a->size_id,
            'stage_id' => $a->stage_id,
            'status_id' => $a->status_id,
            'company_id' => $a->company_id,
            'department_id' => $a->department_id,
            // UX labels for display
            'type_label' => $typeLabel,
            'size_label' => $sizeLabel,
            'stage_label' => $stageLabel,
            'status_label' => $statusLabel,
            'amounts' => $a->amounts ?? null,
            'amounts_unit' => $a->amounts_unit ?? null,
            'user' => $userInfo,
            'job_type' => $a->job_type ?? null,
            'file_info' => $a->file_info ?? null,
        ];

        // build difficulties list
        $difficultySelect = ['id', 'name'];
        try {
            if (Schema::hasColumn('difficulties', 'slug')) $difficultySelect[] = 'slug';
        } catch (\Throwable $__e) {
        }
        try {
            $difficulties = \App\Models\Difficulty::orderBy('sort_order')->get($difficultySelect);
        } catch (\Throwable $__e) {
            $difficulties = collect();
        }

        return Inertia::render('Coordinator/ProjectJobs/JobAssign/Show', [
            'projectJob' => $projectJob,
            'assignment' => $assignmentPayload,
            // provide lookup arrays so frontend can resolve names like in edit()
            'members' => $members,
            'companies' => $companies,
            'types' => $types,
            'sizes' => $sizes,
            'stages' => $stages,
            'statuses' => $statuses,
            'difficulties' => $difficulties,
            'user_role' => $userRole,
            'user_company_id' => $userCompanyId,
            'user_department_id' => $userDepartmentId,
            'editMode' => false,
        ]);
    }

    public function update(Request $request, ProjectJob $projectJob, ProjectJobAssignment $assignment)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'detail' => 'nullable|string',
            // accept difficulty by id only
            'difficulty_id' => 'nullable|exists:difficulties,id',
            'estimated_hours' => 'nullable|numeric|min:0',
            // scheduling: desired_start_date removed
            'desired_end_date' => 'nullable|date',
            'desired_time' => 'nullable|date_format:H:i',
            'user_id' => 'nullable|exists:users,id',
            // lookup fields
            'work_item_type_id' => 'nullable|exists:work_item_types,id',
            'size_id' => 'nullable|exists:sizes,id',
            'status_id' => 'nullable|exists:statuses,id',
            'company_id' => 'nullable|exists:companies,id',
            'department_id' => 'nullable|exists:departments,id',
            'stage_id' => 'nullable|exists:stages,id',
            // amounts and unit: amounts is integer >= 0, unit limited to page|file
            'amounts' => 'nullable|integer|min:0',
            'amounts_unit' => 'nullable|string|in:page,file',
            'send_immediately' => 'nullable|boolean',
            'file_info' => 'nullable|string',
        ]);

        $sendImmediately = $request->boolean('send_immediately', false);

        // server-side logical validations
        // if end date is today, desired_time must be >= now
        if (!empty($data['desired_end_date']) && !empty($data['desired_time'])) {
            $today = date('Y-m-d');
            if ($data['desired_end_date'] === $today) {
                $now = date('H:i');
                if ($data['desired_time'] < $now) {
                    return back()->withErrors(['desired_time' => '当日の時間は現在時刻以降を指定してください。'])->withInput();
                }
            }
        }

        // prefer explicit difficulty_id only
        $difficultyId = !empty($data['difficulty_id']) ? (int) $data['difficulty_id'] : null;

        $updateData = [
            'user_id' => $data['user_id'] ?? null,
            'title' => $data['title'],
            'detail' => $data['detail'] ?? null,
            // store difficulty_id; keep legacy string column untouched if present
            'difficulty_id' => $difficultyId,
            'desired_end_date' => $data['desired_end_date'] ?? null,
            'desired_time' => $data['desired_time'] ?? null,
            'estimated_hours' => $data['estimated_hours'] ?? null,
            // preserve lookup fields when updating
            'work_item_type_id' => $data['work_item_type_id'] ?? null,
            'size_id' => $data['size_id'] ?? null,
            'stage_id' => $data['stage_id'] ?? null,
            'status_id' => $data['status_id'] ?? null,
            'company_id' => $data['company_id'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'amounts' => $data['amounts'] ?? null,
            'amounts_unit' => $data['amounts_unit'] ?? null,
            'file_info' => isset($data['file_info']) && $data['file_info']
                ? json_decode($data['file_info'], true)
                : ($assignment->file_info ?? null),
        ];

        // (debug logs removed)

        // legacy difficulty string column removed from update payload

        $assignment->update($updateData);

        // assignment_file_stats に upsert
        if (!empty($updateData['file_info']) && is_array($updateData['file_info'])) {
            \App\Models\AssignmentFileStat::upsertFromFileInfo($assignment->id, $updateData['file_info']);
        }

        // 保存して再送信: 既存の JobAssignmentMessage を削除し、最新内容で再作成
        if ($sendImmediately && !empty($updateData['user_id'])) {
            $senderUser = auth()->user();
            try {
                DB::transaction(function () use ($assignment, $projectJob, $updateData, $senderUser) {
                    // 旧メッセージを削除（送信一覧が二重にならないよう）
                    JobAssignmentMessage::where('project_job_assignment_id', $assignment->id)->delete();

                    $assignedName = null;
                    try {
                        $assignedUser = \App\Models\User::find($updateData['user_id']);
                        $assignedName = $assignedUser?->name;
                    } catch (\Throwable $_) {}

                    $bodyText = implode("\n", array_filter([
                        'ジョブ割り当て依頼（更新）',
                        '案件: ' . ($projectJob->title ?? ''),
                        'ジョブ: ' . ($updateData['title'] ?? ''),
                        $assignedName ? '担当: ' . $assignedName : null,
                        !empty($updateData['detail']) ? '詳細: ' . $updateData['detail'] : null,
                        !empty($updateData['desired_end_date']) ? '締め切り: ' . $updateData['desired_end_date'] : null,
                    ]));

                    $jam = JobAssignmentMessage::create([
                        'project_job_assignment_id' => $assignment->id,
                        'sender_id' => $senderUser->id,
                        'subject' => $updateData['title'] ?? null,
                        'body' => $bodyText,
                    ]);

                    $assignment->assigned = true;
                    $assignment->save();

                    try {
                        $jamLoaded = JobAssignmentMessage::with([
                            'sender',
                            'projectJobAssignment.user',
                            'projectJobAssignment.projectJob.client',
                        ])->find($jam->id);
                        event(new \App\Events\JobMessageCreated(
                            $jamLoaded,
                            [$updateData['user_id']],
                            $jam->id
                        ));
                    } catch (\Throwable $_) {}
                });
            } catch (\Throwable $e) {
                Log::warning('send_immediately failed in assignment update', ['error' => $e->getMessage()]);
            }
        }

        return redirect()->route('coordinator.project_jobs.assignments.index', ['projectJob' => $projectJob->id]);
    }

    public function store(Request $request, ProjectJob $projectJob)
    {
        // 完了済み案件へのジョブ割り当て作成は禁止
        if ($projectJob->completed) {
            return response()->json(['error' => 'この案件は完了済みのため、新しいジョブを割り当てることはできません。'], 422);
        }

        $data = $request->validate([
            'assignments' => 'required|array',
            'assignments.*.title' => 'required|string|max:255',
            'assignments.*.detail' => 'nullable|string',
            // accept difficulty by id only
            'assignments.*.difficulty_id' => 'nullable|exists:difficulties,id',
            'assignments.*.estimated_hours' => 'nullable|numeric|min:0',
            // scheduling: desired_start_date removed
            'assignments.*.desired_end_date' => 'nullable|date',
            'assignments.*.desired_time' => 'nullable|date_format:H:i',
            'assignments.*.user_id' => 'nullable|exists:users,id',
            // lookup fields moved from WorkItem: clients should send these lookup ids directly on the assignment payload
            'assignments.*.work_item_type_id' => 'nullable|exists:work_item_types,id',
            'assignments.*.size_id' => 'nullable|exists:sizes,id',
            'assignments.*.status_id' => 'nullable|exists:statuses,id',
            'assignments.*.company_id' => 'nullable|exists:companies,id',
            'assignments.*.department_id' => 'nullable|exists:departments,id',
            'assignments.*.stage_id' => 'nullable|exists:stages,id',
            'assignments.*.amounts' => 'nullable|integer|min:0',
            'assignments.*.amounts_unit' => 'nullable|string|in:page,file',
            'assignments.*.sender_id' => 'nullable|exists:users,id',
            'assignments.*.title' => 'nullable|string|max:255',
            'assignments.*.description' => 'nullable|string',
            'assignments.*._progress_sheet_id' => 'nullable|integer',
            'assignments.*._row_id' => 'nullable|integer',
            'assignments.*._col_key' => 'nullable|string|max:64',
            'assignments.*.file_info' => 'nullable|string',
        ]);

        $sendImmediately = $request->boolean('send_immediately', false);
        $senderUser = auth()->user();

        foreach ($data['assignments'] as $a) {
            // logical validations per assignment
            // scheduling: desired_start_date removed; only validate desired_time vs today below
            if (!empty($a['desired_end_date']) && !empty($a['desired_time'])) {
                $today = date('Y-m-d');
                if ($a['desired_end_date'] === $today) {
                    $now = date('H:i');
                    if ($a['desired_time'] < $now) {
                        return back()->withErrors(['assignments' => '当日の時間は現在時刻以降を指定してください。'])->withInput();
                    }
                }
            }

            // Create assignment and associated WorkItem in a transaction
            DB::transaction(function () use ($projectJob, $a, $sendImmediately, $senderUser) {
                // prefer explicit difficulty_id only
                $difficultyId = !empty($a['difficulty_id']) ? (int) $a['difficulty_id'] : null;

                $createData = [
                    'project_job_id' => $projectJob->id,
                    'user_id' => $a['user_id'] ?? null,
                    'sender_id' => $a['sender_id'] ?? null,
                    'title' => $a['title'],
                    'detail' => $a['detail'] ?? null,
                    'difficulty_id' => $difficultyId,
                    // scheduling: desired_start_date removed
                    'desired_end_date' => $a['desired_end_date'] ?? null,
                    'desired_time' => $a['desired_time'] ?? null,
                    'estimated_hours' => $a['estimated_hours'] ?? null,
                    // store lookup fields migrated from WorkItem
                    'work_item_type_id' => $a['work_item_type_id'] ?? null,
                    'size_id' => $a['size_id'] ?? null,
                    'stage_id' => $a['stage_id'] ?? null,
                    'status_id' => $a['status_id'] ?? null,
                    'company_id' => $a['company_id'] ?? null,
                    'department_id' => $a['department_id'] ?? null,
                    // amounts fields
                    'amounts' => $a['amounts'] ?? null,
                    'amounts_unit' => $a['amounts_unit'] ?? null,
                    // file info
                    'file_info' => isset($a['file_info']) && $a['file_info']
                        ? json_decode($a['file_info'], true)
                        : null,
                ];

                // legacy difficulty string column removed from create payload

                // (debug logs removed)

                $assignment = ProjectJobAssignment::create($createData);

                // assignment_file_stats に upsert
                if (!empty($createData['file_info']) && is_array($createData['file_info'])) {
                    \App\Models\AssignmentFileStat::upsertFromFileInfo($assignment->id, $createData['file_info']);
                }

                // ジョブ通知（受信者と案件リーダー・副リーダーへ）
                if (!empty($createData['user_id']) && $senderUser) {
                    \App\Services\JobNotificationService::notifyNewJob(
                        $senderUser,
                        $createData['user_id'],
                        $projectJob,
                        $assignment
                    );
                }

                // 保存して送信: JobAssignmentMessage を即時作成して依頼を発信する
                if ($sendImmediately && !empty($createData['user_id']) && $senderUser) {
                    try {
                        $detailText = $a['detail'] ?? '';
                        $assignedName = null;
                        try {
                            $assignedUser = \App\Models\User::find($createData['user_id']);
                            $assignedName = $assignedUser?->name;
                        } catch (\Throwable $_) {}

                        $bodyText = implode("\n", array_filter([
                            'ジョブ割り当て依頼',
                            '案件: ' . ($projectJob->title ?? ''),
                            'ジョブ: ' . ($a['title'] ?? ''),
                            $assignedName ? '担当: ' . $assignedName : null,
                            $detailText ? '詳細: ' . $detailText : null,
                            !empty($a['desired_end_date']) ? '締め切り: ' . $a['desired_end_date'] : null,
                        ]));

                        $jam = JobAssignmentMessage::create([
                            'project_job_assignment_id' => $assignment->id,
                            'sender_id' => $senderUser->id,
                            'subject' => $a['title'] ?? null,
                            'body' => $bodyText,
                        ]);

                        $assignment->assigned = true;
                        try { $assignment->accepted = true; } catch (\Throwable $_) {}
                        $assignment->save();

                        try {
                            $jamLoaded = JobAssignmentMessage::with([
                                'sender',
                                'projectJobAssignment.user',
                                'projectJobAssignment.projectJob.client',
                            ])->find($jam->id);
                            event(new \App\Events\JobMessageCreated(
                                $jamLoaded,
                                [$createData['user_id']],
                                $jam->id
                            ));
                        } catch (\Throwable $_) {}
                    } catch (\Throwable $eSend) {
                        Log::warning('send_immediately failed after assignment store', [
                            'error' => $eSend->getMessage(),
                        ]);
                    }
                }

                // 進行表セルリンク: _progress_sheet_id / _row_id / _col_key が渡された場合
                if (!empty($a['_progress_sheet_id']) && !empty($a['_row_id']) && !empty($a['_col_key'])) {
                    try {
                        \App\Models\ProgressCell::updateOrCreate(
                            ['row_id' => (int) $a['_row_id'], 'col_key' => (string) $a['_col_key']],
                            ['assignment_id' => $assignment->id]
                        );
                    } catch (\Throwable $__eCellLink) {
                        \Illuminate\Support\Facades\Log::warning('Failed to link ProgressCell after coordinator assignment', [
                            'error'   => $__eCellLink->getMessage(),
                            'row_id'  => $a['_row_id'],
                            'col_key' => $a['_col_key'],
                        ]);
                    }
                }

                // previously we created a separate WorkItem here; now assignment stores type/size/stage/status/company/department directly
                // No-op for WorkItem creation - clients should send lookup ids on assignment payload instead.
            });
        }

        return redirect()->route('coordinator.project_jobs.assignments.index', ['projectJob' => $projectJob->id]);
    }

    /**
     * Store an assignment created by a regular user (no coordinator side-effects).
     * This saves a single assignment record and redirects back to the calendar view.
     */
    public function storeUser(Request $request, ProjectJob $projectJob)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'detail' => 'nullable|string',
            // accept difficulty by id only
            'difficulty_id' => 'nullable|exists:difficulties,id',
            'estimated_hours' => 'nullable|numeric|min:0',
            // scheduling: desired_start_date removed
            'desired_end_date' => 'nullable|date',
            'desired_time' => 'nullable|date_format:H:i',
            // Note: user_id should be the current authenticated user; do not accept client-supplied user_id
            'work_item_type_id' => 'nullable|exists:work_item_types,id',
            'size_id' => 'nullable|exists:sizes,id',
            'status_id' => 'nullable|exists:statuses,id',
            'company_id' => 'nullable|exists:companies,id',
            'department_id' => 'nullable|exists:departments,id',
            'stage_id' => 'nullable|exists:stages,id',
            'amounts' => 'nullable|integer|min:0',
            'amounts_unit' => 'nullable|string|in:page,file',
        ]);

        // server-side logical validations (same as store)
        if (!empty($data['desired_end_date']) && !empty($data['desired_time'])) {
            $today = date('Y-m-d');
            if ($data['desired_end_date'] === $today) {
                $now = date('H:i');
                if ($data['desired_time'] < $now) {
                    return back()->withErrors(['desired_time' => '当日の時間は現在時刻以降を指定してください。'])->withInput();
                }
            }
        }

        // create a single assignment for the authenticated user without side-effects
        $user = $request->user();

        DB::transaction(function () use ($projectJob, $data, $user) {
            // prefer explicit difficulty_id only
            $difficultyId = !empty($data['difficulty_id']) ? (int) $data['difficulty_id'] : null;

            $createData = [
                'project_job_id' => $projectJob->id,
                'user_id' => $user ? $user->id : null,
                'title' => $data['title'],
                'detail' => $data['detail'] ?? null,
                'difficulty_id' => $difficultyId,
                // scheduling: desired_start_date removed
                'desired_end_date' => $data['desired_end_date'] ?? null,
                'desired_time' => $data['desired_time'] ?? null,
                'estimated_hours' => $data['estimated_hours'] ?? null,
                'work_item_type_id' => $data['work_item_type_id'] ?? null,
                'size_id' => $data['size_id'] ?? null,
                'stage_id' => $data['stage_id'] ?? null,
                'status_id' => $data['status_id'] ?? null,
                'company_id' => $data['company_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'amounts' => $data['amounts'] ?? null,
                'amounts_unit' => $data['amounts_unit'] ?? null,
            ];

            // legacy difficulty string column removed from create payload

            $assignment = ProjectJobAssignment::create($createData);
        });

        // After user saves their own job, redirect back to calendar (assume route 'calendar.index' exists)
        // If your app uses a different calendar route, please adjust the route name accordingly.
        try {
            return redirect()->route('calendar.index');
        } catch (\Exception $e) {
            // Fallback: go back to project assignments index if calendar route not available
            return redirect()->route('coordinator.project_jobs.assignments.index', ['projectJob' => $projectJob->id]);
        }
    }

    public function destroy(ProjectJob $projectJob, ProjectJobAssignment $assignment)
    {
        // ensure assignment belongs to the provided project
        if ((int) $assignment->project_job_id !== (int) $projectJob->id) {
            abort(404);
        }

        DB::transaction(function () use ($assignment) {
            // simply delete the assignment. any historical WorkItem rows were dropped by migration.
            $assignment->delete();
        });

        return redirect()->route('coordinator.project_jobs.assignments.index', ['projectJob' => $projectJob->id]);
    }

    /**
     * Show project-selection page for standalone assignment creation.
     * Returns owned clients and projects for dropdown selection.
     */
    public function selectProject(Request $request)
    {
        $user = $request->user();

        $ownedProjects = ProjectJob::where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('coordinators', fn($q2) => $q2->where('users.id', $user->id));
            })
            ->where(function ($q) {
                $q->where('completed', false)->orWhereNull('completed');
            })
            ->with('client')
            ->orderBy('created_at', 'desc')
            ->get();

        $clients = $ownedProjects
            ->whereNotNull('client')
            ->pluck('client')
            ->unique('id')
            ->values()
            ->map(fn($c) => ['id' => $c->id, 'name' => $c->name]);

        $projects = $ownedProjects->map(fn($p) => [
            'id' => $p->id,
            'title' => $p->title,
            'client_id' => $p->client_id,
        ])->values();

        return Inertia::render('Coordinator/ProjectJobs/JobAssign/SelectProject', [
            'clients' => $clients,
            'projects' => $projects,
        ]);
    }

    /**
     * Return JSON of past ProjectJobAssignments for the current coordinator (projects they own).
     * Used by the "過去データから流用" modal in the assignment form.
     */
    public function pastData(Request $request)
    {
        $user = $request->user();

        $mode = $request->input('mode', 'date');
        $hideCompleted = filter_var($request->input('hide_completed', true), FILTER_VALIDATE_BOOLEAN);
        $dateRange = $request->input('date_range', 'yesterday');
        $clientId = $request->input('client_id');
        $projectJobId = $request->input('project_job_id');

        $query = ProjectJobAssignment::with(['projectJob.client'])
            ->whereHas('projectJob', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->orderBy('created_at', 'desc');

        if ($hideCompleted) {
            try {
                if (Schema::hasColumn('project_job_assignments', 'completed')) {
                    $query->where(function ($q) {
                        $q->where('completed', false)->orWhereNull('completed');
                    });
                }
            } catch (\Throwable $e) {}
        }

        if ($mode === 'date') {
            $now = Carbon::now();
            switch ($dateRange) {
                case 'week':
                    $start = $now->copy()->subDays(7)->startOfDay();
                    $end = $now->copy()->endOfDay();
                    break;
                case 'month':
                    $start = $now->copy()->subDays(30)->startOfDay();
                    $end = $now->copy()->endOfDay();
                    break;
                default: // yesterday
                    $start = $now->copy()->subDay()->startOfDay();
                    $end = $now->copy()->subDay()->endOfDay();
            }
            $query->whereBetween('created_at', [$start, $end]);
        } elseif ($mode === 'project') {
            if ($projectJobId) {
                $query->where('project_job_id', $projectJobId);
            } elseif ($clientId) {
                $query->whereHas('projectJob', function ($q) use ($clientId) {
                    $q->where('client_id', $clientId);
                });
            }
        }

        $records = $query->limit(100)->get()->map(function ($a) {
            return [
                'id' => $a->id,
                'project_job_id' => $a->project_job_id,
                'client_id' => $a->projectJob?->client?->id,
                'client_name' => $a->projectJob?->client?->name ?? '-',
                'project_job_name' => $a->projectJob?->title ?? '-',
                'title' => $a->title,
                'detail' => $a->detail,
                'work_item_type_id' => $a->work_item_type_id,
                'size_id' => $a->size_id,
                'stage_id' => $a->stage_id,
                'difficulty_id' => $a->difficulty_id,
                'desired_end_date' => $a->desired_end_date ? $a->desired_end_date->format('Y-m-d') : null,
                'desired_time' => $a->desired_time,
                'estimated_hours' => $a->estimated_hours !== null ? (float) $a->estimated_hours : null,
                'amounts' => $a->amounts ?? null,
                'amounts_unit' => $a->amounts_unit ?? 'page',
                'created_at' => $a->created_at ? $a->created_at->format('Y-m-d') : null,
            ];
        });

        // Clients for dropdown (coordinator's own projects' clients)
        $clients = Client::whereHas('projectJobs', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->orderBy('name')->get(['id', 'name']);

        // Projects for dropdown
        $projects = ProjectJob::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'title', 'client_id'])
            ->map(fn($p) => ['id' => $p->id, 'title' => $p->title, 'client_id' => $p->client_id]);

        return response()->json([
            'records' => $records,
            'clients' => $clients,
            'projects' => $projects,
        ]);
    }
}
