<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\MGinbon\MGinbonProject;
use App\Models\Subcontractor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MGinbonReportController extends Controller
{
    public function textInput(Request $request): Response
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'items' => ['required', 'string', 'max:2000'],
        ]);
        $itemIds = collect(explode(',', $validated['items']))
            ->filter(fn ($id) => ctype_digit($id))->map(fn ($id) => (int) $id)->unique()->take(200)->values();
        if ($itemIds->isEmpty()) {
            throw ValidationException::withMessages(['items' => '印刷する媒体を選択してください。']);
        }

        $db = DB::connection('mginbon');
        $project = MGinbonProject::query()->where('year', $validated['year'])->firstOrFail();
        $items = $db->table('mginbon_items as items')
            ->join('mginbon_production_units as units', 'units.id', '=', 'items.mginbon_production_unit_id')
            ->join('mginbon_media_types as media', 'media.id', '=', 'items.mginbon_media_type_id')
            ->where('units.mginbon_project_id', $project->id)->whereIn('items.id', $itemIds)
            ->get(['items.id', 'items.note', 'units.mikuni_code', 'units.n_code', 'units.display_name',
                'units.n_category', 'units.school_category', 'media.name as media_name'])->keyBy('id');
        abort_unless($items->count() === $itemIds->count(), 422, '印刷対象に不正な媒体が含まれています。');

        $subjects = $db->table('mginbon_item_subjects as item_subjects')
            ->join('mginbon_subjects as subjects', 'subjects.id', '=', 'item_subjects.mginbon_subject_id')
            ->whereIn('item_subjects.mginbon_item_id', $itemIds)->orderBy('subjects.sort_order')
            ->get(['item_subjects.id', 'item_subjects.mginbon_item_id', 'subjects.name']);
        $subjectIds = $subjects->pluck('id');
        $dates = $db->table('mginbon_milestones')->whereIn('mginbon_item_subject_id', $subjectIds)
            ->whereIn('code', ['manuscript_received_on', 'text_input_completed_on'])
            ->get()->groupBy('mginbon_item_subject_id');
        $tasks = $db->table('mginbon_stage_tasks as tasks')
            ->join('mginbon_stage_definitions as stages', 'stages.id', '=', 'tasks.mginbon_stage_definition_id')
            ->whereIn('tasks.mginbon_item_subject_id', $subjectIds)->where('stages.code', 'text_input')
            ->get(['tasks.id', 'tasks.mginbon_item_subject_id']);
        $packages = $db->table('mginbon_work_package_tasks as links')
            ->join('mginbon_work_packages as packages', 'packages.id', '=', 'links.mginbon_work_package_id')
            ->whereIn('links.mginbon_stage_task_id', $tasks->pluck('id'))
            ->whereIn('packages.status', ['planned', 'assigned', 'in_progress', 'completed'])
            ->orderByDesc('packages.id')->get(['links.mginbon_stage_task_id', 'packages.user_id', 'packages.subcontractor_id'])
            ->groupBy('mginbon_stage_task_id')->map->first();
        $users = User::withGhosts()->whereIn('id', $packages->pluck('user_id')->filter())->pluck('name', 'id');
        $vendors = Subcontractor::query()->whereIn('id', $packages->pluck('subcontractor_id')->filter())->pluck('name', 'id');
        $taskBySubject = $tasks->keyBy('mginbon_item_subject_id');
        $subjectsByItem = $subjects->groupBy('mginbon_item_id');

        $rows = $itemIds->map(function (int $itemId) use ($items, $subjectsByItem, $dates, $taskBySubject, $packages, $users, $vendors) {
            $item = $items[$itemId];
            return [
                'id' => $item->id, 'mikuni_code' => $item->mikuni_code, 'n_code' => $item->n_code,
                'school_name' => $item->display_name, 'classification' => $item->n_category ?: $item->school_category,
                'media_name' => $item->media_name, 'note' => $item->note,
                'subjects' => $subjectsByItem->get($itemId, collect())->map(function ($subject) use ($dates, $taskBySubject, $packages, $users, $vendors) {
                    $subjectDates = $dates->get($subject->id, collect())->keyBy('code');
                    $task = $taskBySubject->get($subject->id);
                    $package = $task ? $packages->get($task->id) : null;
                    return [
                        'name' => $subject->name,
                        'ordered_on' => $subjectDates->get('manuscript_received_on')?->occurred_on,
                        'delivered_on' => $subjectDates->get('text_input_completed_on')?->occurred_on,
                        'actor' => $package?->user_id ? ($users[$package->user_id] ?? '')
                            : ($package?->subcontractor_id ? ($vendors[$package->subcontractor_id] ?? '') : ''),
                    ];
                })->values(),
            ];
        });

        return Inertia::render('Coordinator/MGinbon/Reports/TextInput', [
            'project' => ['year' => $project->year, 'name' => $project->name],
            'rows' => $rows,
            'printedAt' => now()->format('Y/m/d H:i'),
        ]);
    }
}
