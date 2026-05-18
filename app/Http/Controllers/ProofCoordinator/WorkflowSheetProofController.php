<?php

namespace App\Http\Controllers\ProofCoordinator;

use App\Http\Controllers\Controller;
use App\Models\ProjectJobAssignment;
use App\Models\ProofDispatcher;
use App\Models\ProofRequest;
use App\Models\ProofTeamMember;
use App\Models\User;
use App\Models\WorkflowCell;
use App\Models\WorkflowRow;
use App\Models\WorkflowSheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class WorkflowSheetProofController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');

        $query = WorkflowSheet::with([
            'projectJob:id,title,client_id,completed',
            'projectJob.client:id,name',
        ])->whereHas('projectJob', fn($q) => $q->where('completed', false));

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('projectJob', fn($j) => $j->where('title', 'like', "%{$search}%"))
                  ->orWhereHas('projectJob.client', fn($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        $sheets = $query->orderByDesc('created_at')->get();

        // 各シートの proof_v2 セル状況を集計
        $sheetIds = $sheets->pluck('id')->all();
        $rows     = WorkflowRow::whereIn('sheet_id', $sheetIds)->get(['id', 'sheet_id']);
        $rowToSheet = $rows->pluck('sheet_id', 'id')->all(); // row_id => sheet_id
        $allRowIds  = $rows->pluck('id')->all();

        $summary = [];
        if (!empty($allRowIds)) {
            $cells = WorkflowCell::whereIn('row_id', $allRowIds)
                ->where('cell_type', 'proof_v2')
                ->get(['id', 'row_id', 'proof_assignment_id']);

            foreach ($cells as $cell) {
                $sheetId = $rowToSheet[$cell->row_id] ?? null;
                if (!$sheetId) continue;
                $summary[$sheetId]['total']      = ($summary[$sheetId]['total']      ?? 0) + 1;
                $summary[$sheetId]['unassigned'] = ($summary[$sheetId]['unassigned'] ?? 0)
                    + ($cell->proof_assignment_id ? 0 : 1);
            }
        }

        $mapped = $sheets->map(function ($sheet) use ($summary) {
            $s = $summary[$sheet->id] ?? ['total' => 0, 'unassigned' => 0];
            return [
                'id'                => $sheet->id,
                'name'              => $sheet->name,
                'project_job_title' => $sheet->projectJob?->title ?? '-',
                'client_name'       => $sheet->projectJob?->client?->name ?? '-',
                'created_at'        => $sheet->created_at?->format('Y-m-d'),
                'proof_total'       => $s['total'],
                'proof_unassigned'  => $s['unassigned'],
            ];
        });

        return Inertia::render('ProofCoordinator/WorkflowSheets/Index', [
            'sheets' => $mapped->values(),
            'search' => $search,
        ]);
    }

    public function show(Request $request, WorkflowSheet $sheet)
    {
        $sheet->load(['projectJob:id,title,client_id', 'projectJob.client:id,name']);
        $projectJob   = $sheet->projectJob;
        $columnConfig = $sheet->getEffectiveColumnConfig();

        $defaultRow = $sheet->rows()->whereNull('parent_id')->orderBy('sort_order')->first();
        if (!$defaultRow) {
            $defaultRow = WorkflowRow::create([
                'sheet_id'   => $sheet->id,
                'label'      => '_default',
                'sort_order' => 0,
            ]);
        }

        $tableRows    = $this->buildTableRows($columnConfig, $defaultRow->id);
        $allProofKeys = array_unique(array_merge(
            ...array_map(fn($r) => array_column($r['proof_cols'], 'key'), $tableRows)
        ));

        $rawCells = WorkflowCell::where('row_id', $defaultRow->id)
            ->whereIn('stage_key', $allProofKeys)
            ->with([
                'proofAssignment:id,title,completed,proof_completed_at,user_id',
                'proofAssignment.user:id,name',
            ])
            ->get();

        // pending な ProofRequest を workflow_cell_id で照合
        $cellIds = $rawCells->pluck('id')->filter()->values()->toArray();
        $pendingProofRequests = [];
        if (!empty($cellIds)) {
            $prs = ProofRequest::whereIn('workflow_cell_id', $cellIds)
                ->where('status', 'pending')
                ->get(['id', 'workflow_cell_id']);
            foreach ($prs as $pr) {
                $pendingProofRequests[$pr->workflow_cell_id] = $pr->id;
            }
        }

        $cells = $rawCells->map(fn($c) => [
            'id'                         => $c->id,
            'row_id'                     => $c->row_id,
            'stage_key'                  => $c->stage_key,
            'proof_assignment_id'        => $c->proof_assignment_id,
            'proof_assignment_title'     => $c->proofAssignment?->title,
            'proof_assignment_user_id'   => $c->proofAssignment?->user_id,
            'proof_assignment_user_name' => $c->proofAssignment?->user?->name,
            'proof_assignment_completed' => $c->proofAssignment
                ? ($c->proofAssignment->completed || $c->proofAssignment->proof_completed_at !== null)
                : false,
            'proof_request_id'           => $pendingProofRequests[$c->id] ?? null,
        ])->values();

        // URL クエリから proof_request_id を取得し、依頼情報を組み立てる
        $proofRequestId   = $request->query('proof_request_id') ? (int) $request->query('proof_request_id') : null;
        $proofRequestData = null;
        if ($proofRequestId) {
            $pr = ProofRequest::with('requester:id,name')->find($proofRequestId);
            if ($pr) {
                $proofRequestData = [
                    'id'             => $pr->id,
                    'title'          => $pr->title,
                    'deadline'       => $pr->deadline?->format('Y-m-d'),
                    'note'           => $pr->note,
                    'requester_name' => $pr->requester?->name,
                ];
            }
        }

        $members      = $this->buildMembersList(Auth::user());
        $types        = \App\Models\WorkItemType::orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name', 'group', 'company_id', 'department_id']);
        $sizes        = \App\Models\Size::orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name', 'group', 'width', 'height', 'unit', 'company_id', 'department_id']);
        $stages       = \App\Models\Stage::orderBy('sort_order')->orderBy('order_index')
            ->get(['id', 'name', 'company_id', 'department_id']);
        $statuses     = \App\Models\Status::orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'company_id', 'department_id']);
        $difficulties = \App\Models\Difficulty::orderBy('sort_order')->get(['id', 'name']);
        $user         = Auth::user();

        return Inertia::render('ProofCoordinator/WorkflowSheets/Show', [
            'sheet' => [
                'id'            => $sheet->id,
                'name'          => $sheet->name,
                'column_config' => $columnConfig,
            ],
            'projectJob' => [
                'id'          => $projectJob->id,
                'title'       => $projectJob->title,
                'client_name' => $projectJob->client?->name,
                'client_id'   => $projectJob->client_id,
            ],
            'defaultRowId'       => $defaultRow->id,
            'tableRows'          => $tableRows,
            'cells'              => $cells,
            'members'            => $members,
            'types'              => $types,
            'sizes'              => $sizes,
            'stages'             => $stages,
            'statuses'           => $statuses,
            'difficulties'       => $difficulties,
            'user_role'          => $user->user_role,
            'user_company_id'    => $user->company_id,
            'user_department_id' => $user->department_id,
            'proofRequestId'     => $proofRequestId,
            'proofRequestData'   => $proofRequestData,
        ]);
    }

    public function assignPage(Request $request, WorkflowSheet $sheet)
    {
        $sheet->load(['projectJob:id,title,client_id', 'projectJob.client:id,name']);
        $projectJob = $sheet->projectJob;

        $title          = $request->query('title', '');
        $rowId          = $request->query('row_id') ? (int) $request->query('row_id') : null;
        $colKey         = $request->query('col_key');
        $stageId        = $request->query('stage_id') ? (int) $request->query('stage_id') : null;
        $proofRequestId = $request->query('proof_request_id') ? (int) $request->query('proof_request_id') : null;

        // proof_request_id があればタイトルと締切を ProofRequest から引き継ぐ
        $proofRequest     = null;
        $proofRequestData = null;
        if ($proofRequestId) {
            $proofRequest = ProofRequest::with('requester:id,name')->find($proofRequestId);
            if ($proofRequest) {
                if (!$title) $title = $proofRequest->title . '-校正';
                $proofRequestData = [
                    'id'       => $proofRequest->id,
                    'title'    => $proofRequest->title,
                    'deadline' => $proofRequest->deadline?->format('Y-m-d'),
                    'note'     => $proofRequest->note,
                ];
            }
        }

        $members      = $this->buildMembersList(Auth::user());
        $types        = \App\Models\WorkItemType::orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name', 'group', 'company_id', 'department_id']);
        $sizes        = \App\Models\Size::orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name', 'group', 'width', 'height', 'unit', 'company_id', 'department_id']);
        $stages       = \App\Models\Stage::orderBy('sort_order')->orderBy('order_index')
            ->get(['id', 'name', 'company_id', 'department_id']);
        $statuses     = \App\Models\Status::orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'company_id', 'department_id']);
        $difficulties = \App\Models\Difficulty::orderBy('sort_order')->get(['id', 'name']);
        $user         = Auth::user();

        $prefill = [
            'project_job_id' => $projectJob->id,
            '_client_id'     => (string) ($projectJob->client_id ?? ''),
            'title'          => $title,
            'stage_id'       => $stageId,
            'user_id'        => null,
            'company_id'     => $user->company_id,
            'department_id'  => $user->department_id,
        ];
        if ($proofRequest?->deadline) {
            $prefill['desired_end_date'] = $proofRequest->deadline->format('Y-m-d');
        }

        return Inertia::render('ProofCoordinator/WorkflowSheets/Assign', [
            'sheet'      => ['id' => $sheet->id, 'name' => $sheet->name],
            'projectJob' => $projectJob,
            'rowId'      => $rowId,
            'colKey'     => $colKey,
            'members'    => $members,
            'assignments' => [$prefill],
            'types'       => $types,
            'sizes'       => $sizes,
            'stages'      => $stages,
            'statuses'    => $statuses,
            'difficulties'=> $difficulties,
            'companies'   => [],
            'user_role'          => $user->user_role,
            'user_company_id'    => $user->company_id,
            'user_department_id' => $user->department_id,
            'proofRequest'       => $proofRequestData,
        ]);
    }

    public function assignStore(Request $request, WorkflowSheet $sheet)
    {
        $sheet->load(['projectJob:id,title,client_id']);
        $projectJob = $sheet->projectJob;

        $data = $request->validate([
            'assignments'                          => 'required|array|min:1',
            'assignments.*.title'                  => 'required|string|max:255',
            'assignments.*.detail'                 => 'nullable|string',
            'assignments.*.user_id'                => 'nullable|exists:users,id',
            'assignments.*.proof_dispatcher_id'    => 'nullable|exists:proof_dispatchers,id',
            'assignments.*.project_job_id'         => 'nullable|exists:project_jobs,id',
            'assignments.*.difficulty_id'          => 'nullable|exists:difficulties,id',
            'assignments.*.desired_end_date'       => 'nullable|date',
            'assignments.*.desired_time'           => 'nullable|date_format:H:i',
            'assignments.*.estimated_hours'        => 'nullable|numeric|min:0',
            'assignments.*.work_item_type_id'      => 'nullable|exists:work_item_types,id',
            'assignments.*.size_id'                => 'nullable|exists:sizes,id',
            'assignments.*.stage_id'               => 'nullable|exists:stages,id',
            'assignments.*.company_id'             => 'nullable|exists:companies,id',
            'assignments.*.department_id'          => 'nullable|exists:departments,id',
            'assignments.*.amounts'                => 'nullable|integer|min:0',
            'assignments.*.amounts_unit'           => 'nullable|string|in:page,file',
        ]);

        $rowId  = $request->query('row_id') ? (int) $request->query('row_id') : null;
        $colKey = $request->query('col_key');

        $a          = $data['assignments'][0];
        $senderUser = Auth::user();

        $isDispatcher   = !empty($a['proof_dispatcher_id']);
        $assigneeUserId = $isDispatcher ? $senderUser->id : ($a['user_id'] ?? $senderUser->id);
        $dispatcherId   = $isDispatcher ? $a['proof_dispatcher_id'] : null;
        $proofRequestId = $request->query('proof_request_id') ? (int) $request->query('proof_request_id') : null;

        DB::transaction(function () use ($a, $projectJob, $senderUser, $assigneeUserId, $dispatcherId, $isDispatcher, $rowId, $colKey, $proofRequestId) {
            $assignment = ProjectJobAssignment::create([
                'project_job_id'      => $projectJob->id,
                'user_id'             => $assigneeUserId,
                'sender_id'           => $senderUser->id,
                'job_type'            => 'proof',
                'proof_dispatcher_id' => $dispatcherId,
                'title'               => $a['title'],
                'detail'              => $a['detail'] ?? null,
                'difficulty_id'       => $a['difficulty_id'] ?? null,
                'desired_end_date'    => $a['desired_end_date'] ?? null,
                'desired_time'        => $a['desired_time'] ?? null,
                'estimated_hours'     => $a['estimated_hours'] ?? null,
                'work_item_type_id'   => $a['work_item_type_id'] ?? null,
                'size_id'             => $a['size_id'] ?? null,
                'stage_id'            => $a['stage_id'] ?? null,
                'company_id'          => $a['company_id'] ?? null,
                'department_id'       => $a['department_id'] ?? null,
                'amounts'             => $a['amounts'] ?? null,
                'amounts_unit'        => $a['amounts_unit'] ?? null,
                'status_id'           => 1,
            ]);

            // WorkflowCell に proof_assignment_id を紐づけ
            if ($rowId && $colKey) {
                $cell = WorkflowCell::firstOrCreate(
                    ['row_id' => $rowId, 'stage_key' => $colKey],
                    ['cell_type' => 'proof_v2']
                );
                $cell->update([
                    'proof_assignment_id' => $assignment->id,
                    'value_user_id'       => $assigneeUserId,
                    'cell_type'           => 'proof_v2',
                ]);
            }

            // ProofRequest が紐づいている場合は受理（pending → in_progress）
            if ($proofRequestId) {
                ProofRequest::where('id', $proofRequestId)
                    ->where('status', 'pending')
                    ->update([
                        'status'               => 'in_progress',
                        'proof_coordinator_id' => $senderUser->id,
                        'proofreader_id'       => $isDispatcher ? null : $assigneeUserId,
                    ]);
            }

            // 担当者へ通知（単発派遣・自己割当はスキップ）
            if (!$isDispatcher && $assigneeUserId !== $senderUser->id) {
                try {
                    \App\Services\JobNotificationService::notifyNewJob(
                        $senderUser,
                        $assigneeUserId,
                        $projectJob,
                        $assignment
                    );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('WorkflowSheetProofController: notify failed', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        return redirect()->route('proof_coordinator.workflow_sheets.show', $sheet->id)
            ->with('success', '担当者をアサインしました。');
    }

    // ──────────────────────────────────────────────────
    //  Private helpers
    // ──────────────────────────────────────────────────

    private function buildMembersList(User $authUser): \Illuminate\Support\Collection
    {
        $teamUserIds = ProofTeamMember::pluck('user_id');

        $members = User::whereIn('id', $teamUserIds)
            ->with('assignment:id,name,code')
            ->orderBy('name')
            ->get()
            ->map(fn($u) => [
                'id'                    => $u->id,
                'name'                  => $u->name,
                'assignment_name'       => $u->assignment?->name,
                'employment_type'       => $u->employment_type ?? 'regular',
                'employment_type_label' => $u->employmentTypeLabel(),
                'is_dispatcher'         => false,
                'dispatcher_id'         => null,
            ]);

        $dispatchers = ProofDispatcher::active()
            ->when($authUser->user_role !== 'superadmin', fn($q) => $q->forCompany($authUser->company_id))
            ->orderBy('name')
            ->get()
            ->map(fn($d) => [
                'id'                    => 'dp_' . $d->id,
                'name'                  => $d->name,
                'assignment_name'       => '単発派遣',
                'employment_type'       => 'proof_dispatcher',
                'employment_type_label' => '単発派遣',
                'is_dispatcher'         => true,
                'dispatcher_id'         => $d->id,
            ]);

        return $members->concat($dispatchers);
    }

    /**
     * column_config から proof_v2 列を含む tableRows を構築する。
     * トップレベルが stage 型なら各 stage を1行として返す。
     */
    private function buildTableRows(array $nodes, int $rowId): array
    {
        $hasStages = collect($nodes)->contains(fn($n) => ($n['type'] ?? '') === 'stage');

        if ($hasStages) {
            $rows = [];
            foreach ($nodes as $node) {
                if (($node['type'] ?? '') !== 'stage') continue;

                $proofCols = [];
                foreach ($node['children'] ?? [] as $child) {
                    if (($child['type'] ?? '') === 'proof_v2') {
                        $proofCols[] = ['key' => $child['key'], 'label' => $child['label'] ?? ''];
                    }
                }
                if (empty($proofCols)) continue;

                $itemLabel  = $node['item_label'] ?? '';
                $stageLabel = $node['label'] ?? '';
                $rows[] = [
                    'label'       => $itemLabel ? $itemLabel . ' ' . $stageLabel : $stageLabel,
                    'item_label'  => $itemLabel,
                    'stage_label' => $stageLabel,
                    'row_id'      => $rowId,
                    'proof_cols'  => $proofCols,
                ];
            }
            return $rows;
        }

        // フラット構造
        $proofCols = [];
        foreach ($nodes as $node) {
            if (($node['type'] ?? '') === 'proof_v2') {
                $proofCols[] = ['key' => $node['key'], 'label' => $node['label'] ?? ''];
            }
        }

        if (empty($proofCols)) return [];

        return [[
            'label'       => '',
            'item_label'  => '',
            'stage_label' => '',
            'row_id'      => $rowId,
            'proof_cols'  => $proofCols,
        ]];
    }
}
