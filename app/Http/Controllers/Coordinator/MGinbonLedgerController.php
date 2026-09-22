<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\MGinbon\MGinbonProject;
use App\Models\ProjectJob;
use App\Services\ProjectJobAssigneeOptions;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MGinbonLedgerController extends Controller
{
    private const SUBJECT_ORDER = ['japanese', 'math', 'social', 'science'];

    public function index(Request $request, ProjectJobAssigneeOptions $assigneeOptions): Response
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'search' => ['nullable', 'string', 'max:100'],
            'media' => ['nullable', 'string', 'max:100'],
            'subject' => ['nullable', Rule::in(['', ...self::SUBJECT_ORDER])],
            'status' => ['nullable', Rule::in(['all', 'draft', 'review_required'])],
            'view' => ['nullable', Rule::in(['list', 'intake'])],
            'per_page' => ['nullable', Rule::in([10, 25, 50])],
        ]);

        $projects = MGinbonProject::query()->orderByDesc('year')->get(['id', 'project_job_id', 'year', 'name', 'status']);
        $project = isset($validated['year'])
            ? $projects->firstWhere('year', (int) $validated['year'])
            : $projects->first();

        abort_unless($project, 404, 'MGinbon年度プロジェクトがありません。');

        $filters = [
            'year' => $project->year,
            'search' => trim((string) ($validated['search'] ?? '')),
            'media' => (string) ($validated['media'] ?? ''),
            'subject' => (string) ($validated['subject'] ?? ''),
            'status' => (string) ($validated['status'] ?? 'all'),
            'view' => (string) ($validated['view'] ?? 'list'),
            'per_page' => (int) ($validated['per_page'] ?? 10),
        ];

        $mediaOptions = $this->itemsQuery($project->id)->distinct()->orderBy('media_name')->pluck('media_name')->values();
        $query = $this->applyUnitFilters($this->unitsQuery($project->id), $filters);

        /** @var LengthAwarePaginator $units */
        $units = $query
            ->orderByRaw('CASE WHEN units.mikuni_code REGEXP "^[0-9]+$" THEN CAST(units.mikuni_code AS UNSIGNED) ELSE 999999 END')
            ->orderBy('units.mikuni_code')
            ->orderBy('units.display_name')
            ->paginate($filters['per_page'])
            ->withQueryString();

        $unitIds = $units->getCollection()->pluck('id');
        $pageItems = $this->applyItemFilters($this->itemsQuery($project->id), $filters)
            ->whereIn('units.id', $unitIds)
            ->orderBy('media.sort_order')->orderBy('items.id')->get();
        $hydratedItems = $this->hydrateItems($pageItems)->groupBy('unit_id');
        $units->setCollection($units->getCollection()->map(function ($unit) use ($hydratedItems) {
            $unit->items = $hydratedItems->get($unit->id, collect())->values();
            return $unit;
        }));

        $summaryQuery = $this->itemsQuery($project->id);
        $summary = [
            'total' => (clone $summaryQuery)->count(),
            'review_required' => (clone $summaryQuery)->where('items.review_status', 'review_required')->count(),
            'units' => DB::connection('mginbon')->table('mginbon_production_units')
                ->where('mginbon_project_id', $project->id)->count(),
        ];

        $actorOptions = ['users' => collect(), 'subcontractors' => collect()];
        if ($project->project_job_id && ($projectJob = ProjectJob::find($project->project_job_id))) {
            $actorOptions = $assigneeOptions->for($projectJob, $request->user());
        }

        return Inertia::render('Coordinator/MGinbon/LedgerIndex', [
            'project' => $project,
            'projects' => $projects,
            'projectJobs' => $this->projectJobOptions($request),
            'units' => $units,
            'summary' => $summary,
            'mediaOptions' => $mediaOptions,
            'subjects' => [
                ['code' => 'japanese', 'name' => '国語'],
                ['code' => 'math', 'name' => '算数'],
                ['code' => 'social', 'name' => '社会'],
                ['code' => 'science', 'name' => '理科'],
            ],
            'filters' => $filters,
            'actorOptions' => $actorOptions,
        ]);
    }

    private function projectJobOptions(Request $request)
    {
        $companyId = $request->user()?->user_role === 'superadmin'
            ? session('superadmin_context.company_id') : $request->user()?->company_id;
        if ($request->user()?->user_role === 'superadmin' && $companyId === null) return collect();

        return ProjectJob::query()->where('company_id', $companyId)->where('completed', false)
            ->orderByDesc('id')->get(['id', 'jobcode', 'title']);
    }

    private function itemsQuery(int $projectId): Builder
    {
        return DB::connection('mginbon')->table('mginbon_items as items')
            ->join('mginbon_production_units as units', 'units.id', '=', 'items.mginbon_production_unit_id')
            ->join('mginbon_media_types as media', 'media.id', '=', 'items.mginbon_media_type_id')
            ->where('units.mginbon_project_id', $projectId)
            ->select([
                'items.id', 'items.mginbon_import_row_id', 'items.publication_status', 'items.note', 'items.updated_at',
                'items.review_status', 'units.id as unit_id', 'units.unit_type', 'units.mikuni_code',
                'units.n_code', 'units.display_name', 'units.school_category', 'units.n_category',
                'media.name as media_name',
            ]);
    }

    private function unitsQuery(int $projectId): Builder
    {
        return DB::connection('mginbon')->table('mginbon_production_units as units')
            ->where('units.mginbon_project_id', $projectId)
            ->select([
                'units.id', 'units.unit_type', 'units.mikuni_code', 'units.n_code',
                'units.display_name', 'units.school_category', 'units.n_category', 'units.review_status',
            ]);
    }

    /** @param array<string, mixed> $filters */
    private function applyUnitFilters(Builder $query, array $filters): Builder
    {
        if ($filters['search'] !== '') {
            $search = addcslashes($filters['search'], '\\%_');
            $query->where(function (Builder $inner) use ($search) {
                $inner->where('units.display_name', 'like', "%{$search}%")
                    ->orWhere('units.mikuni_code', 'like', "%{$search}%")
                    ->orWhere('units.n_code', 'like', "%{$search}%");
            });
        }
        if ($filters['media'] !== '' || $filters['status'] !== 'all' || $filters['subject'] !== '') {
            $query->whereExists(function (Builder $inner) use ($filters) {
                $inner->selectRaw('1')->from('mginbon_items as filter_items')
                    ->join('mginbon_media_types as filter_media', 'filter_media.id', '=', 'filter_items.mginbon_media_type_id')
                    ->whereColumn('filter_items.mginbon_production_unit_id', 'units.id');
                $this->applyNestedItemFilters($inner, $filters, 'filter_items', 'filter_media');
            });
        }

        return $query;
    }

    /** @param array<string, mixed> $filters */
    private function applyItemFilters(Builder $query, array $filters): Builder
    {
        $this->applyNestedItemFilters($query, $filters, 'items', 'media');
        return $query;
    }

    /** @param array<string, mixed> $filters */
    private function applyNestedItemFilters(Builder $query, array $filters, string $itemsAlias, string $mediaAlias): void
    {
        if ($filters['media'] !== '') $query->where("{$mediaAlias}.name", $filters['media']);
        if ($filters['status'] !== 'all') $query->where("{$itemsAlias}.review_status", $filters['status']);
        if ($filters['subject'] !== '') {
            $query->whereExists(function (Builder $inner) use ($filters, $itemsAlias) {
                $inner->selectRaw('1')->from('mginbon_item_subjects as filter_item_subjects')
                    ->join('mginbon_subjects as filter_subjects', 'filter_subjects.id', '=', 'filter_item_subjects.mginbon_subject_id')
                    ->whereColumn('filter_item_subjects.mginbon_item_id', "{$itemsAlias}.id")
                    ->where('filter_subjects.code', $filters['subject']);
            });
        }
    }

    private function hydrateItems($items)
    {
        $itemIds = $items->pluck('id');
        if ($itemIds->isEmpty()) {
            return $items;
        }

        $subjectRows = DB::connection('mginbon')->table('mginbon_item_subjects as item_subjects')
            ->join('mginbon_subjects as subjects', 'subjects.id', '=', 'item_subjects.mginbon_subject_id')
            ->whereIn('item_subjects.mginbon_item_id', $itemIds)
            ->orderBy('subjects.sort_order')
            ->get(['item_subjects.id', 'item_subjects.mginbon_item_id', 'subjects.code', 'subjects.name']);
        $subjectIds = $subjectRows->pluck('id');

        $measurements = DB::connection('mginbon')->table('mginbon_work_measurements')
            ->whereIn('mginbon_item_subject_id', $subjectIds)
            ->get()->groupBy('mginbon_item_subject_id');
        $milestones = DB::connection('mginbon')->table('mginbon_milestones')
            ->whereIn('mginbon_item_id', $itemIds)
            ->get()->groupBy('mginbon_item_id');
        $tasks = DB::connection('mginbon')->table('mginbon_stage_tasks as tasks')
            ->join('mginbon_stage_definitions as stages', 'stages.id', '=', 'tasks.mginbon_stage_definition_id')
            ->whereIn('tasks.mginbon_item_id', $itemIds)
            ->orderBy('stages.sort_order')
            ->get([
                'tasks.id', 'tasks.mginbon_item_id', 'tasks.mginbon_item_subject_id', 'tasks.status',
                'tasks.project_job_assignment_id', 'stages.id as stage_id', 'stages.code', 'stages.name',
            ]);
        $taskIds = $tasks->pluck('id');
        $participants = DB::connection('mginbon')->table('mginbon_stage_task_participants')
            ->whereIn('mginbon_stage_task_id', $taskIds)
            ->orderByDesc('id')->get()->groupBy('mginbon_stage_task_id');
        $packages = DB::connection('mginbon')->table('mginbon_work_package_tasks as package_tasks')
            ->join('mginbon_work_packages as packages', 'packages.id', '=', 'package_tasks.mginbon_work_package_id')
            ->whereIn('package_tasks.mginbon_stage_task_id', $taskIds)
            ->whereIn('packages.status', ['planned', 'assigned', 'in_progress', 'completed'])
            ->orderByDesc('packages.id')
            ->get([
                'package_tasks.mginbon_stage_task_id', 'packages.id', 'packages.user_id',
                'packages.subcontractor_id', 'packages.project_job_assignment_id', 'packages.status',
                'packages.assigned_at', 'packages.completed_at',
            ])->groupBy('mginbon_stage_task_id')->map->first();
        $userNames = \App\Models\User::query()->whereIn('id', $packages->pluck('user_id')->filter()->unique())
            ->pluck('name', 'id');
        $subcontractorNames = \App\Models\Subcontractor::query()
            ->whereIn('id', $packages->pluck('subcontractor_id')->filter()->unique())
            ->pluck('name', 'id');
        $tasks = $tasks->groupBy('mginbon_item_subject_id');

        $subjectsByItem = $subjectRows->groupBy('mginbon_item_id');

        return $items->map(function ($item) use ($subjectsByItem, $measurements, $milestones, $tasks, $participants, $packages, $userNames, $subcontractorNames) {
            $itemMilestones = $milestones->get($item->id, collect());
            $item->shared_dates = $itemMilestones->whereNull('mginbon_item_subject_id')
                ->mapWithKeys(fn ($date) => [$date->code => $date->occurred_on]);
            $item->subjects = $subjectsByItem->get($item->id, collect())->map(function ($subject) use ($measurements, $itemMilestones, $tasks, $participants, $packages, $userNames, $subcontractorNames) {
                $subject->measurements = $measurements->get($subject->id, collect())->map(fn ($measurement) => [
                    'work_type' => $measurement->work_type,
                    'execution_type' => $measurement->execution_type,
                    'quantity' => (int) $measurement->quantity,
                ])->values();
                $subject->dates = $itemMilestones->where('mginbon_item_subject_id', $subject->id)
                    ->mapWithKeys(fn ($date) => [$date->code => $date->occurred_on]);
                $subject->stages = $tasks->get($subject->id, collect())
                    ->map(function ($task) use ($participants, $packages, $userNames, $subcontractorNames) {
                        $package = $packages->get($task->id);
                        $participant = $participants->get($task->id, collect())->first();
                        $actor = $package?->user_id
                            ? ($userNames[$package->user_id] ?? '担当者')
                            : ($package?->subcontractor_id
                                ? ($subcontractorNames[$package->subcontractor_id] ?? '外注先')
                                : $participant?->legacy_value);

                        return [
                            'stage_id' => $task->stage_id,
                            'code' => $task->code,
                            'name' => $task->name,
                            'status' => $task->status,
                            'actor' => $actor,
                            'target' => $package?->user_id ? 'user:'.$package->user_id
                                : ($package?->subcontractor_id ? 'subcontractor:'.$package->subcontractor_id : null),
                            'assignment_id' => $package?->project_job_assignment_id,
                            'assigned_at' => $package?->assigned_at,
                            'completed_at' => $package?->completed_at,
                            'planned' => $package?->status === 'planned',
                            'resolution_status' => $participant?->resolution_status,
                        ];
                    })->values();

                return $subject;
            })->values();

            return $item;
        });
    }
}
