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

        // 日付は Create.vue が 'date'、カレンダー側が 'metadata.date' で送ってくる
        $data = $request->validate([
            'body'          => ['required', 'string'],
            'date'          => ['nullable', 'date'],
            'metadata.date' => ['nullable', 'date'],
        ]);

        $comment = ProjectScheduleComment::create([
            'project_schedule_id' => $schedule->id,
            'user_id' => Auth::id(),
            'body' => $data['body'],
            'date' => $data['date'] ?? ($data['metadata']['date'] ?? null),
        ]);

        // カレンダーからは axios で呼ばれ、作成結果をその場で描画するため JSON を返す
        if ($request->wantsJson()) {
            return response()->json(['status' => 'ok', 'comment' => $comment]);
        }

        // 旧実装は存在しない coordinator.project_schedules.show へリダイレクトしており
        // RouteNotFoundException になっていた（show ルートは定義されていない）
        return redirect()->route('coordinator.project_schedules.index');
    }

    public function show($id)
    {
        $comment = ProjectScheduleComment::findOrFail($id);
        // ProjectScheduleComment 用の Policy は存在しないため、store() と同じく
        // 親スケジュールの権限で判定する（旧実装は常に 403 になっていた）
        abort_if(! $comment->schedule, 404);
        $this->authorize('update', $comment->schedule);
        return Inertia::render('Coordinator/ProjectSchedules/Comments/Show', [
            'comment' => $comment,
        ]);
    }

    public function update(Request $request, $id)
    {
        $comment = ProjectScheduleComment::findOrFail($id);
        // 同上（親スケジュールの権限で判定）
        abort_if(! $comment->schedule, 404);
        $this->authorize('update', $comment->schedule);

        $data = $request->validate([
            'body'          => ['required', 'string'],
            'date'          => ['nullable', 'date'],
            'metadata.date' => ['nullable', 'date'],
        ]);

        $comment->body = $data['body'];
        $newDate = $data['date'] ?? ($data['metadata']['date'] ?? null);
        if ($newDate !== null) {
            $comment->date = $newDate;
        }
        $comment->save();

        return response()->json(['status' => 'ok', 'comment' => $comment]);
    }
}
