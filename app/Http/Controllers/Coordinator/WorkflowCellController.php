<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\JobAssignmentMessage;
use App\Models\ProjectJobAssignment;
use App\Models\WorkflowSheet;
use App\Models\WorkflowRow;
use App\Models\WorkflowCell;
use App\Services\JobNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkflowCellController extends Controller
{
    public function bulkUpdate(Request $request, WorkflowSheet $sheet)
    {
        $this->authorizeSheet($request->user(), $sheet);

        $validated = $request->validate([
            'cells'                           => 'required|array',
            'cells.*.row_id'                  => 'required|integer|exists:workflow_rows,id',
            'cells.*.stage_key'               => 'required|string|max:64',
            'cells.*.cell_type'               => 'nullable|string|max:32',
            'cells.*.assigned_user_id'        => 'nullable|integer|exists:users,id',
            'cells.*.value_text'              => 'nullable|string',
            'cells.*.value_date'              => 'nullable|date',
            'cells.*.value_bool'              => 'nullable|boolean',
            'cells.*.value_user_id'           => 'nullable|integer|exists:users,id',
            'cells.*.value_subcontractor_id'  => 'nullable|integer|exists:subcontractors,id',
            'cells.*.proof_assignment_id'     => 'nullable|integer|exists:project_job_assignments,id',
            'cells.*.schedule_id'             => 'nullable|integer|exists:project_schedules,id',
            'cells.*.cell_deadline'           => 'nullable|date',
            'cells.*.assignment_id'           => 'nullable|integer|exists:project_job_assignments,id',
            'cells.*.completed_at'            => 'nullable|string',
            'cells.*.cell_note'               => 'nullable|string',
        ]);

        $scalarFields = [
            'cell_type', 'assigned_user_id', 'value_text', 'value_date', 'value_bool',
            'value_user_id', 'value_subcontractor_id', 'proof_assignment_id',
            'schedule_id', 'cell_deadline', 'completed_at',
        ];

        $rowIds      = collect($validated['cells'])->pluck('row_id')->unique();
        $validRowIds = WorkflowRow::where('sheet_id', $sheet->id)->whereIn('id', $rowIds)->pluck('id');

        DB::transaction(function () use ($validated, $validRowIds, $scalarFields, $request) {
            foreach ($validated['cells'] as $data) {
                if (!$validRowIds->contains($data['row_id'])) {
                    continue;
                }
                $attrs = [];
                foreach ($scalarFields as $field) {
                    if (array_key_exists($field, $data)) {
                        $attrs[$field] = $data[$field];
                    }
                }
                if (array_key_exists('cell_note', $data)) {
                    $attrs['cell_note'] = $data['cell_note'];
                    if ($data['cell_note']) {
                        $attrs['cell_note_user_id'] = $request->user()->id;
                    }
                }
                if (!empty($attrs)) {
                    WorkflowCell::updateOrCreate(
                        ['row_id' => $data['row_id'], 'stage_key' => $data['stage_key']],
                        $attrs
                    );
                }
            }
        });

        $updatedCells = WorkflowCell::whereIn('row_id', $validRowIds)
            ->with([
                'assignedUser:id,name,user_role',
                'valueUser:id,name',
                'valueSubcontractor:id,name',
                'proofAssignment:id,title,completed,proof_completed_at,user_id',
                'schedule:id,name,end_date',
                'noteUser:id,name,user_role',
            ])
            ->get();

        $assignmentIds = $updatedCells->whereNotNull('assignment_id')->pluck('assignment_id')->unique();
        $eventMinutes  = $this->batchEventMinutes($assignmentIds->toArray());

        return response()->json([
            'cells' => $updatedCells->map(fn($c) => $this->formatCell($c, $eventMinutes)),
        ]);
    }

    /**
     * 担当者を登録し ProjectJobAssignment を作成してセルに紐づける
     * POST coordinator/workflow-sheets/{sheet}/cells/register
     */
    public function register(Request $request, WorkflowSheet $sheet)
    {
        $this->authorizeSheet($request->user(), $sheet);

        $validated = $request->validate([
            'row_id'           => 'required|integer|exists:workflow_rows,id',
            'stage_key'        => 'required|string|max:64',
            'user_id'          => 'required|integer|exists:users,id',
            'desired_end_date' => 'nullable|date',
            'title'            => 'nullable|string|max:255',
            'stage_id'         => 'nullable|integer|exists:stages,id',
        ]);

        $row = WorkflowRow::where('id', $validated['row_id'])
            ->where('sheet_id', $sheet->id)
            ->firstOrFail();

        $col = $this->findColumnByKey($sheet->getEffectiveColumnConfig(), $validated['stage_key']);
        abort_unless($col, 422, 'ステージキーが見つかりません');

        $projectJob = $sheet->projectJob;
        $title = $validated['title']
            ?? "{$projectJob->title} - {$row->label}（{$col['label']}）";

        $assignment = ProjectJobAssignment::create([
            'project_job_id'   => $projectJob->id,
            'user_id'          => $validated['user_id'],
            'sender_id'        => $request->user()->id,
            'title'            => $title,
            'assigned'         => true,
            'desired_end_date' => $validated['desired_end_date'] ?? null,
            'stage_id'         => $validated['stage_id'] ?? $row->stage_id ?? null,
        ]);

        $cell = WorkflowCell::updateOrCreate(
            ['row_id' => $row->id, 'stage_key' => $validated['stage_key']],
            [
                'assigned_user_id' => $validated['user_id'],
                'value_user_id'    => $validated['user_id'],
                'assignment_id'    => $assignment->id,
                'completed_at'     => null,
            ]
        );

        $cell->load('assignedUser:id,name');

        // ─── 依頼発信: JobAssignmentMessage 作成 + 通知 ───────────────────
        $senderUser = $request->user();

        // ① ジョブ通知（job_notifications テーブル）
        try {
            $projectJob->load('coordinators');
            JobNotificationService::notifyNewJob(
                $senderUser,
                $validated['user_id'],
                $projectJob,
                $assignment
            );
        } catch (\Throwable $e) {
            Log::warning('WorkflowCell::register notifyNewJob failed', ['error' => $e->getMessage()]);
        }

        // ② JobAssignmentMessage 作成（JobBox に依頼メッセージを発信）
        try {
            $assignedUser = \App\Models\User::find($validated['user_id']);
            $bodyText = implode("\n", array_filter([
                'ジョブ割り当て依頼',
                '案件: ' . ($projectJob->title ?? ''),
                'ジョブ: ' . $title,
                $assignedUser ? '担当: ' . $assignedUser->name : null,
                !empty($validated['desired_end_date']) ? '締め切り: ' . $validated['desired_end_date'] : null,
            ]));

            $jam = JobAssignmentMessage::create([
                'project_job_assignment_id' => $assignment->id,
                'sender_id'                => $senderUser->id,
                'subject'                  => $title,
                'body'                     => $bodyText,
            ]);

            try {
                $jamLoaded = JobAssignmentMessage::with([
                    'sender',
                    'projectJobAssignment.user',
                    'projectJobAssignment.projectJob.client',
                ])->find($jam->id);
                event(new \App\Events\JobMessageCreated(
                    $jamLoaded,
                    [$validated['user_id']],
                    $jam->id
                ));
            } catch (\Throwable $_) {
                // WebSocket broadcast 失敗は非致命的
            }
        } catch (\Throwable $eSend) {
            Log::warning('WorkflowCell::register JobAssignmentMessage creation failed', [
                'error' => $eSend->getMessage(),
            ]);
        }
        // ────────────────────────────────────────────────────────────────

        // ③ proof_v2/proof_worker ステージ: ProofRequest を作成して校正ジョブ一覧に表示
        if (in_array($col['type'] ?? '', ['proof_worker', 'proof_v2']) && $assignment->user_id) {
            try {
                \App\Models\ProofRequest::updateOrCreate(
                    ['project_job_assignment_id' => $assignment->id],
                    [
                        'project_job_id'       => $projectJob->id,
                        'proof_cell_id'        => null, // workflow_cell 経由（progress_cell ではない）
                        'requester_id'         => $senderUser->id,
                        'proof_coordinator_id' => $senderUser->id,
                        'proofreader_id'       => $assignment->user_id,
                        'title'                => $title,
                        'status'               => 'assigned',
                    ]
                );
            } catch (\Throwable $eProof) {
                Log::warning('WorkflowCell::register ProofRequest creation failed', ['error' => $eProof->getMessage()]);
            }
        }

        return response()->json([
            'cell' => $this->formatCell($cell, []),
        ]);
    }

    /**
     * 完了トグル（Coordinator用）
     * POST coordinator/workflow-cells/{cell}/complete
     */
    public function complete(Request $request, WorkflowCell $cell)
    {
        $sheet = $cell->row->sheet;
        $this->authorizeSheet($request->user(), $sheet);

        if ($cell->completed_at) {
            $cell->update(['completed_at' => null]);
            if ($cell->assignment_id) {
                ProjectJobAssignment::where('id', $cell->assignment_id)
                    ->update(['completed' => false]);
            }
        } else {
            $cell->update(['completed_at' => now()]);
            if ($cell->assignment_id) {
                ProjectJobAssignment::where('id', $cell->assignment_id)
                    ->update(['completed' => true]);
            }
        }

        $workMinutes = $cell->assignment_id
            ? $this->singleEventMinutes($cell->assignment_id)
            : 0;

        return response()->json([
            'completed_at' => $cell->completed_at?->format('Y-m-d H:i:s'),
            'work_minutes' => $workMinutes,
        ]);
    }

    /**
     * 登録解除（assignment_id をクリア）
     * POST coordinator/workflow-cells/{cell}/unregister
     */
    public function unregister(Request $request, WorkflowCell $cell)
    {
        $sheet = $cell->row->sheet;
        $this->authorizeSheet($request->user(), $sheet);

        $cell->update([
            'assignment_id'    => null,
            'completed_at'     => null,
            'assigned_user_id' => null,
            'value_user_id'    => null,
        ]);

        $cell->load('assignedUser:id,name');

        return response()->json([
            'cell' => $this->formatCell($cell, []),
        ]);
    }

    // ─────────────────────────────────────────────────────────

    private function formatCell(WorkflowCell $c, array $eventMinutes): array
    {
        $workMinutes = $c->assignment_id ? ($eventMinutes[$c->assignment_id] ?? 0) : 0;

        return [
            'id'                         => $c->id,
            'row_id'                     => $c->row_id,
            'stage_key'                  => $c->stage_key,
            'cell_type'                  => $c->cell_type ?? 'worker',
            'value_text'                 => $c->value_text,
            'value_date'                 => $c->value_date?->format('Y-m-d'),
            'value_bool'                 => $c->value_bool,
            'value_user_id'              => $c->value_user_id ?? $c->assigned_user_id,
            'value_user_name'            => $c->valueUser?->name ?? $c->assignedUser?->name,
            'value_subcontractor_id'     => $c->value_subcontractor_id,
            'value_subcontractor_name'   => $c->valueSubcontractor?->name,
            'assignment_id'              => $c->assignment_id,
            'proof_assignment_id'        => $c->proof_assignment_id,
            'proof_assignment_title'     => $c->proofAssignment?->title,
            'proof_assignment_completed' => $c->proofAssignment?->completed
                                            || $c->proofAssignment?->proof_completed_at !== null,
            'schedule_id'                => $c->schedule_id,
            'schedule_name'              => $c->schedule?->name,
            'schedule_end_date'          => $c->schedule?->end_date?->format('Y-m-d'),
            'cell_deadline'              => $c->cell_deadline?->format('Y-m-d'),
            'cell_note'                  => $c->cell_note,
            'cell_note_user_name'        => $c->noteUser?->name,
            'cell_note_user_role'        => $c->noteUser?->user_role,
            'completed_at'               => $c->completed_at?->format('Y-m-d H:i:s'),
            'work_minutes'               => $workMinutes,
        ];
    }

    private function batchEventMinutes(array $assignmentIds): array
    {
        if (empty($assignmentIds)) {
            return [];
        }
        return DB::table('events')
            ->whereIn('project_job_assignment_id', $assignmentIds)
            ->whereNotNull('ends_at')
            ->selectRaw('project_job_assignment_id,
                COALESCE(SUM(TIMESTAMPDIFF(MINUTE, starts_at, ends_at)
                    - COALESCE(interruption_minutes, 0)), 0) as total')
            ->groupBy('project_job_assignment_id')
            ->pluck('total', 'project_job_assignment_id')
            ->toArray();
    }

    private function singleEventMinutes(int $assignmentId): int
    {
        return (int) DB::table('events')
            ->where('project_job_assignment_id', $assignmentId)
            ->whereNotNull('ends_at')
            ->selectRaw('COALESCE(SUM(TIMESTAMPDIFF(MINUTE, starts_at, ends_at)
                - COALESCE(interruption_minutes, 0)), 0) as total')
            ->value('total');
    }

    private function findColumnByKey(array $nodes, string $key): ?array
    {
        foreach ($nodes as $node) {
            if (($node['key'] ?? '') === $key) return $node;
            if (!empty($node['children'])) {
                $found = $this->findColumnByKey($node['children'], $key);
                if ($found) return $found;
            }
        }
        return null;
    }

    private function authorizeSheet($user, WorkflowSheet $sheet): void
    {
        $job       = $sheet->projectJob;
        $isOwner   = $job->user_id === $user->id;
        $isSub     = $job->coordinators()->where('users.id', $user->id)->exists();
        $isAdmin   = in_array($user->user_role, ['admin', 'superadmin']);
        $isCreator = $sheet->created_by === $user->id;

        abort_unless($isOwner || $isSub || $isAdmin || $isCreator, 403);
    }
}
