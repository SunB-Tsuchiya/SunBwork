<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\MGinbon\MGinbonProject;
use App\Models\Subcontractor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MGinbonAggregationController extends Controller
{
    public function index(Request $request): Response
    {
        $data = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'dimension' => ['nullable', Rule::in(['school', 'media', 'subject', 'stage', 'actor', 'subcontractor'])],
        ]);
        $projects = MGinbonProject::query()->orderByDesc('year')->get(['id', 'year', 'name']);
        $project = isset($data['year']) ? $projects->firstWhere('year', (int) $data['year']) : $projects->first();
        abort_unless($project, 404, 'MGinbon年度プロジェクトがありません。');
        $dimension = $data['dimension'] ?? 'school';

        return Inertia::render('Coordinator/MGinbon/Aggregations/Index', [
            'project' => $project, 'projects' => $projects, 'dimension' => $dimension,
            'rows' => $this->rows($project->id, $dimension),
        ]);
    }

    private function rows(int $projectId, string $dimension)
    {
        if ($dimension === 'actor') {
            $rows = $this->taskQuery($projectId)
                ->join('mginbon_stage_task_participants as participants', 'participants.mginbon_stage_task_id', '=', 'tasks.id')
                ->groupBy('participants.user_id', 'participants.subcontractor_id', 'participants.legacy_value')
                ->get(['participants.user_id', 'participants.subcontractor_id', 'participants.legacy_value',
                    DB::raw('COUNT(DISTINCT tasks.id) as task_count'),
                    DB::raw("COUNT(DISTINCT CASE WHEN tasks.status = 'completed' THEN tasks.id END) as completed_count"),
                    DB::raw("COUNT(DISTINCT CASE WHEN tasks.status = 'in_progress' THEN tasks.id END) as in_progress_count")]);
            $users = User::query()->whereIn('id', $rows->pluck('user_id')->filter())->pluck('name', 'id');
            $vendors = Subcontractor::query()->whereIn('id', $rows->pluck('subcontractor_id')->filter())->pluck('name', 'id');
            return $this->format($rows->map(function ($row) use ($users, $vendors) {
                $row->label = $row->user_id ? ($users[$row->user_id] ?? '担当者')
                    : ($row->subcontractor_id ? ($vendors[$row->subcontractor_id] ?? '外注先') : ($row->legacy_value ?: '未設定'));
                return $row;
            }));
        }

        if ($dimension === 'subcontractor') {
            $rows = $this->measurementQuery($projectId)->where('measurements.execution_type', 'subcontracted')
                ->groupBy('measurements.subcontractor_id')->get(['measurements.subcontractor_id',
                    DB::raw('COUNT(DISTINCT items.id) as task_count'),
                    DB::raw("SUM(CASE WHEN measurements.work_type = 'scan' THEN measurements.quantity ELSE 0 END) as scan_points"),
                    DB::raw("SUM(CASE WHEN measurements.work_type = 'drawing' THEN measurements.quantity ELSE 0 END) as drawing_points"),
                    DB::raw('SUM(measurements.quantity) as subcontracted_points')]);
            $names = Subcontractor::query()->whereIn('id', $rows->pluck('subcontractor_id')->filter())->pluck('name', 'id');
            return $this->format($rows->map(function ($row) use ($names) {
                $row->label = $row->subcontractor_id ? ($names[$row->subcontractor_id] ?? '外注先') : '旧データ・外注先未対応';
                return $row;
            }));
        }

        if ($dimension === 'media') {
            $tasks = $this->taskQuery($projectId)
                ->groupBy('items.id', 'units.mikuni_code', 'units.display_name', 'media.name')
                ->orderBy('units.mikuni_code')->orderBy('units.display_name')->orderBy('media.name')
                ->get(['items.id as group_id', 'units.mikuni_code as m_code', 'units.display_name as school_name', 'media.name as media_name',
                    DB::raw('COUNT(DISTINCT tasks.id) as task_count'),
                    DB::raw("COUNT(DISTINCT CASE WHEN tasks.status = 'completed' THEN tasks.id END) as completed_count"),
                    DB::raw("COUNT(DISTINCT CASE WHEN tasks.status = 'in_progress' THEN tasks.id END) as in_progress_count")]);
            $points = $this->measurementQuery($projectId)->groupBy('items.id')->get(['items.id as group_id',
                DB::raw("SUM(CASE WHEN measurements.work_type = 'scan' THEN measurements.quantity ELSE 0 END) as scan_points"),
                DB::raw("SUM(CASE WHEN measurements.work_type = 'drawing' THEN measurements.quantity ELSE 0 END) as drawing_points"),
                DB::raw("SUM(CASE WHEN measurements.execution_type = 'subcontracted' THEN measurements.quantity ELSE 0 END) as subcontracted_points")])->keyBy('group_id');
            return $this->format($tasks->map(function ($row) use ($points) {
                $point = $points->get($row->group_id);
                $row->label = $row->school_name.' / '.$row->media_name;
                $row->scan_points = $point?->scan_points ?? 0;
                $row->drawing_points = $point?->drawing_points ?? 0;
                $row->subcontracted_points = $point?->subcontracted_points ?? 0;
                return $row;
            }));
        }

        [$key, $label] = match ($dimension) {
            'school' => ['units.id', 'units.display_name'],
            'subject' => ['subjects.id', 'subjects.name'],
            'stage' => ['stages.id', 'stages.name'],
        };
        $taskColumns = [$key.' as group_id', $label.' as label', DB::raw('COUNT(DISTINCT tasks.id) as task_count'),
            DB::raw("COUNT(DISTINCT CASE WHEN tasks.status = 'completed' THEN tasks.id END) as completed_count"),
            DB::raw("COUNT(DISTINCT CASE WHEN tasks.status = 'in_progress' THEN tasks.id END) as in_progress_count")];
        $taskQuery = $this->taskQuery($projectId)->groupBy($key, $label)->orderBy($label);
        if ($dimension === 'school') {
            $taskQuery->groupBy('units.mikuni_code')->orderBy('units.mikuni_code');
            $taskColumns[] = 'units.mikuni_code as m_code';
        }
        if ($dimension === 'stage') {
            $taskQuery->groupBy('stages.code');
            $taskColumns[] = 'stages.code as stage_code';
        }
        $tasks = $taskQuery->get($taskColumns);
        if ($dimension === 'stage') {
            $stageOrder = [
                'text_input', 'drawing', 'initial_operation', 'initial_check', 'initial_text_proof',
                'reproof_scan_check', 'reproof_text_proof', 'reproof_operation', 'client_return_operation',
                'third_proof', 'third_operation', 'fourth_operation', 'fourth_proof', 'fifth_operation',
            ];
            return $this->format($tasks->map(function ($row) use ($stageOrder) {
                $index = array_search($row->stage_code, $stageOrder, true);
                $row->stage_number = $index === false ? 999 : $index + 1;
                return $row;
            }));
        }

        $points = $this->measurementQuery($projectId)->groupBy($key)->get([$key.' as group_id',
            DB::raw("SUM(CASE WHEN measurements.work_type = 'scan' THEN measurements.quantity ELSE 0 END) as scan_points"),
            DB::raw("SUM(CASE WHEN measurements.work_type = 'drawing' THEN measurements.quantity ELSE 0 END) as drawing_points"),
            DB::raw("SUM(CASE WHEN measurements.execution_type = 'subcontracted' THEN measurements.quantity ELSE 0 END) as subcontracted_points")])->keyBy('group_id');
        return $this->format($tasks->map(function ($row) use ($points) {
            $point = $points->get($row->group_id);
            $row->scan_points = $point?->scan_points ?? 0;
            $row->drawing_points = $point?->drawing_points ?? 0;
            $row->subcontracted_points = $point?->subcontracted_points ?? 0;
            return $row;
        }));
    }

    private function taskQuery(int $projectId)
    {
        return DB::connection('mginbon')->table('mginbon_stage_tasks as tasks')
            ->join('mginbon_items as items', 'items.id', '=', 'tasks.mginbon_item_id')
            ->join('mginbon_production_units as units', 'units.id', '=', 'items.mginbon_production_unit_id')
            ->join('mginbon_media_types as media', 'media.id', '=', 'items.mginbon_media_type_id')
            ->leftJoin('mginbon_item_subjects as item_subjects', 'item_subjects.id', '=', 'tasks.mginbon_item_subject_id')
            ->leftJoin('mginbon_subjects as subjects', 'subjects.id', '=', 'item_subjects.mginbon_subject_id')
            ->join('mginbon_stage_definitions as stages', 'stages.id', '=', 'tasks.mginbon_stage_definition_id')
            ->where('units.mginbon_project_id', $projectId);
    }

    private function measurementQuery(int $projectId)
    {
        return DB::connection('mginbon')->table('mginbon_work_measurements as measurements')
            ->join('mginbon_item_subjects as item_subjects', 'item_subjects.id', '=', 'measurements.mginbon_item_subject_id')
            ->join('mginbon_subjects as subjects', 'subjects.id', '=', 'item_subjects.mginbon_subject_id')
            ->join('mginbon_items as items', 'items.id', '=', 'item_subjects.mginbon_item_id')
            ->join('mginbon_production_units as units', 'units.id', '=', 'items.mginbon_production_unit_id')
            ->join('mginbon_media_types as media', 'media.id', '=', 'items.mginbon_media_type_id')
            ->where('units.mginbon_project_id', $projectId);
    }

    private function format($rows)
    {
        return $rows->sortBy('label')->values()->map(fn ($row) => collect((array) $row)->map(
            fn ($value, $key) => str_ends_with($key, '_count') || str_ends_with($key, '_points') ? (int) $value : $value
        ));
    }
}
