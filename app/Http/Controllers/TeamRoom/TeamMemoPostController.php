<?php

namespace App\Http\Controllers\TeamRoom;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamMemoPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeamMemoPostController extends Controller
{
    public function index(Team $team)
    {
        app(TeamRoomController::class)->assertMember($team);

        $posts = TeamMemoPost::where('team_id', $team->id)
            ->with('user:id,name,user_role')
            ->orderBy('created_at')
            ->get();

        return response()->json($this->mapPosts($posts));
    }

    public function store(Request $request, Team $team)
    {
        app(TeamRoomController::class)->assertMember($team);

        $validated = $request->validate([
            'body'      => 'required|string|max:2000',
            'parent_id' => 'nullable|integer|exists:team_memo_posts,id',
        ]);

        $post = TeamMemoPost::create(array_merge($validated, [
            'team_id' => $team->id,
            'user_id' => Auth::id(),
        ]));

        $post->load('user:id,name,user_role');

        return response()->json($this->mapPost($post), 201);
    }

    public function update(Request $request, Team $team, TeamMemoPost $memoPost)
    {
        app(TeamRoomController::class)->assertMember($team);
        abort_unless($memoPost->team_id === $team->id, 404);

        if ($memoPost->user_id !== Auth::id() && ! Auth::user()->isSuperAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $memoPost->update($validated);
        $memoPost->load('user:id,name,user_role');

        return response()->json($this->mapPost($memoPost));
    }

    public function destroy(Team $team, TeamMemoPost $memoPost)
    {
        app(TeamRoomController::class)->assertMember($team);
        abort_unless($memoPost->team_id === $team->id, 404);

        if ($memoPost->user_id !== Auth::id() && ! Auth::user()->isSuperAdmin()) {
            abort(403);
        }

        $memoPost->delete();

        return response()->noContent();
    }

    private function mapPost(TeamMemoPost $p): array
    {
        return [
            'id'         => $p->id,
            'body'       => $p->body,
            'parent_id'  => $p->parent_id,
            'user_id'    => $p->user_id,
            'user_name'  => $p->user?->name,
            'user_role'  => $p->user?->user_role,
            'created_at' => $p->created_at?->format('Y-m-d H:i'),
            'updated_at' => $p->updated_at?->format('Y-m-d H:i'),
        ];
    }

    private function mapPosts($posts): array
    {
        return $posts->map(fn ($p) => $this->mapPost($p))->values()->all();
    }
}
