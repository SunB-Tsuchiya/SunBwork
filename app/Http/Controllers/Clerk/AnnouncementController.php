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
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($a) => [
                'id'           => $a->id,
                'title'        => $a->title,
                'target_type'  => $a->target_type,
                'recipients_count' => $a->recipients_count,
                'created_at'   => $a->created_at->format('Y/m/d H:i'),
            ]);

        return Inertia::render('Clerk/Announcements/Index', compact('announcements'));
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

        // 送信者自身は除外
        $recipientIds = array_filter($recipientIds, fn ($id) => $id !== $request->user()->id);

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
