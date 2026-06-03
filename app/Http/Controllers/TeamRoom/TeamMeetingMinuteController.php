<?php

namespace App\Http\Controllers\TeamRoom;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Team;
use App\Models\TeamMeetingMinute;
use App\Services\AttachmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TeamMeetingMinuteController extends Controller
{
    public function index(Team $team)
    {
        app(TeamRoomController::class)->assertMember($team);

        $minutes = TeamMeetingMinute::where('team_id', $team->id)
            ->with('user:id,name')
            ->withCount('attendees')
            ->orderByDesc('held_at')
            ->get(['id', 'title', 'content', 'held_at', 'user_id']);

        return Inertia::render('TeamRoom/Show', [
            'team'      => $team->load('department'),
            'minutes'   => $minutes,
            'activeTab' => 'minutes',
        ]);
    }

    public function create(Team $team)
    {
        app(TeamRoomController::class)->assertMember($team);

        $members = DB::table('team_user')
            ->join('users', 'users.id', '=', 'team_user.user_id')
            ->where('team_user.team_id', $team->id)
            ->select('users.id', 'users.name')
            ->orderBy('users.name')
            ->get();

        return Inertia::render('TeamRoom/Minutes/Create', [
            'team'    => $team->load('department'),
            'members' => $members,
        ]);
    }

    public function store(Request $request, Team $team)
    {
        app(TeamRoomController::class)->assertMember($team);

        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'content'      => 'nullable|string',
            'held_at'      => 'required|date',
            'attendee_ids' => 'nullable|array',
            'attendee_ids.*' => 'integer|exists:users,id',
        ]);

        $minute = TeamMeetingMinute::create([
            'team_id' => $team->id,
            'user_id' => Auth::id(),
            'title'   => $validated['title'],
            'content' => $validated['content'] ?? null,
            'held_at' => $validated['held_at'],
        ]);

        if (! empty($validated['attendee_ids'])) {
            $minute->attendeeUsers()->sync($validated['attendee_ids']);
        }

        // Quill プレースホルダーから添付を紐付ける
        $this->linkPlaceholderAttachments($minute, $validated['content'] ?? '');

        return redirect()->route('team-rooms.minutes.show', ['team' => $team->id, 'minute' => $minute->id])
            ->with('success', '会議記録を作成しました');
    }

    public function show(Team $team, TeamMeetingMinute $minute)
    {
        app(TeamRoomController::class)->assertMember($team);
        abort_unless($minute->team_id === $team->id, 404);

        $minute->load([
            'user:id,name',
            'attendeeUsers:id,name',
            'comments.user:id,name',
            'attachments',
        ]);

        $svc = new AttachmentService();
        $attachments = $minute->attachments->map(fn($a) => array_merge($a->toArray(), [
            'url'       => $svc->getStreamUrl($a),
            'thumb_url' => $svc->getThumbUrl($a),
        ]));

        $members = DB::table('team_user')
            ->join('users', 'users.id', '=', 'team_user.user_id')
            ->where('team_user.team_id', $team->id)
            ->select('users.id', 'users.name')
            ->orderBy('users.name')
            ->get();

        return Inertia::render('TeamRoom/Minutes/Show', [
            'team'        => $team->load('department'),
            'minute'      => array_merge($minute->toArray(), ['attachments' => $attachments]),
            'members'     => $members,
            'canEdit'     => $this->canEdit($team, $minute),
        ]);
    }

    public function edit(Team $team, TeamMeetingMinute $minute)
    {
        app(TeamRoomController::class)->assertMember($team);
        abort_unless($minute->team_id === $team->id, 404);
        abort_unless($this->canEdit($team, $minute), 403);

        $minute->load(['attendeeUsers:id,name', 'attachments']);

        $members = DB::table('team_user')
            ->join('users', 'users.id', '=', 'team_user.user_id')
            ->where('team_user.team_id', $team->id)
            ->select('users.id', 'users.name')
            ->orderBy('users.name')
            ->get();

        return Inertia::render('TeamRoom/Minutes/Edit', [
            'team'    => $team->load('department'),
            'minute'  => $minute,
            'members' => $members,
        ]);
    }

    public function update(Request $request, Team $team, TeamMeetingMinute $minute)
    {
        app(TeamRoomController::class)->assertMember($team);
        abort_unless($minute->team_id === $team->id, 404);
        abort_unless($this->canEdit($team, $minute), 403);

        $validated = $request->validate([
            'title'          => 'required|string|max:255',
            'content'        => 'nullable|string',
            'held_at'        => 'required|date',
            'attendee_ids'   => 'nullable|array',
            'attendee_ids.*' => 'integer|exists:users,id',
        ]);

        $minute->update([
            'title'   => $validated['title'],
            'content' => $validated['content'] ?? null,
            'held_at' => $validated['held_at'],
        ]);

        $minute->attendeeUsers()->sync($validated['attendee_ids'] ?? []);

        $this->linkPlaceholderAttachments($minute, $validated['content'] ?? '');

        return redirect()->route('team-rooms.minutes.show', ['team' => $team->id, 'minute' => $minute->id])
            ->with('success', '会議記録を更新しました');
    }

    public function destroy(Team $team, TeamMeetingMinute $minute)
    {
        app(TeamRoomController::class)->assertMember($team);
        abort_unless($minute->team_id === $team->id, 404);
        abort_unless($this->canEdit($team, $minute), 403);

        $minute->delete();

        return redirect()->route('team-rooms.show', ['team' => $team->id, 'tab' => 'minutes'])
            ->with('success', '会議記録を削除しました');
    }

    private function canEdit(Team $team, TeamMeetingMinute $minute): bool
    {
        $user = Auth::user();
        if ($user->isSuperAdmin()) return true;
        return $minute->user_id === $user->id || $team->leader_id === $user->id;
    }

    private function linkPlaceholderAttachments(TeamMeetingMinute $minute, string $content): void
    {
        if (preg_match_all('/\[\[attachment:(\d+):[^\]]+\]\]/', $content, $matches)) {
            $ids = $matches[1];
            $attachments = Attachment::whereIn('id', $ids)->get();
            $rows = [];
            $now = now();
            foreach ($attachments as $a) {
                $rows[] = [
                    'attachment_id'  => $a->id,
                    'attachable_type' => TeamMeetingMinute::class,
                    'attachable_id'  => $minute->id,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }
            if ($rows) {
                DB::table('attachmentables')->insertOrIgnore($rows);
            }
        }
    }
}
