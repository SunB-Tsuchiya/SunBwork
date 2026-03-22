<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\ProjectSchedule;

class ProjectSchedulesCalendarController extends Controller
{
    // Render the Inertia calendar page for project schedules (PoC)
    public function index(Request $request)
    {
        $user = Auth::user();
        $schedules = [];
        if ($user) {
            // For PoC return schedules where the user is assigned or all if coordinator
            // Avoid selecting a fragile subset of columns (some environments may differ)
            // and normalize the result to a plain array that always includes project_job_id.
            $query = ProjectSchedule::query();
            // If a project_id filter is provided, apply it
            if ($request->filled('project_job_id')) {
                $query->where('project_job_id', $request->input('project_job_id'));
            }
            // Load full models and map to a stable plain-array shape so the frontend
            // always receives project_job_id explicitly.
            $schedules = $query->with('comments')->get()->map(function ($s) {
                return [
                    'id' => $s->id,
                    'name' => $s->name ?? null,
                    'description' => $s->description ?? null,
                    'start_date' => $s->start_date ?? null,
                    'end_date' => $s->end_date ?? null,
                    'progress' => $s->progress ?? null,
                    'project_job_id' => $s->project_job_id ?? null,
                    'color' => $s->color ?? null,
                    // include comments inline to keep the frontend shape similar to before
                    'comments' => $s->comments ? $s->comments->map(function ($c) {
                        return [
                            'id' => $c->id,
                            'project_schedule_id' => $c->project_schedule_id,
                            'body' => $c->body,
                            'date' => $c->date ? $c->date->toDateString() : null,
                            'user_id' => $c->user_id,
                        ];
                    })->toArray() : [],
                ];
            })->values();
        }

        // If a project_job_id was provided, also resolve the ProjectJob and its client
        $project = null;
        $client = null;
        if ($request->filled('project_job_id')) {
            $pj = \App\Models\ProjectJob::with('client')->find($request->input('project_job_id'));
            if ($pj) {
                $project = [
                    'id' => $pj->id,
                    'name' => $pj->title,
                    'jobcode' => $pj->jobcode ?? null,
                ];
                if ($pj->client) {
                    $client = [
                        'id' => $pj->client->id,
                        'name' => $pj->client->name,
                    ];
                }
            }
        }

        // Collect schedule comments separately for quicker JSON serialization in the frontend
        $comments = [];
        foreach ($schedules as $s) {
            foreach ($s->comments ?? [] as $c) {
                $comments[] = [
                    'id' => $c->id,
                    'project_schedule_id' => $c->project_schedule_id,
                    'body' => $c->body,
                    'date' => $c->date ? $c->date->toDateString() : null,
                    'user_id' => $c->user_id,
                ];
            }
        }

        // Load project-level memos (project_memos).
        // Always include global memos (project_id NULL). If a project_job_id is provided,
        // also include memos for that project.
        $projectMemos = [];
        $projectId = $request->input('project_job_id', null);
        $memosQuery = \App\Models\ProjectMemo::query();
        if ($projectId) {
            $memosQuery->where(function ($q) use ($projectId) {
                $q->where('project_id', $projectId)->orWhereNull('project_id');
            });
        } else {
            // no project specified: only global memos
            $memosQuery->whereNull('project_id');
        }
        $memos = $memosQuery->get();
        foreach ($memos as $m) {
            $projectMemos[] = [
                'id' => $m->id,
                'project_id' => $m->project_id,
                'body' => $m->body,
                'date' => $m->date ? $m->date->toDateString() : null,
                'user_id' => $m->user_id,
            ];
        }

        return Inertia::render('Coordinator/ProjectSchedules/Calendar', [
            'schedules' => $schedules,
            'project' => $project,
            'client' => $client,
            'comments' => $comments,
            'memos' => $projectMemos,
        ]);
    }

    // API-ish endpoint to update a schedule's dates/progress (PoC)
    // API-ish endpoint to update a schedule's dates/progress (PoC)
    public function update(Request $request, $project_schedule)
    {
        // Resolve the ProjectSchedule by id explicitly to avoid environment-specific
        // implicit binding anomalies seen in tests. This is a deliberate, minimal
        // change that does not reintroduce complex defensive heuristics.
        $project_schedule = ProjectSchedule::findOrFail($project_schedule);

        $this->authorize('update', $project_schedule);

        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:50',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'progress' => 'nullable|integer|min:0|max:100',
        ]);

        if (array_key_exists('name', $data) && $data['name'] !== null) $project_schedule->name = $data['name'];
        if (array_key_exists('description', $data)) $project_schedule->description = $data['description'];
        if (array_key_exists('color', $data)) $project_schedule->color = $data['color'];
        if (array_key_exists('start_date', $data) && $data['start_date'] !== null) $project_schedule->start_date = $data['start_date'];
        if (array_key_exists('end_date', $data) && $data['end_date'] !== null) $project_schedule->end_date = $data['end_date'];
        if (array_key_exists('progress', $data)) $project_schedule->progress = $data['progress'];

        $project_schedule->save();
        // Ensure we return the latest state (including casts / defaults) after save
        $project_schedule->refresh();

        return response()->json(['ok' => true, 'schedule' => $project_schedule]);
    }
}
