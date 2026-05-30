<?php

namespace App\Http\Controllers;

use App\Models\AnnouncementRecipient;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnnouncementController extends Controller
{
    /** 受信者: お知らせ一覧（日付別） */
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $items = AnnouncementRecipient::where('user_id', $userId)
            ->with('announcement.sender')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($r) => [
                'id'         => $r->id,
                'announcement_id' => $r->announcement_id,
                'title'      => $r->announcement->title,
                'sender'     => $r->announcement->sender?->name ?? '',
                'is_read'    => $r->read_at !== null,
                'date'       => $r->created_at->format('Y/m/d'),
                'created_at' => $r->created_at->format('Y/m/d H:i'),
            ]);

        // 日付別グループ
        $grouped = $items->groupBy('date')->map(fn ($group, $date) => [
            'date'  => $date,
            'items' => $group->values(),
        ])->values();

        return Inertia::render('Announcements/Index', [
            'grouped' => $grouped,
        ]);
    }

    /** 受信者: お知らせ詳細（既読にする） */
    public function show(Request $request, $id)
    {
        $recipient = AnnouncementRecipient::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->with(['announcement.sender', 'announcement.attachments'])
            ->firstOrFail();

        if ($recipient->read_at === null) {
            $recipient->update(['read_at' => now()]);
        }

        $announcement = $recipient->announcement;

        return Inertia::render('Announcements/Show', [
            'recipient' => [
                'id'          => $recipient->id,
                'title'       => $announcement->title,
                'content'     => $announcement->content,
                'sender'      => $announcement->sender?->name ?? '',
                'target_type' => $announcement->target_type,
                'created_at'  => $recipient->created_at->format('Y/m/d H:i'),
                'read_at'     => $recipient->read_at?->format('Y/m/d H:i'),
                'attachments' => $announcement->attachments->map(fn ($a) => [
                    'id'            => $a->id,
                    'url'           => asset('storage/' . $a->path),
                    'thumb_url'     => null,
                    'original_name' => $a->original_name,
                    'mime'          => $a->mime_type,
                ]),
            ],
        ]);
    }
}
