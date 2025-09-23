<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\ProjectJob;
use App\Models\ProjectJobAssignment;

class ProjectJobAssignmentsController extends Controller
{
    public function index(Request $request, ProjectJob $projectJob)
    {
        // pagination and sorting
        $perPage = 15;
        $allowedSorts = [
            'desired_start_date',
            'title',
            'user', // special-case: users.name
            'desired_end_date',
            'estimated_hours',
            'assigned',
        ];

        $sortBy = $request->query('sort_by', 'desired_start_date');
        $sortDir = strtolower($request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'desired_start_date';
        }

        // base query
        $query = $projectJob->projectJobAssignments()->with('user');

        // search
        $q = $request->query('q', null);
        if ($q) {
            $query = $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', '%' . $q . '%')
                    ->orWhere('detail', 'like', '%' . $q . '%');
            })->orWhereHas('user', function ($uq) use ($q) {
                $uq->where('name', 'like', '%' . $q . '%');
            });
        }

        // apply sorting
        if ($sortBy === 'user') {
            // sort by user name
            $query = $query->leftJoin('users', 'project_job_assignments.user_id', '=', 'users.id')
                ->select('project_job_assignments.*')
                ->orderBy('users.name', $sortDir);
        } else {
            if (Schema::hasColumn('project_job_assignments', $sortBy)) {
                $query = $query->orderBy($sortBy, $sortDir);
            } else {
                // fallback
                $query = $query->orderBy('desired_start_date', 'desc');
            }
        }

        $paginator = $query->paginate($perPage)->withQueryString();

        // transform items
        $paginator->getCollection()->transform(function ($a) {
            return [
                'id' => $a->id,
                'project_job_id' => $a->project_job_id,
                'user_id' => $a->user_id,
                'title' => $a->title,
                'detail' => $a->detail,
                'difficulty' => $a->difficulty,
                'difficulty_id' => $a->difficulty_id ?? null,
                'difficulty_label' => $a->difficultyModel?->name ?? null,
                'desired_start_date' => $a->desired_start_date ? $a->desired_start_date->format('Y-m-d') : null,
                'desired_end_date' => $a->desired_end_date ? $a->desired_end_date->format('Y-m-d') : null,
                'desired_time' => $a->desired_time,
                'estimated_hours' => isset($a->estimated_hours) ? (float) $a->estimated_hours : null,
                'assigned' => (bool) $a->assigned,
                'accepted' => (bool) $a->accepted,
                'user' => $a->user ? ['id' => $a->user->id, 'name' => $a->user->name] : null,
            ];
        });

        // ensure client relation is available to the page so we can display client.name
        if (method_exists($projectJob, 'load')) {
            $projectJob->load('client');
        }

        return Inertia::render('Coordinator/ProjectJobs/JobAssign/Index', [
            'projectJob' => $projectJob,
            'assignments' => $paginator,
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
            'q' => $q,
        ]);
    }

    public function create(ProjectJob $projectJob)
    {
        // send available team members for selection
        $members = $projectJob->teamMembers()->with('user')->get()->map(function ($m) {
            return ['id' => $m->user?->id, 'name' => $m->user?->name];
        })->filter(function ($item) {
            return $item['id'] !== null;
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
        $types = \App\Models\WorkItemType::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'company_id', 'department_id']);
        $sizes = \App\Models\Size::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'width', 'height', 'unit', 'company_id', 'department_id']);
        $stages = \App\Models\Stage::orderBy('sort_order')->orderBy('order_index')->get(['id', 'name', 'company_id', 'department_id']);
        $statuses = \App\Models\Status::orderBy('sort_order')->get(['id', 'name', 'slug', 'company_id', 'department_id']);

        return Inertia::render('Coordinator/ProjectJobs/JobAssign/Edit', [
            'projectJob' => $projectJob,
            'members' => $members,
            'assignments' => [],
            'editMode' => false,
            'companies' => $companies,
            'types' => $types,
            'sizes' => $sizes,
            'stages' => $stages,
            'statuses' => $statuses,
            'user_role' => $userRole,
            'user_company_id' => $userCompanyId,
            'user_department_id' => $userDepartmentId,
        ]);
    }

    public function edit(ProjectJob $projectJob, ProjectJobAssignment $assignment)
    {
        $members = $projectJob->teamMembers()->with('user')->get()->map(function ($m) {
            return ['id' => $m->user?->id, 'name' => $m->user?->name];
        })->filter(function ($item) {
            return $item['id'] !== null;
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
        $types = \App\Models\WorkItemType::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'company_id', 'department_id']);
        $sizes = \App\Models\Size::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'width', 'height', 'unit', 'company_id', 'department_id']);
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
            'detail' => $a->detail,
            'difficulty' => $a->difficulty,
            'difficulty_id' => $a->difficulty_id ?? null,
            'difficulty_label' => $a->difficultyModel?->name ?? null,
            'desired_start_date' => $a->desired_start_date ? $a->desired_start_date->format('Y-m-d') : null,
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
            // UX labels for display in the edit modal
            'type_label' => $typeLabel,
            'size_label' => $sizeLabel,
            'stage_label' => $stageLabel,
            'status_label' => $statusLabel,
            'amounts' => $a->amounts ?? null,
            'amounts_unit' => $a->amounts_unit ?? null,
        ];

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
        $members = $projectJob->teamMembers()->with('user')->get()->map(function ($m) {
            return ['id' => $m->user?->id, 'name' => $m->user?->name];
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
        $types = \App\Models\WorkItemType::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'company_id', 'department_id']);
        $sizes = \App\Models\Size::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'width', 'height', 'unit', 'company_id', 'department_id']);
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
            'difficulty' => $a->difficulty,
            'difficulty_id' => $a->difficulty_id ?? null,
            'difficulty_label' => $a->difficultyModel?->name ?? null,
            'desired_start_date' => $a->desired_start_date ? $a->desired_start_date->format('Y-m-d') : null,
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
        ];

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
            // accept either difficulty id (numeric) or known name/slug
            'difficulty' => 'required',
            'estimated_hours' => 'nullable|numeric|min:0',
            'desired_start_date' => 'nullable|date',
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
        ]);

        // server-side logical validations
        if (!empty($data['desired_start_date']) && !empty($data['desired_end_date'])) {
            if ($data['desired_end_date'] < $data['desired_start_date']) {
                return back()->withErrors(['desired_end_date' => '終了希望日は割当希望日より前にできません。'])->withInput();
            }
        }
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

        // resolve difficulty to id if necessary
        $difficultyId = null;
        $difficultyLabel = null;
        if (!empty($data['difficulty'])) {
            // numeric id
            if (is_numeric($data['difficulty'])) {
                $difficultyId = (int) $data['difficulty'];
            } else {
                // try to find by slug (if column exists) or name
                $query = \App\Models\Difficulty::query();
                if (Schema::hasColumn('difficulties', 'slug')) {
                    $query->where('slug', $data['difficulty'])->orWhere('name', $data['difficulty']);
                } else {
                    $query->where('name', $data['difficulty']);
                }
                $d = $query->first();
                if ($d) {
                    $difficultyId = $d->id;
                    $difficultyLabel = $d->name;
                }
            }
        }

        $updateData = [
            'user_id' => $data['user_id'] ?? null,
            'title' => $data['title'],
            'detail' => $data['detail'] ?? null,
            // store difficulty_id; keep legacy string column untouched if present
            'difficulty_id' => $difficultyId,
            'desired_start_date' => $data['desired_start_date'] ?? null,
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
        ];

        // Only include legacy 'difficulty' string if the column exists in the table
        if (Schema::hasColumn('project_job_assignments', 'difficulty')) {
            $updateData['difficulty'] = $data['difficulty'] ?? null;
        }

        $assignment->update($updateData);

        return redirect()->route('coordinator.project_jobs.assignments.index', ['projectJob' => $projectJob->id]);
    }

    public function store(Request $request, ProjectJob $projectJob)
    {
        $data = $request->validate([
            'assignments' => 'required|array',
            'assignments.*.title' => 'required|string|max:255',
            'assignments.*.detail' => 'nullable|string',
            'assignments.*.difficulty' => 'required|in:light,normal,heavy',
            'assignments.*.estimated_hours' => 'nullable|numeric|min:0',
            'assignments.*.desired_start_date' => 'nullable|date',
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
            'assignments.*.title' => 'nullable|string|max:255',
            'assignments.*.description' => 'nullable|string',
        ]);

        foreach ($data['assignments'] as $a) {
            // logical validations per assignment
            if (!empty($a['desired_start_date']) && !empty($a['desired_end_date'])) {
                if ($a['desired_end_date'] < $a['desired_start_date']) {
                    return back()->withErrors(['assignments' => '終了希望日は割当希望日より前にできません。'])->withInput();
                }
            }
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
            DB::transaction(function () use ($projectJob, $a) {
                // resolve difficulty for each incoming assignment payload
                $difficultyId = null;
                if (!empty($a['difficulty'])) {
                    if (is_numeric($a['difficulty'])) {
                        $difficultyId = (int) $a['difficulty'];
                    } else {
                        $q = \App\Models\Difficulty::query();
                        if (Schema::hasColumn('difficulties', 'slug')) {
                            $q->where('slug', $a['difficulty'])->orWhere('name', $a['difficulty']);
                        } else {
                            $q->where('name', $a['difficulty']);
                        }
                        $d = $q->first();
                        if ($d) $difficultyId = $d->id;
                    }
                }

                $createData = [
                    'project_job_id' => $projectJob->id,
                    'user_id' => $a['user_id'] ?? null,
                    'title' => $a['title'],
                    'detail' => $a['detail'] ?? null,
                    'difficulty_id' => $difficultyId,
                    'desired_start_date' => $a['desired_start_date'] ?? null,
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
                ];

                if (Schema::hasColumn('project_job_assignments', 'difficulty')) {
                    $createData['difficulty'] = $a['difficulty'] ?? null;
                }

                $assignment = ProjectJobAssignment::create($createData);

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
            // accept either difficulty id or name/slug
            'difficulty' => 'required',
            'estimated_hours' => 'nullable|numeric|min:0',
            'desired_start_date' => 'nullable|date',
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
        if (!empty($data['desired_start_date']) && !empty($data['desired_end_date'])) {
            if ($data['desired_end_date'] < $data['desired_start_date']) {
                return back()->withErrors(['desired_end_date' => '終了希望日は割当希望日より前にできません。'])->withInput();
            }
        }
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
            // resolve difficulty id
            $difficultyId = null;
            if (!empty($data['difficulty'])) {
                if (is_numeric($data['difficulty'])) {
                    $difficultyId = (int) $data['difficulty'];
                } else {
                    $q = \App\Models\Difficulty::query();
                    if (Schema::hasColumn('difficulties', 'slug')) {
                        $q->where('slug', $data['difficulty'])->orWhere('name', $data['difficulty']);
                    } else {
                        $q->where('name', $data['difficulty']);
                    }
                    $d = $q->first();
                    if ($d) $difficultyId = $d->id;
                }
            }

            $createData = [
                'project_job_id' => $projectJob->id,
                'user_id' => $user ? $user->id : null,
                'title' => $data['title'],
                'detail' => $data['detail'] ?? null,
                'difficulty_id' => $difficultyId,
                'desired_start_date' => $data['desired_start_date'] ?? null,
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

            if (Schema::hasColumn('project_job_assignments', 'difficulty')) {
                $createData['difficulty'] = $data['difficulty'] ?? null;
            }

            ProjectJobAssignment::create($createData);
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
}
