<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ProjectJob;
use App\Models\ProjectJobAssignment;
use App\Models\ProjectScheduleWeekPost;
use App\Models\ProjectTeamMember;
use Illuminate\Http\Request;

class ProjectScheduleWeekPostController extends Controller
{
    private function checkAccess(Request $request, ProjectJob $projectJob): bool
    {
        $user = $request->user();

        return ProjectJobAssignment::where('project_job_id', $projectJob->id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhere('sender_id', $user->id);
            })->exists()
            || ProjectTeamMember::where('project_job_id', $projectJob->id)
                ->where('user_id', $user->id)->exists();
    }

    public function index(Request $request, ProjectJob $projectJob)
    {
        if (! $this->checkAccess($request, $projectJob)) {
            abort(403);
        }

        $year = (int) $request->input('year', now()->year);
        $week = (int) $request->input('week', now()->isoWeek());

        $posts = ProjectScheduleWeekPost::where('project_job_id', $projectJob->id)
            ->where('year', $year)
            ->where('week', $week)
            ->with('user:id,name,user_role')
            ->orderBy('created_at')
            ->get(['id', 'user_id', 'parent_id', 'body', 'created_at']);

        return response()->json($posts);
    }

    public function store(Request $request, ProjectJob $projectJob)
    {
        if (! $this->checkAccess($request, $projectJob)) {
            abort(403);
        }

        $validated = $request->validate([
            'year'      => 'required|integer',
            'week'      => 'required|integer|min:1|max:53',
            'body'      => 'required|string|max:2000',
            'parent_id' => 'nullable|exists:project_schedule_week_posts,id',
        ]);

        $post = ProjectScheduleWeekPost::create([
            'project_job_id' => $projectJob->id,
            'user_id'        => $request->user()->id,
            'year'           => $validated['year'],
            'week'           => $validated['week'],
            'body'           => $validated['body'],
            'parent_id'      => $validated['parent_id'] ?? null,
        ]);

        $post->load('user:id,name');

        return response()->json($post, 201);
    }
}
