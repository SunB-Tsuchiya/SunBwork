<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\WorkflowSheet;
use App\Models\WorkflowCell;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class WorkflowSheetController extends Controller
{
    public function show(Request $request, WorkflowSheet $sheet)
    {
        $sheet->load(['projectJob.client', 'projectJob.user', 'projectJob.coordinators']);
        $projectJob = $sheet->projectJob;

        $authUser = $request->user();
        $this->authorizeView($authUser, $projectJob);

        $rows = $sheet->rows()->orderBy('sort_order')->get(['id', 'parent_id', 'label', 'sort_order', 'item_entry_id']);

        $rawCells = WorkflowCell::whereIn('row_id', $rows->pluck('id'))
            ->with(['assignedUser:id,name,user_role', 'noteUser:id,name'])
            ->get();

        $assignmentIds = $rawCells->whereNotNull('assignment_id')->pluck('assignment_id')->unique()->toArray();
        $eventMinutes  = [];
        if (!empty($assignmentIds)) {
            $eventMinutes = DB::table('events')
                ->whereIn('project_job_assignment_id', $assignmentIds)
                ->whereNotNull('ends_at')
                ->selectRaw('project_job_assignment_id,
                    COALESCE(SUM(TIMESTAMPDIFF(MINUTE, starts_at, ends_at)
                        - COALESCE(interruption_minutes, 0)), 0) as total')
                ->groupBy('project_job_assignment_id')
                ->pluck('total', 'project_job_assignment_id')
                ->toArray();
        }

        $cells = $rawCells->map(fn($c) => [
            'id'                 => $c->id,
            'row_id'             => $c->row_id,
            'stage_key'          => $c->stage_key,
            'assigned_user_id'   => $c->assigned_user_id,
            'assigned_user_name' => $c->assignedUser?->name,
            'assignment_id'      => $c->assignment_id,
            'work_minutes'       => $c->assignment_id ? ($eventMinutes[$c->assignment_id] ?? 0) : 0,
            'completed_at'       => $c->completed_at?->format('Y-m-d H:i:s'),
            'cell_note'          => $c->cell_note,
            'cell_note_user_name' => $c->noteUser?->name,
        ]);

        $memberIds  = $projectJob->teamMembers()->pluck('user_id')->toArray();
        $coIds      = $projectJob->coordinators->pluck('id')->toArray();
        $ownerId    = $projectJob->user_id;
        $allUserIds = array_unique(array_merge($memberIds, $coIds, [$ownerId]));

        $allUsers = User::whereIn('id', $allUserIds)
            ->orderBy('name')
            ->get(['id', 'name', 'user_role']);

        $workerUsers      = $allUsers->values();
        $coordinatorUsers = $allUsers->whereIn('user_role', ['coordinator', 'clerk', 'leader', 'admin', 'superadmin'])->values();

        return Inertia::render('User/WorkflowSheets/Show', [
            'sheet' => [
                'id'            => $sheet->id,
                'name'          => $sheet->name,
                'column_config' => $sheet->getEffectiveColumnConfig(),
            ],
            'rows'             => $rows,
            'cells'            => $cells,
            'workerUsers'      => $workerUsers,
            'coordinatorUsers' => $coordinatorUsers,
            'authUserId'       => $authUser->id,
            'projectJob'       => [
                'id'          => $projectJob->id,
                'title'       => $projectJob->title,
                'client_name' => $projectJob->client?->name,
            ],
        ]);
    }

    private function authorizeView($user, $projectJob): void
    {
        $isMember = $projectJob->user_id === $user->id
            || $projectJob->coordinators()->where('users.id', $user->id)->exists()
            || $projectJob->teamMembers()->where('user_id', $user->id)->exists();
        $isAdmin = in_array($user->user_role, ['admin', 'superadmin']);
        abort_unless($isMember || $isAdmin, 403);
    }
}
