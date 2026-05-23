<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ProjectJobAssignment;
use App\Models\WorkflowSheet;
use App\Models\WorkflowRow;
use App\Models\WorkflowCell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkflowCellController extends Controller
{
    public function update(Request $request, WorkflowSheet $sheet)
    {
        $authUser   = $request->user();
        $projectJob = $sheet->projectJob;
        $this->authorizeUpdate($authUser, $projectJob);

        $validated = $request->validate([
            'cells'                    => 'required|array',
            'cells.*.row_id'           => 'required|integer|exists:workflow_rows,id',
            'cells.*.stage_key'        => 'required|string|max:64',
            'cells.*.assigned_user_id' => 'nullable|integer',
            'cells.*.work_date'        => 'nullable|date',
            'cells.*.work_minutes'     => 'nullable|integer|min:0',
        ]);

        $validRowIds = WorkflowRow::where('sheet_id', $sheet->id)->pluck('id');
        $stages      = collect($sheet->stage_config['stages'] ?? []);

        DB::transaction(function () use ($validated, $validRowIds, $stages, $authUser) {
            foreach ($validated['cells'] as $data) {
                if (!$validRowIds->contains($data['row_id'])) {
                    continue;
                }

                $stage = $stages->firstWhere('key', $data['stage_key']);
                if (!$stage) {
                    continue;
                }

                $cell = WorkflowCell::where('row_id', $data['row_id'])
                    ->where('stage_key', $data['stage_key'])
                    ->first();

                if ($cell && $cell->assigned_user_id !== $authUser->id) {
                    $isAdmin = in_array($authUser->user_role, ['admin', 'superadmin', 'coordinator', 'clerk', 'leader']);
                    if (!$isAdmin) {
                        continue;
                    }
                }

                WorkflowCell::updateOrCreate(
                    ['row_id' => $data['row_id'], 'stage_key' => $data['stage_key']],
                    [
                        'assigned_user_id' => $data['assigned_user_id'] ?? ($cell->assigned_user_id ?? $authUser->id),
                        'work_date'        => $data['work_date'] ?? null,
                        'work_minutes'     => $data['work_minutes'] ?? null,
                    ]
                );
            }
        });

        return response()->json(['ok' => true]);
    }

    public function register(Request $request, WorkflowSheet $sheet)
    {
        $authUser   = $request->user();
        $projectJob = $sheet->projectJob;
        $this->authorizeUpdate($authUser, $projectJob);

        $validated = $request->validate([
            'row_id'    => 'required|integer|exists:workflow_rows,id',
            'stage_key' => 'required|string|max:64',
        ]);

        $row = WorkflowRow::where('id', $validated['row_id'])
            ->where('sheet_id', $sheet->id)
            ->firstOrFail();

        $cell = WorkflowCell::where('row_id', $row->id)
            ->where('stage_key', $validated['stage_key'])
            ->first();

        if ($cell?->assignment_id) {
            return response()->json(['error' => '既に登録済みです'], 422);
        }

        // ステージキーから列ラベルを再帰検索
        $col      = $this->findColumnByKey($sheet->getEffectiveColumnConfig(), $validated['stage_key']);
        $colLabel = $col['label'] ?? $validated['stage_key'];

        // 行ラベルが "_default" の場合は列ラベルのみ使用
        $rowPart = ($row->label === '_default') ? '' : " - {$row->label}";
        $title   = "{$projectJob->title}{$rowPart}（{$colLabel}）";

        $assignment = ProjectJobAssignment::create([
            'project_job_id' => $projectJob->id,
            'user_id'        => $authUser->id,
            'sender_id'      => $authUser->id,
            'title'          => $title,
            'assigned'       => true,
        ]);

        $cell = WorkflowCell::updateOrCreate(
            ['row_id' => $row->id, 'stage_key' => $validated['stage_key']],
            [
                'assigned_user_id' => $authUser->id,
                'value_user_id'    => $authUser->id,
                'assignment_id'    => $assignment->id,
                'completed_at'     => null,
            ]
        );

        $cell->load('assignedUser:id,name');

        return response()->json([
            'cell' => [
                'id'                 => $cell->id,
                'row_id'             => $cell->row_id,
                'stage_key'          => $cell->stage_key,
                'assigned_user_id'   => $cell->assigned_user_id,
                'assigned_user_name' => $cell->assignedUser?->name,
                'assignment_id'      => $assignment->id,
                'work_minutes'       => 0,
                'completed_at'       => null,
                'cell_note'          => null,
            ],
        ]);
    }

    public function complete(Request $request, WorkflowCell $cell)
    {
        $authUser   = $request->user();
        $projectJob = $cell->row->sheet->projectJob;
        $this->authorizeUpdate($authUser, $projectJob);

        $isAdmin    = in_array($authUser->user_role, ['admin', 'superadmin', 'coordinator', 'clerk', 'leader']);
        $isAssigned = $cell->assigned_user_id === $authUser->id;
        abort_unless($isAdmin || $isAssigned, 403);

        $cell->completed_at = $cell->completed_at ? null : now();
        $cell->save();

        if ($cell->assignment_id) {
            \App\Models\ProjectJobAssignment::where('id', $cell->assignment_id)
                ->update(['completed' => (bool) $cell->completed_at]);
        }

        $workMinutes = 0;
        if ($cell->assignment_id) {
            $workMinutes = (int) DB::table('events')
                ->where('project_job_assignment_id', $cell->assignment_id)
                ->whereNotNull('ends_at')
                ->selectRaw('COALESCE(SUM(TIMESTAMPDIFF(MINUTE, starts_at, ends_at)
                    - COALESCE(interruption_minutes, 0)), 0) as total')
                ->value('total');
        }

        return response()->json([
            'completed_at' => $cell->completed_at?->format('Y-m-d H:i:s'),
            'work_minutes' => $workMinutes,
        ]);
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

    private function authorizeUpdate($user, $projectJob): void
    {
        $isMember = $projectJob->user_id === $user->id
            || $projectJob->coordinators()->where('users.id', $user->id)->exists()
            || $projectJob->teamMembers()->where('user_id', $user->id)->exists();
        $isAdmin = in_array($user->user_role, ['admin', 'superadmin']);
        abort_unless($isMember || $isAdmin, 403);
    }
}
