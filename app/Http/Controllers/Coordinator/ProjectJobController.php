<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProjectJob;
use Inertia\Inertia;

class ProjectJobController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $jobs = ProjectJob::with('client')
            ->where('user_id', $user->id)
            ->get();
    return Inertia::render('Coordinator/ProjectJobs/Index', ['jobs' => $jobs]);
    }

    public function create()
    {
        return Inertia::render('Coordinator/ProjectJobs/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'jobcode' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'coordinator_id' => 'required|exists:coordinators,id',
            'detail' => 'nullable|array',
            'teammember' => 'nullable|array',
            'schedule' => 'nullable|array',
        ]);
        $job = ProjectJob::create($data);
        return redirect()->route('coordinator.project_jobs.index');
    }

    public function show(ProjectJob $projectJob)
    {
        return Inertia::render('Coordinator/ProjectJobs/Show', ['job' => $projectJob]);
    }

    public function edit(ProjectJob $projectJob)
    {
        return Inertia::render('Coordinator/ProjectJobs/Edit', ['job' => $projectJob]);
    }

    public function update(Request $request, ProjectJob $projectJob)
    {
        $data = $request->validate([
            'jobcode' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'coordinator_id' => 'required|exists:coordinators,id',
            'detail' => 'nullable|array',
            'teammember' => 'nullable|array',
            'schedule' => 'nullable|array',
        ]);
        $projectJob->update($data);
        return redirect()->route('coordinator.project_jobs.index');
    }

    public function destroy(ProjectJob $projectJob)
    {
        $projectJob->delete();
        return redirect()->route('coordinator.project_jobs.index');
    }
}
