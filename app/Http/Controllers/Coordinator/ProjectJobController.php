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
        // フラッシュデータからjobid/register_flagsを取得
        $jobid = session('jobid');
        $registerFlags = session('register_flags', []);
        return Inertia::render('Coordinator/ProjectJobs/Index', [
            'jobs' => $jobs,
            'jobid' => $jobid,
            'registerFlags' => $registerFlags,
        ]);
    }

    public function create()
    {
        return Inertia::render('Coordinator/ProjectJobs/Create');
    }

    public function store(Request $request)
    {


        try {
            $data = $request->validate([
                'jobcode' => ['required', 'string', 'max:255', 'regex:/^[0-9\-]+$/'],
                'name' => 'required|string|max:255',
                'user_id' => 'required|exists:users,id',
                'client_id' => 'required|exists:clients,id',
                'detail' => 'nullable|string',
            ]);
            // detailはJSON形式で保存
            if (isset($data['detail']) && is_string($data['detail'])) {
                $data['detail'] = json_encode(['text' => $data['detail']]);
            }
            $job = ProjectJob::create($data);
            // schedule未設定フラグのみ
            $registerFlags = [];
            if (is_null($job->schedule)) {
                $registerFlags[] = 'schedule';
            }
            return redirect()->route('coordinator.project_jobs.index')
                ->with('jobid', $job->id)
                ->with('register_flags', $registerFlags);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        }
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
        try {
            $data = $request->validate([
                'jobcode' => ['required', 'string', 'max:255', 'regex:/^[0-9\-]+$/'],
                'name' => 'required|string|max:255',
                'user_id' => 'required|exists:users,id',
                'client_id' => 'required|exists:clients,id',
                'detail' => 'nullable|string',
                'schedule' => 'nullable|array',
            ]);
            // detailはJSON形式で保存
            if (isset($data['detail']) && is_string($data['detail'])) {
                $data['detail'] = json_encode(['text' => $data['detail']]);
            }
            $projectJob->update($data);
            return redirect()->route('coordinator.project_jobs.index');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        }
    }

    public function destroy(ProjectJob $projectJob)
    {
    $projectJob->delete();
    // Inertiaリダイレクト時にフロントでリロードを促すため、フラッシュメッセージを渡す
    return redirect()->route('coordinator.project_jobs.index')->with('reload', true);
    }
}
