<?php

namespace App\Http\Controllers\TeamRoom;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamMeetingComment;
use App\Models\TeamMeetingMinute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeamMeetingCommentController extends Controller
{
    public function store(Request $request, Team $team, TeamMeetingMinute $minute)
    {
        app(TeamRoomController::class)->assertMember($team);
        abort_unless($minute->team_id === $team->id, 404);

        $request->validate([
            'comment' => 'required|string|max:2000',
        ]);

        $user = Auth::user();
        $comment = TeamMeetingComment::create([
            'team_meeting_minute_id' => $minute->id,
            'user_id'   => $user->id,
            'user_name' => $user->name,
            'comment'   => $request->comment,
        ]);

        return back()->with('success', 'コメントを投稿しました');
    }

    public function destroy(Team $team, TeamMeetingMinute $minute, TeamMeetingComment $comment)
    {
        app(TeamRoomController::class)->assertMember($team);
        abort_unless($minute->team_id === $team->id, 404);
        abort_unless($comment->team_meeting_minute_id === $minute->id, 404);

        $user = Auth::user();
        if ($comment->user_id !== $user->id && ! $user->isSuperAdmin()) {
            abort(403);
        }

        $comment->delete();

        return back()->with('success', 'コメントを削除しました');
    }
}
