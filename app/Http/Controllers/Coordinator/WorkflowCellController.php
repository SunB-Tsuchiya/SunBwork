<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\ProjectJobAssignment;
use App\Models\WorkflowSheet;
use App\Models\WorkflowRow;
use App\Models\WorkflowCell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkflowCellController extends Controller
{
    public function bulkUpdate(Request $request, WorkflowSheet $sheet)
    {
        $this->authorizeSheet($request->user(), $sheet);

        $validated = $request->validate([
            'cells'                    => 'required|array',
            'cells.*.row_id'           => 'required|integer|exists:workflow_rows,id',
            'cells.*.stage_key'        => 'required|string|max:64',
            'cells.*.assigned_user_id' => 'nullable|integer|exists:users,id',
            'cells.*.assignment_id'    => 'nullable|integer|exists:project_job_assignments,id',
            'cells.*.cell_note'        => 'nullable|string',
        ]);

        $rowIds      = collect($validated['cells'])->pluck('row_id')->unique();
        $validRowIds = WorkflowRow::where('sheet_id', $sheet->id)->whereIn('id', $rowIds)->pluck('id');

        DB::transaction(function () use ($validated, $validRowIds, $request) {
            foreach ($validated['cells'] as $data) {
                if (!$validRowIds->contains($data['row_id'])) {
                    continue;
                }
                $attrs = [];
                if (array_key_exists('assigned_user_id', $data)) {
                    $attrs['assigned_user_id'] = $data['assigned_user_id'];
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
            ->with(['assignedUser:id,name', 'noteUser:id,name'])
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
        ]);

        $row = WorkflowRow::where('id', $validated['row_id'])
            ->where('sheet_id', $sheet->id)
            ->firstOrFail();

        $stages = collect($sheet->stage_config['stages'] ?? []);
        $stage  = $stages->firstWhere('key', $validated['stage_key']);
        abort_unless($stage, 422, 'ステージキーが見つかりません');

        $projectJob = $sheet->projectJob;
        $title = $validated['title']
            ?? "{$projectJob->title} - {$row->label}（{$stage['label']}）";

        $assignment = ProjectJobAssignment::create([
            'project_job_id'   => $projectJob->id,
            'user_id'          => $validated['user_id'],
            'sender_id'        => $request->user()->id,
            'title'            => $title,
            'assigned'         => true,
            'desired_end_date' => $validated['desired_end_date'] ?? null,
        ]);

        $cell = WorkflowCell::updateOrCreate(
            ['row_id' => $row->id, 'stage_key' => $validated['stage_key']],
            [
                'assigned_user_id' => $validated['user_id'],
                'assignment_id'    => $assignment->id,
                'completed_at'     => null,
            ]
        );

        $cell->load('assignedUser:id,name');

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
            'assignment_id' => null,
            'completed_at'  => null,
        ]);

        $cell->load('assignedUser:id,name');

        return response()->json([
            'cell' => $this->formatCell($cell, []),
        ]);
    }

    // ─────────────────────────────────────────────────────────

    private function formatCell(WorkflowCell $c, array $eventMinutes): array
    {
        $workMinutes = $c->assignment_id
            ? ($eventMinutes[$c->assignment_id] ?? 0)
            : 0;

        return [
            'id'                 => $c->id,
            'row_id'             => $c->row_id,
            'stage_key'          => $c->stage_key,
            'assigned_user_id'   => $c->assigned_user_id,
            'assigned_user_name' => $c->assignedUser?->name,
            'assignment_id'      => $c->assignment_id,
            'work_minutes'       => $workMinutes,
            'completed_at'       => $c->completed_at?->format('Y-m-d H:i:s'),
            'cell_note'          => $c->cell_note,
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
