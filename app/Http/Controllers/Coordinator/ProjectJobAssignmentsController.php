<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ProjectJob;
use App\Models\ProjectJobAssignment;

class ProjectJobAssignmentsController extends Controller
{
    public function index(ProjectJob $projectJob)
    {
        $assignments = $projectJob->projectJobAssignments()->with('user')->orderBy('desired_end_date', 'asc')->get();
        return Inertia::render('Coordinator/ProjectJobs/JobAssign/Index', [
            'projectJob' => $projectJob,
            'assignments' => $assignments,
        ]);
    }

    public function create(ProjectJob $projectJob)
    {
        // send available team members for selection
        $members = $projectJob->teamMembers()->with('user')->get()->map(function ($m) {
            return ['id' => $m->user?->id, 'name' => $m->user?->name];
        })->filter(function ($item) {
            return $item['id'] !== null;
        })->values();

        return Inertia::render('Coordinator/ProjectJobs/JobAssign/Edit', [
            'projectJob' => $projectJob,
            'members' => $members,
            'assignments' => [],
            'editMode' => false,
        ]);
    }

    public function edit(ProjectJob $projectJob, ProjectJobAssignment $assignment)
    {
        $members = $projectJob->teamMembers()->with('user')->get()->map(function ($m) {
            return ['id' => $m->user?->id, 'name' => $m->user?->name];
        })->filter(function ($item) {
            return $item['id'] !== null;
        })->values();

        return Inertia::render('Coordinator/ProjectJobs/JobAssign/Edit', [
            'projectJob' => $projectJob,
            'members' => $members,
            'assignments' => [$assignment],
            'editMode' => true,
        ]);
    }

    public function update(Request $request, ProjectJob $projectJob, ProjectJobAssignment $assignment)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'detail' => 'nullable|string',
            'difficulty' => 'required|in:light,normal,heavy',
            'desired_start_date' => 'nullable|date',
            'desired_end_date' => 'nullable|date',
            'desired_time' => 'nullable|date_format:H:i',
            'user_id' => 'nullable|exists:users,id',
        ]);

        // server-side logical validations
        if (!empty($data['desired_start_date']) && !empty($data['desired_end_date'])) {
            if ($data['desired_end_date'] < $data['desired_start_date']) {
                return back()->withErrors(['desired_end_date' => '終了希望日は割当希望日より前にできません。'])->withInput();
            }
        }
        // if end date is today, desired_time must be >= now
        if (!empty($data['desired_end_date']) && !empty($data['desired_time'])) {
            $today = date('Y-m-d');
            if ($data['desired_end_date'] === $today) {
                $now = date('H:i');
                if ($data['desired_time'] < $now) {
                    return back()->withErrors(['desired_time' => '当日の時間は現在時刻以降を指定してください。'])->withInput();
                }
            }
        }

        $assignment->update([
            'user_id' => $data['user_id'] ?? null,
            'title' => $data['title'],
            'detail' => $data['detail'] ?? null,
            'difficulty' => $data['difficulty'],
            'desired_start_date' => $data['desired_start_date'] ?? null,
            'desired_end_date' => $data['desired_end_date'] ?? null,
            'desired_time' => $data['desired_time'] ?? null,
        ]);

        return redirect()->route('coordinator.project_jobs.assignments.index', ['projectJob' => $projectJob->id]);
    }

    public function store(Request $request, ProjectJob $projectJob)
    {
        $data = $request->validate([
            'assignments' => 'required|array',
            'assignments.*.title' => 'required|string|max:255',
            'assignments.*.detail' => 'nullable|string',
            'assignments.*.difficulty' => 'required|in:light,normal,heavy',
            'assignments.*.desired_start_date' => 'nullable|date',
            'assignments.*.desired_end_date' => 'nullable|date',
            'assignments.*.desired_time' => 'nullable|date_format:H:i',
            'assignments.*.user_id' => 'nullable|exists:users,id',
        ]);

        foreach ($data['assignments'] as $a) {
            // logical validations per assignment
            if (!empty($a['desired_start_date']) && !empty($a['desired_end_date'])) {
                if ($a['desired_end_date'] < $a['desired_start_date']) {
                    return back()->withErrors(['assignments' => '終了希望日は割当希望日より前にできません。'])->withInput();
                }
            }
            if (!empty($a['desired_end_date']) && !empty($a['desired_time'])) {
                $today = date('Y-m-d');
                if ($a['desired_end_date'] === $today) {
                    $now = date('H:i');
                    if ($a['desired_time'] < $now) {
                        return back()->withErrors(['assignments' => '当日の時間は現在時刻以降を指定してください。'])->withInput();
                    }
                }
            }

            ProjectJobAssignment::create([
                'project_job_id' => $projectJob->id,
                'user_id' => $a['user_id'] ?? null,
                'title' => $a['title'],
                'detail' => $a['detail'] ?? null,
                'difficulty' => $a['difficulty'],
                'desired_start_date' => $a['desired_start_date'] ?? null,
                'desired_end_date' => $a['desired_end_date'] ?? null,
                'desired_time' => $a['desired_time'] ?? null,
            ]);
        }

        return redirect()->route('coordinator.project_jobs.show', ['projectJob' => $projectJob->id]);
    }
}
