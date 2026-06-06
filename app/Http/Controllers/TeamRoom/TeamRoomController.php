<?php

namespace App\Http\Controllers\TeamRoom;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamBoard;
use App\Models\TeamDutyTable;
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

        // 部署チーム: SuperAdmin/Admin は全部署チームを表示（所属不問）
        $deptQuery = Team::with(['department', 'members:id,name'])
            ->where('team_type', 'department')
            ->orderBy('name');

        if ($user->isSuperAdmin()) {
            // 全社横断で全部署チームを表示
        } elseif ($user->isAdmin()) {
            // 自社の全部署チームを表示
            $deptQuery->where('company_id', $user->company_id);
        } else {
            // Leader 以下: 自分が所属する部署チームのみ
            $deptQuery->whereIn('id', $memberTeamIds);
        }

        $departmentTeams = $deptQuery->get();

        // 特別チーム・一般チームは所属チームのみ
        $otherTeams = Team::with(['department', 'members:id,name'])
            ->whereIn('team_type', ['special', 'unit'])
            ->whereIn('id', $memberTeamIds)
            ->orderBy('name')
            ->get();

        $mapTeam = fn ($team) => [
            'id'           => $team->id,
            'name'         => $team->name,
            'department'   => $team->department,
            'leader_name'  => $team->leader_id
                ? ($team->members->firstWhere('id', $team->leader_id)?->name ?? null)
                : null,
            'member_names' => $team->members->map(fn ($m) => $m->name)->values(),
        ];

        return Inertia::render('TeamRoom/Index', [
            'departmentTeams' => $departmentTeams->map($mapTeam)->values(),
            'specialTeams'    => $otherTeams->where('team_type', 'special')->map($mapTeam)->values(),
            'unitTeams'       => $otherTeams->where('team_type', 'unit')->map($mapTeam)->values(),
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

        $dutyTables = TeamDutyTable::where('team_id', $team->id)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->get(['id', 'team_id', 'user_id', 'title', 'description', 'html_content', 'created_at']);

        return Inertia::render('TeamRoom/Show', [
            'team'          => $team,
            'leader'        => $leader,
            'board'         => $board,
            'recentMinutes' => $recentMinutes,
            'dutyTables'    => $dutyTables,
            'activeTab'     => $request->query('tab', 'overview'),
        ]);
    }

    public function assertMember(Team $team): void
    {
        if (! in_array($team->team_type, ['unit', 'department', 'special'])) {
            abort(404);
        }
        $user = Auth::user();

        // SuperAdmin は全チームルームに入れる
        if ($user->isSuperAdmin()) {
            return;
        }

        // Admin は自社の部署チームルームに入れる
        if ($user->isAdmin() && $team->team_type === 'department' && $team->company_id === $user->company_id) {
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
