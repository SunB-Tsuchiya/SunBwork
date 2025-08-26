<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ProjectSchedule;
use Illuminate\Support\Facades\DB;

class ProjectSchedulesController extends Controller
{
    public function index(Request $request)
    {
        // for PoC, accept project_job_id query
        $projectJobId = $request->query('project_job_id');
        $schedules = ProjectSchedule::where('project_job_id', $projectJobId)->orderBy('order')->get();
        return Inertia::render('Coordinator/ProjectSchedules/Index', [
            'project_job_id' => $projectJobId,
            'schedules' => $schedules,
        ]);
    }

    public function create(Request $request)
    {
        $projectJobId = $request->query('project_job_id');
        return Inertia::render('Coordinator/ProjectSchedules/Create', [
            'project_job_id' => $projectJobId,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_job_id' => 'required|exists:project_jobs,id',
            'name' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);
        $schedule = ProjectSchedule::create($data + ['created_by' => $request->user()->id]);
        return redirect()->route('coordinator.project_schedules.index', ['project_job_id' => $data['project_job_id']]);
    }

    // Update a single schedule (PATCH)
    public function update(Request $request, ProjectSchedule $projectSchedule)
    {
        $this->authorize('update', $projectSchedule);
        $data = $request->validate([
            'start' => 'nullable|date',
            'end' => 'nullable|date',
            'progress' => 'nullable|numeric|min:0|max:100',
        ]);
        $projectSchedule->fill([
            'start_date' => $data['start'] ?? $projectSchedule->start_date,
            'end_date' => $data['end'] ?? $projectSchedule->end_date,
            'progress' => $data['progress'] ?? $projectSchedule->progress,
            'updated_by' => $request->user()->id,
        ]);
        $projectSchedule->save();
        return response()->json(['status' => 'ok', 'schedule' => $projectSchedule]);
    }

    // Bulk update schedules (e.g., multiple drag changes)
    public function bulkUpdate(Request $request)
    {
        $payload = $request->validate([
            'updates' => 'required|array',
            'updates.*.id' => 'required|integer|exists:project_schedules,id',
            'updates.*.start' => 'nullable|date',
            'updates.*.end' => 'nullable|date',
            'updates.*.progress' => 'nullable|numeric|min:0|max:100',
        ]);

        DB::transaction(function () use ($payload, $request) {
            foreach ($payload['updates'] as $u) {
                $s = ProjectSchedule::find($u['id']);
                if (!$s) continue;
                // optionally authorize per-schedule
                // $this->authorize('update', $s);
                $s->forceFill([
                    'start_date' => $u['start'] ?? $s->start_date,
                    'end_date' => $u['end'] ?? $s->end_date,
                    'progress' => $u['progress'] ?? $s->progress,
                    'updated_by' => $request->user()->id,
                ])->save();
            }
        });

        return response()->json(['status' => 'ok']);
    }
}
