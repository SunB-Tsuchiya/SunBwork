<?php

namespace App\Http\Controllers\TeamRoom;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamBoard;
use App\Models\TeamMeetingMinute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TeamRoomController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $memberTeamIds = DB::table('team_user')
            ->where('user_id', $user->id)
            ->pluck('team_id');

        $teams = Team::with(['department'])
            ->where('team_type', 'unit')
            ->whereIn('id', $memberTeamIds)
            ->orderBy('name')
            ->get();

        $teams->load(['members:id,name']);

        return Inertia::render('TeamRoom/Index', [
            'teams' => $teams->map(fn ($team) => [
                'id'           => $team->id,
                'name'         => $team->name,
                'department'   => $team->department,
                'leader_name'  => $team->leader_id
                    ? ($team->members->firstWhere('id', $team->leader_id)?->name ?? null)
                    : null,
                'member_names' => $team->members
                    ->map(fn ($m) => $m->name)
                    ->values(),
            ]),
        ]);
    }

    public function show(Request $request, $teamId)
    {
        $team = Team::with([
            'department',
            'members' => fn($q) => $q->select(['users.id', 'users.name', 'users.user_role', 'users.department_id']),
            'subLeaders:id,name',
        ])->findOrFail($teamId);

        $this->assertMember($team);

        $leader = $team->leader_id
            ? \App\Models\User::select('id', 'name')->find($team->leader_id)
            : null;

        $board = TeamBoard::with(['columns.cards'])->where('team_id', $team->id)->first();

        $recentMinutes = TeamMeetingMinute::where('team_id', $team->id)
            ->with('user:id,name')
            ->orderByDesc('held_at')
            ->limit(5)
            ->get(['id', 'title', 'content', 'held_at', 'user_id']);

        return Inertia::render('TeamRoom/Show', [
            'team'          => $team,
            'leader'        => $leader,
            'board'         => $board,
            'recentMinutes' => $recentMinutes,
            'activeTab'     => $request->query('tab', 'overview'),
        ]);
    }

    public function assertMember(Team $team): void
    {
        if ($team->team_type !== 'unit') {
            abort(404);
        }
        $user = Auth::user();
        if ($user->isSuperAdmin()) {
            return;
        }
        $isMember = DB::table('team_user')
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->exists();
        if (! $isMember) {
            abort(403, 'このチームのメンバーではありません');
        }
    }
}
