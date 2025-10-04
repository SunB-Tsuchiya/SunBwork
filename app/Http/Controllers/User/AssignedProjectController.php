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
            ->map(function ($ptm) use ($user) {
                $job = $ptm->projectJob;
                // find any assignment for this user on this job (if project_job_assignments exists)
                $assignment = null;
                try {
                    // eager-load statusModel so we can include canonical status in the payload
                    $assignment = \App\Models\ProjectJobAssignment::with(['statusModel'])
                        ->where('project_job_id', $job->id)
                        ->where('user_id', $user->id)
                        ->first();
                } catch (\Throwable $e) {
                    // ignore if table doesn't exist or other issues
                }
                return [
                    'id' => $job->id,
                    'jobcode' => $job->jobcode,
                    'name' => $job->name,
                    'client_name' => $job->client ? $job->client->name : '-',
                    'assignment' => $assignment ? [
                        'id' => $assignment->id,
                        'scheduled' => isset($assignment->scheduled) ? (bool)$assignment->scheduled : null,
                        'scheduled_at' => $assignment->scheduled_at ?? null,
                        'status' => $assignment->statusModel ? [
                            'id' => $assignment->statusModel->id,
                            'key' => $assignment->statusModel->key ?? $assignment->statusModel->slug ?? null,
                            'name' => $assignment->statusModel->name,
                        ] : null,
                    ] : null,
                ];
            });
        // Use project_job_assignments for the user's assigned jobs
        $assignments = [];
        try {
            $assignments = \App\Models\ProjectJobAssignment::with(['projectJob.client', 'statusModel'])
                ->where('user_id', $user->id)
                ->get()
                ->map(function ($a) {
                    $pj = $a->projectJob;
                    return [
                        // id here is the assignment id (used when creating an event)
                        'id' => $a->id,
                        'project_job_id' => $a->project_job_id,
                        'jobcode' => $pj ? $pj->jobcode : null,
                        'name' => $a->title ?: ($pj ? $pj->name : null),
                        'client_name' => $pj && $pj->client ? $pj->client->name : '-',
                        // details: prefer assignment.detail, fallback to project job detail
                        'details' => isset($a->detail) ? (is_string($a->detail) ? $a->detail : json_encode($a->detail)) : (isset($pj->detail) ? (is_string($pj->detail) ? $pj->detail : json_encode($pj->detail)) : null),
                        // difficulty: prefer assignment difficulty, then project job
                        'difficulty' => $a->difficulty ?? $pj->difficulty ?? null,
                        // desired start/end/time: format Carbon to Y-m-d where possible
                        'desired_start_date' => isset($a->desired_start_date) ? (method_exists($a->desired_start_date, 'format') ? $a->desired_start_date->format('Y-m-d') : (string) $a->desired_start_date) : null,
                        'desired_end_date' => isset($a->desired_end_date) ? (method_exists($a->desired_end_date, 'format') ? $a->desired_end_date->format('Y-m-d') : (string) $a->desired_end_date) : null,
                        'desired_time' => $a->desired_time ?? null,
                        'scheduled' => isset($a->scheduled) ? (bool)$a->scheduled : false,
                        'scheduled_at' => $a->scheduled_at ?? null,
                        'status' => $a->statusModel ? [
                            'id' => $a->statusModel->id,
                            'key' => $a->statusModel->key ?? $a->statusModel->slug ?? null,
                            'name' => $a->statusModel->name,
                        ] : null,
                        'preferred_date' => $a->desired_start_date ?? null,
                    ];
                })->toArray();
        } catch (\Throwable $e) {
            // fallback: if assignments table missing or error, keep empty
            $assignments = [];
        }

        $jobs = collect($assignments);
        return Inertia::render('Calendar/Index', [
            'jobs' => $jobs,
        ]);
    }

    public function show($id)
    {
        // First try to find as a ProjectJobAssignment (assignment id)
        $assignment = \App\Models\ProjectJobAssignment::with(['projectJob.client', 'user', 'statusModel'])->find($id);
        if ($assignment) {
            $pj = $assignment->projectJob;
            $pj->detail = is_string($pj->detail) ? json_decode($pj->detail, true) : $pj->detail;
            // attach canonical status object for frontend convenience
            if ($assignment->relationLoaded('statusModel') && $assignment->statusModel) {
                $assignment->status = [
                    'id' => $assignment->statusModel->id,
                    'key' => $assignment->statusModel->key ?? $assignment->statusModel->slug ?? null,
                    'name' => $assignment->statusModel->name,
                ];
            }
            return Inertia::render('User/AssignedProject/ShowAssignment', [
                'assignment' => $assignment,
                'projectJob' => $pj,
            ]);
        }

        // Fallback: treat id as project_job id and render project show
        $job = \App\Models\ProjectJob::with(['client', 'schedules'])->findOrFail($id);
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

        $memos = \App\Models\ProjectMemo::where('project_id', $id)->with('user:id,name')->get();
        $memos->transform(function ($m) {
            $m->author = $m->user ? ['id' => $m->user->id, 'name' => $m->user->name] : null;
            return $m;
        });

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
