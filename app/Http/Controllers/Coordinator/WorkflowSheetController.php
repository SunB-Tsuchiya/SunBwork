<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\ProjectJob;
use App\Models\WorkflowSheet;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowRow;
use App\Models\WorkflowCell;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class WorkflowSheetController extends Controller
{
    public function store(Request $request, ProjectJob $projectJob)
    {
        $this->authorizeJobAccess($request->user(), $projectJob);

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'template_id' => 'nullable|exists:workflow_templates,id',
            'stage_config'=> 'nullable|array',
        ]);

        $stageConfig = $validated['stage_config'] ?? null;

        if (!$stageConfig && !empty($validated['template_id'])) {
            $template = WorkflowTemplate::find($validated['template_id']);
            if ($template) {
                $stageConfig = $template->stage_config;
            }
        }

        if (empty($stageConfig)) {
            $stageConfig = [
                'stages' => [
                    ['key' => 'shinko',  'label' => '進行',  'type' => 'coordinator'],
                    ['key' => 'kumihan', 'label' => '組版',  'type' => 'worker'],
                    ['key' => 'kosei',   'label' => '校正',  'type' => 'worker'],
                    ['key' => 'kosei2',  'label' => '校正２','type' => 'worker'],
                ],
            ];
        }

        $sheet = WorkflowSheet::create([
            'project_job_id' => $projectJob->id,
            'template_id'    => $validated['template_id'] ?? null,
            'name'           => $validated['name'],
            'stage_config'   => $stageConfig,
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

        $canEdit = $this->canEdit($request->user(), $projectJob, $sheet);

        $rows = $sheet->rows()->orderBy('sort_order')->get(['id', 'parent_id', 'label', 'sort_order', 'item_entry_id']);

        $rawCells = WorkflowCell::whereIn('row_id', $rows->pluck('id'))
            ->with(['assignedUser:id,name,user_role', 'noteUser:id,name'])
            ->get();

        // events から作業時間をバッチ算出
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

        $templates = WorkflowTemplate::orderByDesc('updated_at')->get(['id', 'name']);

        $itemEntries = $projectJob->itemEntries()->orderBy('sort_order')->get(['id', 'name']);

        return Inertia::render('Coordinator/WorkflowSheets/Show', [
            'sheet' => [
                'id'           => $sheet->id,
                'name'         => $sheet->name,
                'stage_config' => $sheet->stage_config,
                'created_by'   => $sheet->created_by,
            ],
            'rows'             => $rows,
            'cells'            => $cells,
            'workerUsers'      => $workerUsers,
            'coordinatorUsers' => $coordinatorUsers,
            'itemEntries'      => $itemEntries,
            'templates'        => $templates,
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
            'name'         => 'sometimes|string|max:255',
            'stage_config' => 'sometimes|array',
        ]);

        $sheet->update($validated);

        return response()->json(['ok' => true, 'sheet' => ['id' => $sheet->id, 'name' => $sheet->name, 'stage_config' => $sheet->stage_config]]);
    }

    public function destroy(Request $request, WorkflowSheet $sheet)
    {
        $this->authorizeJobAccess($request->user(), $sheet->projectJob, $sheet);

        $sheet->delete();

        return redirect()->route('coordinator.project_jobs.show', $sheet->project_job_id)
            ->with('success', '工程シートを削除しました。');
    }

    // ─────────────────────────────────────────────────────────

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
