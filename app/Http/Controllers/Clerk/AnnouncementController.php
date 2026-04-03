<?php

namespace App\Http\Controllers\Clerk;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnnouncementController extends Controller
{
    /** Clerk: 送信済みお知らせ一覧 */
    public function index(Request $request)
    {
        $announcements = Announcement::where('sender_id', $request->user()->id)
            ->withCount('recipients')
            ->withCount(['recipients as read_count' => fn ($q) => $q->whereNotNull('read_at')])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($a) => [
                'id'               => $a->id,
                'title'            => $a->title,
                'target_type'      => $a->target_type,
                'recipients_count' => $a->recipients_count,
                'read_count'       => $a->read_count,
                'created_at'       => $a->created_at->format('Y/m/d H:i'),
            ]);

        return Inertia::render('Clerk/Announcements/Index', compact('announcements'));
    }

    /** Clerk: 送信済みお知らせ詳細（受信者一覧＋既読状況） */
    public function show(Request $request, Announcement $announcement)
    {
        // 自分が送信したものだけ閲覧可
        abort_if($announcement->sender_id !== $request->user()->id, 403);

        $recipients = $announcement->recipients()
            ->with('user:id,name,employment_type,assignment_id')
            ->with('user.assignment:id,name')
            ->orderByRaw('read_at IS NULL DESC') // 未読を上に
            ->orderBy('read_at')
            ->get()
            ->map(fn ($r) => [
                'id'              => $r->id,
                'name'            => $r->user->name ?? '(削除済)',
                'assignment_name' => $r->user->assignment?->name ?? '',
                'employment_type' => $r->user->employment_type ?? '',
                'is_read'         => $r->read_at !== null,
                'read_at'         => $r->read_at?->format('Y/m/d H:i'),
            ]);

        return Inertia::render('Clerk/Announcements/Show', [
            'announcement' => [
                'id'               => $announcement->id,
                'title'            => $announcement->title,
                'content'          => $announcement->content,
                'target_type'      => $announcement->target_type,
                'recipients_count' => $recipients->count(),
                'read_count'       => $recipients->where('is_read', true)->count(),
                'created_at'       => $announcement->created_at->format('Y/m/d H:i'),
            ],
            'recipients' => $recipients,
        ]);
    }

    /** Clerk: 作成フォーム */
    public function create(Request $request)
    {
        $users = User::with(['assignment', 'department'])
            ->whereNotNull('department_id')
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => [
                'id'              => $u->id,
                'name'            => $u->name,
                'assignment_name' => $u->assignment?->name ?? '',
                'employment_type' => $u->employment_type ?? 'regular',
            ]);

        return Inertia::render('Clerk/Announcements/Create', compact('users'));
    }

    /** Clerk: お知らせ送信 */
    public function store(Request $request)
    {
        $request->validate([
            'target_type'  => 'required|in:all,employees_only,individual',
            'title'        => 'required|string|max:255',
            'content'      => 'required|string',
            'user_ids'     => 'required_if:target_type,individual|array',
            'user_ids.*'   => 'integer|exists:users,id',
        ]);

        $announcement = Announcement::create([
            'sender_id'   => $request->user()->id,
            'target_type' => $request->target_type,
            'title'       => $request->title,
            'content'     => $request->content,
        ]);

        $recipientIds = match ($request->target_type) {
            'all'            => User::pluck('id')->toArray(),
            'employees_only' => User::whereIn('employment_type', ['regular', 'contract'])->pluck('id')->toArray(),
            'individual'     => $request->user_ids,
        };

        $rows = array_map(fn ($uid) => [
            'announcement_id' => $announcement->id,
            'user_id'         => $uid,
            'created_at'      => now(),
            'updated_at'      => now(),
        ], array_values($recipientIds));

        AnnouncementRecipient::upsert($rows, ['announcement_id', 'user_id'], ['updated_at']);

        return redirect()->route('clerk.announcements.index')
            ->with('success', 'お知らせを送信しました。');
    }
}
