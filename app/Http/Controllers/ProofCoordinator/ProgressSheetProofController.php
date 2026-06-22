<?php

namespace App\Http\Controllers\ProofCoordinator;

use App\Http\Controllers\Concerns\SavesProofWorkSlots;
use App\Http\Controllers\Controller;
use App\Models\ProgressCell;
use App\Models\ProgressSheet;
use App\Models\ProjectJobAssignment;
use App\Models\ProofDispatcher;
use App\Models\ProofRequest;
use App\Models\ProofTeamMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProgressSheetProofController extends Controller
{
    use SavesProofWorkSlots;
    public function show(Request $request, ProgressSheet $sheet)
    {
        $sheet->load(['projectJob:id,title,client_id', 'projectJob.client:id,name']);
        $projectJob   = $sheet->projectJob;
        $columnConfig = $sheet->column_config ?? [];

        $rows = $sheet->rows()->orderBy('order')->get(['id', 'label', 'order']);

        // column_config から proof_user / proof_v2 リーフを再帰抽出
        $proofColumnDefs = $this->extractProofLeaves($columnConfig);
        $proofKeys       = array_column($proofColumnDefs, 'key');

        $rawCells = ProgressCell::whereIn('row_id', $rows->pluck('id'))
            ->whereIn('col_key', $proofKeys)
            ->with([
                'proofAssignment:id,title,completed,proof_completed_at,user_id',
                'proofAssignment.user:id,name',
            ])
            ->get();

        // pending な ProofRequest を proof_cell_id で照合
        $cellIds = $rawCells->pluck('id')->filter()->values()->toArray();
        $pendingProofRequests = [];
        if (!empty($cellIds)) {
            $prs = ProofRequest::whereIn('proof_cell_id', $cellIds)
                ->where('status', 'pending')
                ->get(['id', 'proof_cell_id']);
            foreach ($prs as $pr) {
                $pendingProofRequests[$pr->proof_cell_id] = $pr->id;
            }
        }

        $cells = $rawCells->map(fn($c) => [
            'id'                         => $c->id,
            'row_id'                     => $c->row_id,
            'col_key'                    => $c->col_key,
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
        $targetRowId        = null;
        $targetProofColKeys = null;
        if ($proofRequestId) {
            $pr = ProofRequest::with('requester:id,name')->find($proofRequestId);
            if ($pr) {
                $proofRequestData = [
                    'id'             => $pr->id,
                    'title'          => $pr->title,
                    'deadline'       => $pr->deadline?->toIso8601String(),
                    'note'           => $pr->note,
                    'requester_name' => $pr->requester?->name,
                ];
                // proof_cell_id から対象行を特定し、同ステージグループの proof 列をすべて取得
                if ($pr->proof_cell_id) {
                    $proofCell = ProgressCell::find($pr->proof_cell_id);
                    if ($proofCell) {
                        $targetRowId = $proofCell->row_id;
                        $siblings    = $this->findProofSiblingsForProofKey($columnConfig, $proofCell->col_key);
                        $targetProofColKeys = !empty($siblings) ? $siblings : [$proofCell->col_key];
                    }
                }
            }
        }

        $user = Auth::user();

        return Inertia::render('ProofCoordinator/ProgressSheets/Show', [
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
            'rows'             => $rows->map(fn($r) => ['id' => $r->id, 'label' => $r->label]),
            'cells'            => $cells,
            'proofColumnDefs'  => $proofColumnDefs,
            'members'          => $this->buildMembersList($user),
            'stages'           => \App\Models\Stage::orderBy('sort_order')->orderBy('order_index')
                ->get(['id', 'name', 'company_id', 'department_id']),
            'difficulties'     => \App\Models\Difficulty::orderBy('sort_order')->get(['id', 'name']),
            'types'            => \App\Models\WorkItemType::orderBy('sort_order')->orderBy('name')
                ->get(['id', 'name', 'group', 'company_id', 'department_id']),
            'sizes'            => \App\Models\Size::orderBy('sort_order')->orderBy('name')
                ->get(['id', 'name', 'group', 'width', 'height', 'unit', 'company_id', 'department_id']),
            'statuses'         => \App\Models\Status::orderBy('sort_order')
                ->get(['id', 'name', 'slug', 'company_id', 'department_id']),
            'user_role'          => $user->user_role,
            'user_company_id'    => $user->company_id,
            'user_department_id' => $user->department_id,
            'proofRequestId'     => $proofRequestId,
            'proofRequestData'   => $proofRequestData,
            'targetRowId'        => $targetRowId,
            'targetProofColKeys' => $targetProofColKeys,
        ]);
    }

    public function assignPage(Request $request, ProgressSheet $sheet)
    {
        $sheet->load(['projectJob:id,title,client_id', 'projectJob.client:id,name']);
        $projectJob = $sheet->projectJob;

        $title          = $request->query('title', '');
        $rowId          = $request->query('row_id') ? (int) $request->query('row_id') : null;
        $colKey         = $request->query('col_key');
        $stageId        = $request->query('stage_id') ? (int) $request->query('stage_id') : null;
        $proofRequestId = $request->query('proof_request_id') ? (int) $request->query('proof_request_id') : null;

        $proofRequestData = null;
        if ($proofRequestId) {
            $pr = ProofRequest::with('requester:id,name')->find($proofRequestId);
            if ($pr) {
                if (!$title) $title = $pr->title . '-校正';
                $proofRequestData = [
                    'id'             => $pr->id,
                    'title'          => $pr->title,
                    'deadline'       => $pr->deadline?->toIso8601String(),
                    'note'           => $pr->note,
                    'requester_name' => $pr->requester?->name,
                ];
            }
        }

        $user    = Auth::user();
        $prefill = [
            'project_job_id' => $projectJob->id,
            '_client_id'     => (string) ($projectJob->client_id ?? ''),
            'title'          => $title,
            'stage_id'       => $stageId,
            'user_id'        => null,
            'company_id'     => $user->company_id,
            'department_id'  => $user->department_id,
        ];
        if ($proofRequestData && !empty($proofRequestData['deadline'])) {
            $prefill['desired_end_date'] = $proofRequestData['deadline'];
        }

        return Inertia::render('ProofCoordinator/ProgressSheets/Assign', [
            'sheet'       => ['id' => $sheet->id, 'name' => $sheet->name],
            'projectJob'  => $projectJob,
            'rowId'       => $rowId,
            'colKey'      => $colKey,
            'members'     => $this->buildMembersList($user),
            'assignments' => [$prefill],
            'types'       => \App\Models\WorkItemType::orderBy('sort_order')->orderBy('name')
                ->get(['id', 'name', 'group', 'company_id', 'department_id']),
            'sizes'       => \App\Models\Size::orderBy('sort_order')->orderBy('name')
                ->get(['id', 'name', 'group', 'width', 'height', 'unit', 'company_id', 'department_id']),
            'stages'      => \App\Models\Stage::orderBy('sort_order')->orderBy('order_index')
                ->get(['id', 'name', 'company_id', 'department_id']),
            'statuses'    => \App\Models\Status::orderBy('sort_order')
                ->get(['id', 'name', 'slug', 'company_id', 'department_id']),
            'difficulties'=> \App\Models\Difficulty::orderBy('sort_order')->get(['id', 'name']),
            'companies'   => [],
            'user_role'          => $user->user_role,
            'user_company_id'    => $user->company_id,
            'user_department_id' => $user->department_id,
            'proofRequest'       => $proofRequestData,
        ]);
    }

    public function assignStore(Request $request, ProgressSheet $sheet)
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

        $rowId          = $request->query('row_id') ? (int) $request->query('row_id') : null;
        $colKey         = $request->query('col_key');
        $proofRequestId = $request->query('proof_request_id') ? (int) $request->query('proof_request_id') : null;
        $slots          = $request->input('work_slots', []);

        $a          = $data['assignments'][0];
        $senderUser = Auth::user();

        $isDispatcher   = !empty($a['proof_dispatcher_id']);
        $assigneeUserId = $isDispatcher ? $senderUser->id : ($a['user_id'] ?? $senderUser->id);
        $dispatcherId   = $isDispatcher ? $a['proof_dispatcher_id'] : null;

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

            // ProgressCell に proof_assignment_id を紐づけ
            if ($rowId && $colKey) {
                $cell = ProgressCell::firstOrCreate(
                    ['row_id' => $rowId, 'col_key' => $colKey],
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
                    \Illuminate\Support\Facades\Log::warning('ProgressSheetProofController: notify failed', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        // work_slots から ProofSchedule・Event(pja101)を作成
        if ($proofRequestId && !empty($slots)) {
            $pr = ProofRequest::find($proofRequestId);
            if ($pr) {
                $this->saveWorkSlots($pr, $slots, false);
            }
        }

        return redirect()->route('proof_coordinator.progress_sheets.show', $sheet->id)
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
            ->ordered()
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
     * proof_cell の col_key を含む親ノードから、同ステージグループの proof 列キーをすべて返す。
     * 管理シートの findProofKeysForWorkerKey と同じ発想で proof 列同士のサイドリングを探す。
     */
    private function findProofSiblingsForProofKey(array $nodes, string $proofKey): array
    {
        foreach ($nodes as $node) {
            if (!empty($node['children'])) {
                $childKeys = array_column($node['children'], 'key');
                if (in_array($proofKey, $childKeys, true)) {
                    $proofKeys = [];
                    foreach ($node['children'] as $child) {
                        if (in_array($child['type'] ?? '', ['proof_v2', 'proof_user'], true)) {
                            $proofKeys[] = $child['key'];
                        }
                    }
                    return $proofKeys;
                }
                $found = $this->findProofSiblingsForProofKey($node['children'], $proofKey);
                if (!empty($found)) return $found;
            }
        }
        return [];
    }

    /**
     * column_config から proof_user / proof_v2 型のリーフノードを再帰抽出する。
     */
    private function extractProofLeaves(array $nodes, array $path = []): array
    {
        $leaves = [];
        foreach ($nodes as $node) {
            $type  = $node['type'] ?? '';
            $label = $node['label'] ?? '';

            if (in_array($type, ['proof_user', 'proof_v2'])) {
                $fullPath = array_merge($path, [$label]);
                $leaves[] = [
                    'key'   => $node['key'],
                    'label' => implode(' / ', array_filter($fullPath)),
                    'type'  => $type,
                ];
            }

            if (!empty($node['children'])) {
                $leaves = array_merge(
                    $leaves,
                    $this->extractProofLeaves($node['children'], array_merge($path, [$label]))
                );
            }
        }
        return $leaves;
    }
}
