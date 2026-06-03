<?php

namespace App\Http\Controllers\TeamRoom;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamWeekPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeamWeekPostController extends Controller
{
    public function index(Request $request, Team $team)
    {
        app(TeamRoomController::class)->assertMember($team);

        $request->validate([
            'year' => 'required|integer',
            'week' => 'required|integer|min:1|max:53',
        ]);

        $posts = TeamWeekPost::where('team_id', $team->id)
            ->where('year', $request->year)
            ->where('week', $request->week)
            ->with('user:id,name,user_role')
            ->orderBy('created_at')
            ->get();

        return response()->json($posts->map(fn($p) => [
            'id'         => $p->id,
            'body'       => $p->body,
            'parent_id'  => $p->parent_id,
            'user_id'    => $p->user_id,
            'user_name'  => $p->user?->name,
            'user_role'  => $p->user?->user_role,
            'created_at' => $p->created_at?->format('Y-m-d H:i'),
        ]));
    }

    public function store(Request $request, Team $team)
    {
        app(TeamRoomController::class)->assertMember($team);

        $validated = $request->validate([
            'year'      => 'required|integer',
            'week'      => 'required|integer|min:1|max:53',
            'body'      => 'required|string|max:2000',
            'parent_id' => 'nullable|integer|exists:team_week_posts,id',
        ]);

        $post = TeamWeekPost::create(array_merge($validated, [
            'team_id' => $team->id,
            'user_id' => Auth::id(),
        ]));

        $post->load('user:id,name,user_role');

        return response()->json([
            'id'         => $post->id,
            'body'       => $post->body,
            'parent_id'  => $post->parent_id,
            'user_id'    => $post->user_id,
            'user_name'  => $post->user?->name,
            'user_role'  => $post->user?->user_role,
            'created_at' => $post->created_at?->format('Y-m-d H:i'),
        ], 201);
    }

    public function destroy(Team $team, TeamWeekPost $post)
    {
        app(TeamRoomController::class)->assertMember($team);
        abort_unless($post->team_id === $team->id, 404);

        if ($post->user_id !== Auth::id() && !Auth::user()->isSuperAdmin()) {
            abort(403);
        }

        $post->delete();

        return response()->noContent();
    }
}
