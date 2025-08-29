<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\ProjectScheduleComment;
use App\Models\ProjectSchedule;

class ProjectScheduleCommentsController extends Controller
{
    public function create(Request $request, $project_schedule)
    {
        $schedule = ProjectSchedule::findOrFail($project_schedule);
        $this->authorize('update', $schedule);
        return Inertia::render('Coordinator/ProjectSchedules/Comments/Create', [
            'project_schedule' => [
                'id' => $schedule->id,
                'name' => $schedule->name,
            ],
        ]);
    }

    public function store(Request $request, $project_schedule)
    {
        $schedule = ProjectSchedule::findOrFail($project_schedule);
        $this->authorize('update', $schedule);

        $data = $request->validate([
            'body' => ['required', 'string'],
            'date' => ['nullable', 'date'],
        ]);

        $comment = ProjectScheduleComment::create([
            'project_schedule_id' => $schedule->id,
            'user_id' => Auth::id(),
            'body' => $data['body'],
            'date' => $data['date'] ?? null,
        ]);

        return redirect()->route('coordinator.project_schedules.show', ['project_schedule' => $schedule->id]);
    }

    public function show($id)
    {
        $comment = ProjectScheduleComment::findOrFail($id);
        $this->authorize('view', $comment);
        return Inertia::render('Coordinator/ProjectSchedules/Comments/Show', [
            'comment' => $comment,
        ]);
    }

    public function update(Request $request, $id)
    {
        $comment = ProjectScheduleComment::findOrFail($id);
        $this->authorize('update', $comment);

        $data = $request->validate([
            'body' => ['required', 'string'],
            'metadata.date' => ['nullable', 'date'],
        ]);

        $comment->body = $data['body'];
        if (isset($data['metadata']['date'])) {
            $comment->date = $data['metadata']['date'];
        }
        $comment->save();

        return response()->json(['status' => 'ok', 'comment' => $comment]);
    }
}
