<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\ProjectJob;
use App\Models\ProjectScheduleWeekPost;
use Illuminate\Http\Request;

class ProjectScheduleWeekPostController extends Controller
{
    public function index(Request $request, ProjectJob $projectJob)
    {
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
