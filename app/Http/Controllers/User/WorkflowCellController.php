<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
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

    private function authorizeUpdate($user, $projectJob): void
    {
        $isMember = $projectJob->user_id === $user->id
            || $projectJob->coordinators()->where('users.id', $user->id)->exists()
            || $projectJob->teamMembers()->where('user_id', $user->id)->exists();
        $isAdmin = in_array($user->user_role, ['admin', 'superadmin']);
        abort_unless($isMember || $isAdmin, 403);
    }
}
