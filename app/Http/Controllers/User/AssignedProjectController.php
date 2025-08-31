<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Assignment;
use Illuminate\Support\Facades\Log;

class AssignedProjectController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        // project_team_membersからproject_job_idを取得し、project_jobs, clientsをjoin
        $jobs = \App\Models\ProjectTeamMember::with(['projectJob.client'])
            ->where('user_id', $user->id)
            ->get()
            ->map(function ($ptm) {
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
        // eager-load client and schedules so frontend can render calendar without extra requests
        $job = \App\Models\ProjectJob::with(['client', 'schedules'])->findOrFail($id);
        // detailsはJSONデコード
        $job->detail = is_string($job->detail) ? json_decode($job->detail, true) : $job->detail;

        // チームメンバー取得
        $members = \App\Models\ProjectTeamMember::with(['user.department', 'user.assignment'])
            ->where('project_job_id', $id)
            ->get()
            ->map(function ($ptm) {
                $user = $ptm->user;
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'department' => $user->department ? $user->department->name : '-',
                    'assignment' => $user->assignment ? $user->assignment->name : '-',
                ];
            });

        // load project memos (with author info) so assigned users can see them on calendar
        $memos = \App\Models\ProjectMemo::where('project_id', $id)->with('user:id,name')->get();
        $memos->transform(function ($m) {
            $m->author = $m->user ? ['id' => $m->user->id, 'name' => $m->user->name] : null;
            return $m;
        });

        // debug: log memos count for this view
        try {
            Log::info('AssignedProjectController@show memos', ['project_id' => $id, 'count' => $memos->count()]);
        } catch (\Throwable $e) {
        }

        return Inertia::render('User/AssignedProject/Show', [
            'job' => $job,
            'members' => $members,
            'memos' => $memos,
        ]);
    }
}
