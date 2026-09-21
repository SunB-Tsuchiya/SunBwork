<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\MGinbon\MGinbonProject;
use App\Models\Subcontractor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MGinbonActorMappingController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer'], 'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'unresolved', 'resolved'])],
        ]);
        $projects = MGinbonProject::query()->orderByDesc('year')->get(['id', 'year', 'name']);
        $project = isset($validated['year']) ? $projects->firstWhere('year', (int) $validated['year']) : $projects->first();
        abort_unless($project, 404);

        $query = DB::connection('mginbon')->table('mginbon_stage_task_participants as participants')
            ->join('mginbon_stage_tasks as tasks', 'tasks.id', '=', 'participants.mginbon_stage_task_id')
            ->join('mginbon_items as items', 'items.id', '=', 'tasks.mginbon_item_id')
            ->join('mginbon_production_units as units', 'units.id', '=', 'items.mginbon_production_unit_id')
            ->join('mginbon_stage_definitions as stages', 'stages.id', '=', 'tasks.mginbon_stage_definition_id')
            ->leftJoin('mginbon_legacy_actor_mappings as mappings', function ($join) use ($project) {
                $join->on('mappings.legacy_value', '=', 'participants.legacy_value')
                    ->where('mappings.mginbon_project_id', $project->id);
            })
            ->where('units.mginbon_project_id', $project->id)
            ->whereNotNull('participants.legacy_value')
            ->selectRaw('participants.legacy_value, COUNT(*) as usage_count, COUNT(DISTINCT items.id) as item_count')
            ->selectRaw('GROUP_CONCAT(DISTINCT stages.name ORDER BY stages.sort_order SEPARATOR "、") as stage_names')
            ->addSelect('mappings.target_type', 'mappings.target_id', 'mappings.target_label')
            ->groupBy('participants.legacy_value', 'mappings.target_type', 'mappings.target_id', 'mappings.target_label');

        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '') $query->where('participants.legacy_value', 'like', '%'.addcslashes($search, '\\%_').'%');
        $status = $validated['status'] ?? 'unresolved';
        if ($status === 'unresolved') $query->whereNull('mappings.id');
        if ($status === 'resolved') $query->whereNotNull('mappings.id');

        $companyId = $request->user()?->user_role === 'superadmin'
            ? session('superadmin_context.company_id')
            : $request->user()?->company_id;
        $globalSuperAdmin = $request->user()?->user_role === 'superadmin' && $companyId === null;
        $users = User::query()->when($globalSuperAdmin, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->ordered()->get(['id', 'name', 'department_id']);
        $subcontractors = Subcontractor::query()->when($globalSuperAdmin, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Coordinator/MGinbon/ActorMappings', [
            'project' => $project, 'projects' => $projects,
            'mappings' => $query->orderByDesc('usage_count')->orderBy('participants.legacy_value')->get(),
            'users' => $users, 'subcontractors' => $subcontractors,
            'filters' => ['year' => $project->year, 'search' => $search, 'status' => $status],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer'], 'legacy_value' => ['required', 'string', 'max:255'],
            'target' => ['required', 'string', 'max:100'],
        ]);
        $project = MGinbonProject::findOrFail($validated['project_id']);
        [$targetType, $targetId] = array_pad(explode(':', $validated['target'], 2), 2, null);
        abort_unless(in_array($targetType, ['user', 'subcontractor', 'ignore'], true), 422);

        $companyId = $request->user()?->user_role === 'superadmin'
            ? session('superadmin_context.company_id') : $request->user()?->company_id;
        abort_if($request->user()?->user_role === 'superadmin' && $companyId === null && $targetType !== 'ignore', 422, '会社を選択してください。');
        $target = null;
        if ($targetType === 'user') {
            $target = User::query()->when($companyId, fn ($q) => $q->where('company_id', $companyId))->findOrFail((int) $targetId);
        } elseif ($targetType === 'subcontractor') {
            $target = Subcontractor::query()->when($companyId, fn ($q) => $q->where('company_id', $companyId))->findOrFail((int) $targetId);
        }

        DB::connection('mginbon')->transaction(function () use ($request, $validated, $project, $targetType, $target) {
            $db = DB::connection('mginbon');
            $old = $db->table('mginbon_legacy_actor_mappings')->where('mginbon_project_id', $project->id)
                ->where('legacy_value', $validated['legacy_value'])->first();
            $values = [
                'target_type' => $targetType, 'target_id' => $target?->id, 'target_label' => $target?->name,
                'resolved_by' => $request->user()?->id, 'updated_at' => now(),
            ];
            $db->table('mginbon_legacy_actor_mappings')->updateOrInsert(
                ['mginbon_project_id' => $project->id, 'legacy_value' => $validated['legacy_value']],
                [...$values, 'created_at' => $old?->created_at ?? now()]
            );

            $participants = $db->table('mginbon_stage_task_participants as participants')
                ->join('mginbon_stage_tasks as tasks', 'tasks.id', '=', 'participants.mginbon_stage_task_id')
                ->join('mginbon_items as items', 'items.id', '=', 'tasks.mginbon_item_id')
                ->join('mginbon_production_units as units', 'units.id', '=', 'items.mginbon_production_unit_id')
                ->where('units.mginbon_project_id', $project->id)
                ->where('participants.legacy_value', $validated['legacy_value']);
            $participants->update([
                'participants.user_id' => $targetType === 'user' ? $target?->id : null,
                'participants.subcontractor_id' => $targetType === 'subcontractor' ? $target?->id : null,
                'participants.execution_type' => $targetType === 'subcontractor' ? 'subcontracted' : ($targetType === 'user' ? 'internal' : null),
                'participants.resolution_status' => $targetType === 'ignore' ? 'ignored' : 'resolved',
                'participants.updated_at' => now(),
            ]);
            $db->table('mginbon_change_logs')->insert([
                'mginbon_project_id' => $project->id, 'changed_by' => $request->user()?->id,
                'field_path' => 'actor_mapping.'.$validated['legacy_value'],
                'old_value' => json_encode($old ? ['type' => $old->target_type, 'id' => $old->target_id, 'label' => $old->target_label] : null, JSON_UNESCAPED_UNICODE),
                'new_value' => json_encode(['type' => $targetType, 'id' => $target?->id, 'label' => $target?->name], JSON_UNESCAPED_UNICODE),
                'source' => 'manual', 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return back()->with('success', '旧担当候補の対応を保存しました。');
    }
}
