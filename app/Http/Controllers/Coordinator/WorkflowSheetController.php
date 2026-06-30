<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\ProjectJob;
use App\Models\ProgressTemplate;
use App\Models\WorkflowSheet;
use App\Models\WorkflowRow;
use App\Models\WorkflowCell;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class WorkflowSheetController extends Controller
{
    public function store(Request $request, ProjectJob $projectJob)
    {
        $this->authorizeJobAccess($request->user(), $projectJob);

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'template_id' => 'nullable|exists:progress_templates,id',
        ]);

        $columnConfig = null;

        if (!empty($validated['template_id'])) {
            $template = ProgressTemplate::query()
                ->whereKey($validated['template_id'])
                ->where('sheet_type', 'management')
                ->where(function ($query) use ($request) {
                    $query->where('is_shared', true)
                        ->orWhere('created_by', $request->user()->id);
                })
                ->first();

            abort_unless($template, 403);

            if ($template->column_config) {
                $columnConfig = $template->column_config;
            }
        }

        if (empty($columnConfig)) {
            $columnConfig = [
                [
                    'key'        => (string) Str::uuid(),
                    'label'      => '初校',
                    'item_label' => '',
                    'type'       => 'stage',
                    'children'   => [
                        ['key' => (string) Str::uuid(), 'label' => '進行',   'type' => 'coordinator'],
                        ['key' => (string) Str::uuid(), 'label' => '組版',   'type' => 'worker'],
                        ['key' => (string) Str::uuid(), 'label' => '校正',   'type' => 'proof_v2'],
                        ['key' => (string) Str::uuid(), 'label' => '校正２', 'type' => 'proof_v2'],
                    ],
                ],
            ];
        }

        $sheet = WorkflowSheet::create([
            'project_job_id' => $projectJob->id,
            // This column references the legacy workflow_templates table.
            // ProgressTemplate is copied into column_config instead of linked here.
            'template_id'    => null,
            'name'           => $validated['name'],
            'stage_config'   => ['stages' => []],
            'column_config'  => $columnConfig,
            'created_by'     => $request->user()->id,
            'sort_order'     => WorkflowSheet::where('project_job_id', $projectJob->id)->max('sort_order') + 1,
        ]);

        return redirect()->route('coordinator.workflow_sheets.show', $sheet->id);
    }

    public function reorder(Request $request, ProjectJob $projectJob)
    {
        $this->authorizeJobAccess($request->user(), $projectJob);

        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer',
        ]);

        DB::transaction(function () use ($projectJob, $validated) {
            foreach ($validated['ids'] as $order => $id) {
                WorkflowSheet::where('id', $id)
                    ->where('project_job_id', $projectJob->id)
                    ->update(['sort_order' => $order]);
            }
        });

        return response()->json(['ok' => true]);
    }

    public function show(Request $request, WorkflowSheet $sheet)
    {
        $sheet->load(['projectJob.client', 'projectJob.size', 'projectJob.user', 'projectJob.coordinators']);
        $projectJob = $sheet->projectJob;

        $canEdit      = $this->canEdit($request->user(), $projectJob, $sheet);
        $columnConfig = $sheet->getEffectiveColumnConfig();

        // デフォルト行（行の概念を持たない管理シート用）
        $defaultRow = $sheet->rows()->whereNull('parent_id')->orderBy('sort_order')->first();
        if (!$defaultRow) {
            $defaultRow = $sheet->rows()->create([
                'label'      => '_default',
                'sort_order' => 0,
                'created_by' => $request->user()->id,
            ]);
        }

        $rawCells = WorkflowCell::where('row_id', $defaultRow->id)
            ->with([
                'assignedUser:id,name,user_role',
                'valueUser:id,name',
                'valueSubcontractor:id,name',
                'assignment:id,title,completed,proof_completed_at,user_id,subcontractor_id,desired_end_date',
                'proofAssignment:id,title,completed,proof_completed_at,user_id',
                'schedule:id,name,end_date,completed_at',
                'noteUser:id,name,user_role',
            ])
            ->get();

        // events から作業時間をバッチ算出（1イベント最大1440分でキャップ）
        $assignmentIds = $rawCells->whereNotNull('assignment_id')->pluck('assignment_id')->unique()->values()->toArray();

        // coordinator PJA を supersede している worker 自己割当 PJA も取得
        $supersedingPjas = [];
        if (!empty($assignmentIds)) {
            $supRows = DB::table('project_job_assignments')
                ->whereIn('supersedes_assignment_id', $assignmentIds)
                ->select('id', 'supersedes_assignment_id')
                ->get();
            foreach ($supRows as $r) {
                $supersedingPjas[(int)$r->supersedes_assignment_id][] = (int)$r->id;
            }
        }
        $supersedingIds = array_merge(...array_values($supersedingPjas) ?: [[]]);
        $allPjaIds = array_unique(array_merge($assignmentIds, $supersedingIds));

        $eventMinutes = [];
        if (!empty($allPjaIds)) {
            $rawMinutes = DB::table('events')
                ->whereIn('project_job_assignment_id', $allPjaIds)
                ->whereNotNull('ends_at')
                ->selectRaw('project_job_assignment_id,
                    COALESCE(SUM(GREATEST(0, LEAST(TIMESTAMPDIFF(MINUTE, starts_at, ends_at), 1440)
                        - COALESCE(interruption_minutes, 0))), 0) as total')
                ->groupBy('project_job_assignment_id')
                ->pluck('total', 'project_job_assignment_id')
                ->toArray();
            foreach ($assignmentIds as $coordId) {
                $total = (int)($rawMinutes[$coordId] ?? 0);
                foreach ($supersedingPjas[$coordId] ?? [] as $wId) {
                    $total += (int)($rawMinutes[$wId] ?? 0);
                }
                $eventMinutes[$coordId] = $total;
            }
        }

        // proof_assignment_id 用のイベントも取得
        $proofAssignmentIds = $rawCells->whereNotNull('proof_assignment_id')->pluck('proof_assignment_id')->unique()->values()->toArray();
        $supersedingProofPjas = [];
        if (!empty($proofAssignmentIds)) {
            // supersedes_assignment_id または coordinator_assignment_id で紐づく pja101 を取得
            $supProofRows = DB::table('project_job_assignments')
                ->where(function ($q) use ($proofAssignmentIds) {
                    $q->whereIn('supersedes_assignment_id', $proofAssignmentIds)
                      ->orWhereIn('coordinator_assignment_id', $proofAssignmentIds);
                })
                ->whereColumn('sender_id', 'user_id')
                ->select('id', 'supersedes_assignment_id', 'coordinator_assignment_id')
                ->get();
            foreach ($supProofRows as $r) {
                $parentId = $r->coordinator_assignment_id ?? $r->supersedes_assignment_id;
                if ($parentId) {
                    $supersedingProofPjas[(int)$parentId][] = (int)$r->id;
                }
            }
        }
        $supersedingProofIds = array_merge(...array_values($supersedingProofPjas) ?: [[]]);
        $allProofPjaIds = array_unique(array_merge($proofAssignmentIds, $supersedingProofIds));

        $proofEventMinutes = [];
        if (!empty($allProofPjaIds)) {
            $rawProofMinutes = DB::table('events')
                ->whereIn('project_job_assignment_id', $allProofPjaIds)
                ->whereNotNull('ends_at')
                ->selectRaw('project_job_assignment_id,
                    COALESCE(SUM(GREATEST(0, LEAST(TIMESTAMPDIFF(MINUTE, starts_at, ends_at), 1440)
                        - COALESCE(interruption_minutes, 0))), 0) as total')
                ->groupBy('project_job_assignment_id')
                ->pluck('total', 'project_job_assignment_id')
                ->toArray();
            foreach ($proofAssignmentIds as $proofId) {
                $total = (int)($rawProofMinutes[$proofId] ?? 0);
                foreach ($supersedingProofPjas[$proofId] ?? [] as $wId) {
                    $total += (int)($rawProofMinutes[$wId] ?? 0);
                }
                $proofEventMinutes[$proofId] = $total;
            }
        }

        // pending な ProofRequest を workflow_cell_id で照合
        $cellIds = $rawCells->pluck('id')->filter()->values()->toArray();
        $pendingProofRequests = [];
        if (!empty($cellIds)) {
            $prs = \App\Models\ProofRequest::whereIn('workflow_cell_id', $cellIds)
                ->where('status', 'pending')
                ->get(['id', 'workflow_cell_id', 'deadline']);
            foreach ($prs as $pr) {
                $pendingProofRequests[$pr->workflow_cell_id] = [
                    'id'       => $pr->id,
                    'deadline' => $pr->deadline?->format('Y-m-d'),
                ];
            }
        }

        $cells = $rawCells->map(fn($c) => $this->formatCellFull($c, $eventMinutes, $pendingProofRequests, $proofEventMinutes));

        $memberIds  = $projectJob->teamMembers()->pluck('user_id')->toArray();
        $coIds      = $projectJob->coordinators->pluck('id')->toArray();
        $ownerId    = $projectJob->user_id;
        $allUserIds = array_unique(array_merge($memberIds, $coIds, [$ownerId]));

        $allUsers = User::whereIn('id', $allUserIds)->ordered()->get(['id', 'name', 'user_role'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'user_role' => $u->user_role, 'is_ghost' => false]);

        $ghostUsers = \App\Models\User::withGhosts()
            ->where('ghost_owner_id', $request->user()->id)
            ->ordered()->get(['id', 'name'])
            ->map(fn ($g) => ['id' => $g->id, 'name' => $g->name, 'user_role' => 'user', 'is_ghost' => true]);

        $workerUsers      = $allUsers->concat($ghostUsers)->values();
        $coordinatorUsers = $allUsers->filter(fn ($u) => in_array($u['user_role'], ['coordinator', 'clerk', 'leader', 'admin', 'superadmin']))->values();

        $subcontractors = \App\Models\Subcontractor::managedBy($request->user()->id)->orderBy('name')->get(['id', 'name']);

        $userId    = $request->user()->id;
        $templates = ProgressTemplate::where(function ($q) use ($userId) {
            $q->where('is_shared', true)->orWhere('created_by', $userId);
        })->where(function ($q) {
            $q->whereNull('sheet_type')->orWhere('sheet_type', 'management');
        })->orderByDesc('updated_at')->get(['id', 'name']);

        $itemEntries     = $projectJob->itemEntries()->orderBy('sort_order')->get(['id', 'name']);
        $stages          = \App\Models\Stage::orderBy('order_index')->orderBy('id')->get(['id', 'name']);
        $projectSchedules = \App\Models\ProjectSchedule::where('project_job_id', $projectJob->id)
            ->orderBy('start_date')->orderBy('name')
            ->get(['id', 'name', 'start_date', 'end_date'])
            ->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'end_date' => $s->end_date?->format('Y-m-d')]);

        return Inertia::render('Coordinator/WorkflowSheets/Show', [
            'sheet' => [
                'id'            => $sheet->id,
                'name'          => $sheet->name,
                'column_config' => $columnConfig,
                'created_by'    => $sheet->created_by,
                'share_token'   => $sheet->share_token,
            ],
            'defaultRowId'     => $defaultRow->id,
            'cells'            => $cells,
            'workerUsers'      => $workerUsers,
            'coordinatorUsers' => $coordinatorUsers,
            'subcontractors'   => $subcontractors,
            'itemEntries'      => $itemEntries,
            'templates'        => $templates,
            'stages'           => $stages,
            'projectSchedules' => $projectSchedules,
            'projectJob'       => [
                'id'          => $projectJob->id,
                'title'       => $projectJob->title,
                'client_name' => $projectJob->client?->name,
            ],
            'canEdit' => $canEdit,
        ]);
    }

    public function update(Request $request, WorkflowSheet $sheet)
    {
        $this->authorizeJobAccess($request->user(), $sheet->projectJob, $sheet);

        $validated = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'column_config' => 'sometimes|array',
            'stage_config'  => 'sometimes|array',
        ]);

        $sheet->update($validated);

        return response()->json(['ok' => true, 'sheet' => [
            'id'            => $sheet->id,
            'name'          => $sheet->name,
            'column_config' => $sheet->getEffectiveColumnConfig(),
        ]]);
    }

    public function destroy(Request $request, WorkflowSheet $sheet)
    {
        $this->authorizeJobAccess($request->user(), $sheet->projectJob, $sheet);

        $sheet->delete();

        return redirect()->route('coordinator.project_jobs.show', $sheet->project_job_id)
            ->with('success', '管理シートを削除しました。');
    }

    /**
     * テンプレートとして登録
     * POST coordinator/workflow-sheets/{sheet}/register-template
     */
    public function registerAsTemplate(Request $request, WorkflowSheet $sheet)
    {
        $this->authorizeJobAccess($request->user(), $sheet->projectJob, $sheet);

        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'is_shared' => 'boolean',
        ]);

        $template = ProgressTemplate::create([
            'name'          => $validated['name'],
            'column_config' => $sheet->getEffectiveColumnConfig(),
            'sheet_type'    => 'management',
            'created_by'    => $request->user()->id,
            'is_shared'     => $validated['is_shared'] ?? false,
        ]);

        return response()->json(['ok' => true, 'template' => ['id' => $template->id, 'name' => $template->name]]);
    }

    /**
     * 印刷ページ
     * GET coordinator/workflow-sheets/{sheet}/print
     */
    public function printView(Request $request, WorkflowSheet $sheet)
    {
        $sheet->load(['projectJob.client', 'projectJob.coordinators']);
        $projectJob   = $sheet->projectJob;
        $columnConfig = $sheet->getEffectiveColumnConfig();

        $rows = $sheet->rows()->orderBy('sort_order')
            ->get(['id', 'parent_id', 'label', 'sort_order', 'item_entry_id', 'stage_id']);

        $rawCells = WorkflowCell::whereIn('row_id', $rows->pluck('id'))
            ->with(['assignedUser:id,name', 'valueUser:id,name', 'noteUser:id,name'])
            ->get();

        $cells = $rawCells->map(fn($c) => $this->formatCellFull($c, []));

        $stages = \App\Models\Stage::orderBy('order_index')->orderBy('id')->get(['id', 'name']);

        return Inertia::render('Coordinator/WorkflowSheets/Print', [
            'sheet'       => ['id' => $sheet->id, 'name' => $sheet->name, 'column_config' => $columnConfig],
            'rows'        => $rows,
            'cells'       => $cells,
            'stages'      => $stages,
            'projectJob'  => ['id' => $projectJob->id, 'title' => $projectJob->title, 'client_name' => $projectJob->client?->name],
        ]);
    }

    /**
     * 共有トークン発行
     * POST coordinator/workflow-sheets/{sheet}/share
     */
    public function share(Request $request, WorkflowSheet $sheet)
    {
        $this->authorizeJobAccess($request->user(), $sheet->projectJob, $sheet);

        if (!$sheet->share_token) {
            $sheet->update(['share_token' => Str::random(48)]);
        }

        $url = route('shared.workflow_sheets.show', ['token' => $sheet->share_token]);

        return response()->json(['share_token' => $sheet->share_token, 'url' => $url]);
    }

    /**
     * 共有トークン無効化
     * DELETE coordinator/workflow-sheets/{sheet}/share
     */
    public function unshare(Request $request, WorkflowSheet $sheet)
    {
        $this->authorizeJobAccess($request->user(), $sheet->projectJob, $sheet);

        $sheet->update(['share_token' => null]);

        return response()->json(['ok' => true]);
    }

    // ─────────────────────────────────────────────────────────

    private function formatCellFull(WorkflowCell $c, array $eventMinutes, array $pendingProofRequests = [], array $proofEventMinutes = []): array
    {
        $workMinutes      = $c->assignment_id      ? ($eventMinutes[$c->assignment_id]           ?? 0) : 0;
        $proofWorkMinutes = $c->proof_assignment_id ? ($proofEventMinutes[$c->proof_assignment_id] ?? 0) : 0;

        return [
            'id'                          => $c->id,
            'row_id'                      => $c->row_id,
            'stage_key'                   => $c->stage_key,
            'cell_type'                   => $c->cell_type ?? 'worker',
            'value_text'                  => $c->value_text,
            'value_date'                  => $c->value_date?->format('Y-m-d'),
            'value_bool'                  => $c->value_bool,
            'value_user_id'               => $c->value_user_id ?? $c->assigned_user_id,
            'value_user_name'             => $c->valueUser?->name ?? $c->assignedUser?->name,
            'value_subcontractor_id'      => $c->value_subcontractor_id,
            'value_subcontractor_name'    => $c->valueSubcontractor?->name,
            'assignment_id'               => $c->assignment_id,
            'assignment_title'            => $c->assignment?->title,
            'assignment_completed'        => $c->assignment?->completed,
            'assignment_proof_completed'  => $c->assignment?->proof_completed_at !== null,
            'assignment_user_id'          => $c->assignment?->user_id,
            'assignment_subcontractor_id' => $c->assignment?->subcontractor_id,
            'assignment_end_date'         => $c->assignment?->desired_end_date?->format('Y-m-d'),
            'proof_assignment_id'         => $c->proof_assignment_id,
            'proof_assignment_title'      => $c->proofAssignment?->title,
            'proof_assignment_completed'  => $c->proofAssignment?->completed
                                             || $c->proofAssignment?->proof_completed_at !== null,
            'proof_request_pending'       => isset($pendingProofRequests[$c->id]),
            'proof_request_id'            => $pendingProofRequests[$c->id]['id'] ?? null,
            'proof_request_deadline'      => $pendingProofRequests[$c->id]['deadline'] ?? null,
            'schedule_id'                 => $c->schedule_id,
            'schedule_name'               => $c->schedule?->name,
            'schedule_end_date'           => $c->schedule?->end_date?->format('Y-m-d'),
            'schedule_completed_at'       => $c->schedule?->completed_at?->format('Y-m-d H:i:s'),
            'cell_deadline'               => $c->cell_deadline?->format('Y-m-d'),
            'cell_note'                   => $c->cell_note,
            'cell_note_user_name'         => $c->noteUser?->name,
            'cell_note_user_role'         => $c->noteUser?->user_role,
            'completed_at'               => $c->completed_at?->format('Y-m-d H:i:s'),
            'work_minutes'               => $workMinutes,
            'proof_work_minutes'         => $proofWorkMinutes,
        ];
    }

    private function authorizeJobAccess(User $user, ProjectJob $projectJob, ?WorkflowSheet $sheet = null): void
    {
        $isOwner   = $projectJob->user_id === $user->id;
        $isSub     = $projectJob->coordinators()->where('users.id', $user->id)->exists();
        $isAdmin   = in_array($user->user_role, ['admin', 'superadmin']);
        $isCreator = $sheet && $sheet->created_by === $user->id;

        abort_unless($isOwner || $isSub || $isAdmin || $isCreator, 403);
    }

    private function canEdit(User $user, ProjectJob $projectJob, ?WorkflowSheet $sheet = null): bool
    {
        $isOwner   = $projectJob->user_id === $user->id;
        $isSub     = $projectJob->coordinators()->where('users.id', $user->id)->exists();
        $isAdmin   = in_array($user->user_role, ['admin', 'superadmin']);
        $isCreator = $sheet && $sheet->created_by === $user->id;

        return $isOwner || $isSub || $isAdmin || $isCreator;
    }
}
