<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Assignment;

class AssignedProjectController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        // project_team_membersからproject_job_idを取得し、project_jobs, clientsをjoin
        $jobs = \App\Models\ProjectTeamMember::with(['projectJob.client'])
            ->where('user_id', $user->id)
            ->get()
            ->map(function($ptm) {
                $job = $ptm->projectJob;
                return [
                    'id' => $job->id,
                    'jobcode' => $job->jobcode,
                    'name' => $job->name,
                    'client_name' => $job->client ? $job->client->name : '-',
                ];
            });
        return Inertia::render('User/AssignedProject/Index', [
            'jobs' => $jobs,
        ]);
    }

    public function show($id)
    {
        // project_jobs, client, メンバーを取得
        $job = \App\Models\ProjectJob::with('client')->findOrFail($id);
        // detailsはJSONデコード
        $job->detail = is_string($job->detail) ? json_decode($job->detail, true) : $job->detail;

        // チームメンバー取得
        $members = \App\Models\ProjectTeamMember::with(['user.department', 'user.assignment'])
            ->where('project_job_id', $id)
            ->get()
            ->map(function($ptm) {
                $user = $ptm->user;
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'department' => $user->department ? $user->department->name : '-',
                    'assignment' => $user->assignment ? $user->assignment->name : '-',
                ];
            });

        return Inertia::render('User/AssignedProject/Show', [
            'job' => $job,
            'members' => $members,
        ]);
    }
}
