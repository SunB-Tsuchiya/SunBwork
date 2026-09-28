<?php

namespace App\Services\MGinbon;

use App\Models\MGinbon\MGinbonProject;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MGinbonProjectAccess
{
    public function projectForItem(int $itemId): MGinbonProject
    {
        $projectId = DB::connection('mginbon')->table('mginbon_items as items')
            ->join('mginbon_production_units as units', 'units.id', '=', 'items.mginbon_production_unit_id')
            ->where('items.id', $itemId)->value('units.mginbon_project_id');
        abort_unless($projectId, 404);
        return MGinbonProject::findOrFail($projectId);
    }

    public function requireLinked(MGinbonProject $project): void
    {
        if (! $project->project_job_id) {
            throw ValidationException::withMessages(['project_job_id' => 'SBWork案件へ接続してから更新してください。LISTは接続前でも閲覧できます。']);
        }
    }

    public function isInUse(MGinbonProject $project): bool
    {
        $db = DB::connection('mginbon');
        if ($db->table('mginbon_work_packages')->where('mginbon_project_id', $project->id)->exists()) return true;

        $tasks = $db->table('mginbon_stage_tasks as tasks')
            ->join('mginbon_items as items', 'items.id', '=', 'tasks.mginbon_item_id')
            ->join('mginbon_production_units as units', 'units.id', '=', 'items.mginbon_production_unit_id')
            ->where('units.mginbon_project_id', $project->id);
        if ((clone $tasks)->whereNotNull('tasks.project_job_assignment_id')->exists()) return true;

        if ($db->table('mginbon_stage_task_participants as participants')
            ->join('mginbon_stage_tasks as tasks', 'tasks.id', '=', 'participants.mginbon_stage_task_id')
            ->join('mginbon_items as items', 'items.id', '=', 'tasks.mginbon_item_id')
            ->join('mginbon_production_units as units', 'units.id', '=', 'items.mginbon_production_unit_id')
            ->where('units.mginbon_project_id', $project->id)->exists()) return true;

        return $db->table('mginbon_milestones as milestones')
            ->join('mginbon_items as items', 'items.id', '=', 'milestones.mginbon_item_id')
            ->join('mginbon_production_units as units', 'units.id', '=', 'items.mginbon_production_unit_id')
            ->where('units.mginbon_project_id', $project->id)->where('milestones.source', 'manual')->exists();
    }

    public function requireLinkChangeAllowed(MGinbonProject $project, ?int $newProjectJobId): void
    {
        $oldProjectJobId = $project->project_job_id ? (int) $project->project_job_id : null;
        if ($oldProjectJobId === $newProjectJobId || $oldProjectJobId === null) return;
        if ($this->isInUse($project)) {
            throw ValidationException::withMessages(['project_job_id' => '工程データが利用済みのため、接続案件の変更・解除はできません。']);
        }
    }
}
