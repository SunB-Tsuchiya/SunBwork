<?php

namespace App\Http\Controllers\Clerk;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Models\Company;
use App\Models\User;
use App\Services\AttachmentService;
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

    /** Clerk: 送信済みお知らせ詳細（受信者一覧＋既読状況＋添付） */
    public function show(Request $request, Announcement $announcement)
    {
        abort_if($announcement->sender_id !== $request->user()->id, 403);

        $announcement->load('attachments');

        $recipients = $announcement->recipients()
            ->with('user:id,name,employment_type,assignment_id')
            ->with('user.assignment:id,name')
            ->orderByRaw('read_at IS NULL DESC')
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
                'attachments'      => $announcement->attachments->map(fn ($a) => [
                    'id'            => $a->id,
                    'url'           => asset('storage/' . $a->path),
                    'thumb_url'     => null,
                    'original_name' => $a->original_name,
                    'mime'          => $a->mime_type,
                ]),
            ],
            'recipients' => $recipients,
        ]);
    }

    /** Clerk: 作成フォーム */
    public function create(Request $request)
    {
        $user = $request->user();

        // cross-company 時は全会社、それ以外は自社のみ
        $userQuery = User::with(['assignment', 'department'])->whereNotNull('department_id')->orderBy('name');
        if ($user->company?->company_type !== 'general') {
            $userQuery->where('company_id', $user->company_id);
        }
        $users = $userQuery->get()->map(fn ($u) => [
            'id'              => $u->id,
            'name'            => $u->name,
            'company_id'      => $u->company_id,
            'assignment_name' => $u->assignment?->name ?? '',
            'employment_type' => $u->employment_type ?? 'regular',
        ]);

        // cross-company 対象者: general タイプ会社のユーザーは全会社の一覧を受け取る
        $companies = $user->company?->company_type === 'general'
            ? Company::where('code', '!=', 'SUPERADMIN')->active()->ordered()->get(['id', 'name'])
            : null;

        return Inertia::render('Clerk/Announcements/Create', compact('users', 'companies'));
    }

    /** Clerk: お知らせ送信 */
    public function store(Request $request, AttachmentService $attachmentService)
    {
        $request->validate([
            'target_type'       => 'required|in:all,employees_only,individual',
            'target_company_id' => 'nullable|exists:companies,id',
            'title'             => 'required|string|max:255',
            'content'           => 'required|string',
            'user_ids'          => 'required_if:target_type,individual|array',
            'user_ids.*'        => 'integer|exists:users,id',
            'attachments'       => 'nullable|array|max:10',
            'attachments.*'     => 'file|max:20480',
        ]);

        $sender = $request->user();
        $isCrossCompanyUser = $sender->company?->company_type === 'general';

        // 送信先会社の決定
        // crossCompanyAnnouncement ユーザー:
        //   - 会社を指定した場合 → その会社のみ
        //   - 会社未指定の場合 → 全会社（scopeCompanyId = null）
        // 一般ユーザー: 自社のみ
        $targetCompanyId = null;
        $scopeCompanyId  = $sender->company_id; // デフォルト: 自社

        if ($isCrossCompanyUser) {
            if ($request->filled('target_company_id')) {
                $targetCompanyId = (int) $request->target_company_id;
                $scopeCompanyId  = $targetCompanyId;
            } else {
                $scopeCompanyId = null; // 未指定 = 全会社
            }
        }

        $announcement = Announcement::create([
            'sender_id'         => $sender->id,
            'target_type'       => $request->target_type,
            'title'             => $request->title,
            'content'           => $request->content,
            'target_company_id' => $targetCompanyId,
        ]);

        $recipientIds = match ($request->target_type) {
            'all' => $scopeCompanyId
                ? User::where('company_id', $scopeCompanyId)->pluck('id')->toArray()
                : User::pluck('id')->toArray(),
            'employees_only' => $scopeCompanyId
                ? User::where('company_id', $scopeCompanyId)->whereIn('employment_type', ['regular', 'contract'])->pluck('id')->toArray()
                : User::whereIn('employment_type', ['regular', 'contract'])->pluck('id')->toArray(),
            'individual' => $request->user_ids,
        };

        $rows = array_map(fn ($uid) => [
            'announcement_id' => $announcement->id,
            'user_id'         => $uid,
            'created_at'      => now(),
            'updated_at'      => now(),
        ], array_values($recipientIds));

        AnnouncementRecipient::upsert($rows, ['announcement_id', 'user_id'], ['updated_at']);

        // 添付ファイルの保存
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $attachmentService->storeUploadedFile(
                    $file, $sender, Announcement::class, $announcement->id
                );
            }
        }

        return redirect()->route('clerk.announcements.index')
            ->with('success', 'お知らせを送信しました。');
    }

    /** Clerk: 編集フォーム */
    public function edit(Request $request, Announcement $announcement)
    {
        abort_if($announcement->sender_id !== $request->user()->id, 403);

        $announcement->load('attachments');

        return Inertia::render('Clerk/Announcements/Edit', [
            'announcement' => [
                'id'      => $announcement->id,
                'title'   => $announcement->title,
                'content' => $announcement->content,
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

    /** Clerk: お知らせ更新（タイトル・本文・添付のみ。受信者は変更しない） */
    public function update(Request $request, Announcement $announcement, AttachmentService $attachmentService)
    {
        abort_if($announcement->sender_id !== $request->user()->id, 403);

        $request->validate([
            'title'              => 'required|string|max:255',
            'content'            => 'required|string',
            'attachments'        => 'nullable|array|max:10',
            'attachments.*'      => 'file|max:20480',
            'remove_attachment_ids'   => 'nullable|array',
            'remove_attachment_ids.*' => 'integer',
        ]);

        $announcement->update([
            'title'   => $request->title,
            'content' => $request->content,
        ]);

        // 指定された添付ファイルを削除
        if ($request->filled('remove_attachment_ids')) {
            foreach ($announcement->attachments()->whereIn('attachments.id', $request->remove_attachment_ids)->get() as $att) {
                $attachmentService->deleteAttachment($att);
            }
        }

        // 新しい添付ファイルを追加
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $attachmentService->storeUploadedFile(
                    $file, $request->user(), Announcement::class, $announcement->id
                );
            }
        }

        return redirect()->route('clerk.announcements.show', $announcement)
            ->with('success', 'お知らせを更新しました。');
    }

    /** Clerk: お知らせ削除 */
    public function destroy(Request $request, Announcement $announcement, AttachmentService $attachmentService)
    {
        abort_if($announcement->sender_id !== $request->user()->id, 403);

        $announcement->load('attachments');

        // 添付ファイルをすべて削除
        foreach ($announcement->attachments as $att) {
            $attachmentService->deleteAttachment($att);
        }

        // 受信者レコードと本体を削除
        $announcement->recipients()->delete();
        $announcement->delete();

        return redirect()->route('clerk.announcements.index')
            ->with('success', 'お知らせを削除しました。');
    }
}
