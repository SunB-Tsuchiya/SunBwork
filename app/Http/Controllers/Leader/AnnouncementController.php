<?php

namespace App\Http\Controllers\Leader;

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
    /** Leader: 送信済み＋下書き一覧 */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            $contextId = session('superadmin_context.company_id');
            if ($contextId === null) {
                return Inertia::render('Leader/Announcements/Index', [
                    'sent'         => [],
                    'drafts'       => [],
                    'isGlobalMode' => true,
                ]);
            }
        }

        $base = Announcement::where('sender_id', $user->id);

        if ($user->isSuperAdmin()) {
            $contextId = session('superadmin_context.company_id');
            $base->whereHas('recipients', function ($q) use ($contextId) {
                $q->whereHas('user', fn($u) => $u->where('company_id', $contextId));
            });
        }

        $map = fn ($a) => [
            'id'               => $a->id,
            'title'            => $a->title,
            'target_type'      => $a->target_type,
            'recipients_count' => $a->recipients_count,
            'read_count'       => $a->read_count,
            'created_at'       => $a->created_at->format('Y/m/d H:i'),
        ];

        $counts = fn ($q) => $q
            ->withCount('recipients')
            ->withCount(['recipients as read_count' => fn ($q) => $q->whereNotNull('read_at')])
            ->orderByDesc('created_at');

        $sent   = $counts((clone $base)->where('status', 'sent'))->get()->map($map);
        $drafts = $counts((clone $base)->where('status', 'draft'))->get()->map($map);

        return Inertia::render('Leader/Announcements/Index', [
            'sent'         => $sent,
            'drafts'       => $drafts,
            'isGlobalMode' => false,
        ]);
    }

    /** Leader: 送信済みお知らせ詳細（受信者一覧＋既読状況＋添付） */
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

        return Inertia::render('Leader/Announcements/Show', [
            'announcement' => [
                'id'               => $announcement->id,
                'title'            => $announcement->title,
                'content'          => $announcement->content,
                'target_type'      => $announcement->target_type,
                'status'           => $announcement->status,
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

    /** Leader: 作成フォーム */
    public function create(Request $request)
    {
        $user = $request->user();

        $isCrossCompany = $user->isSuperAdmin() || $user->company?->company_type === 'general';

        $userQuery = User::with(['assignment', 'department'])->whereNotNull('department_id')->orderBy('name');
        if (! $isCrossCompany) {
            $userQuery->where('company_id', $user->company_id);
        }
        $users = $userQuery->get()->map(fn ($u) => [
            'id'              => $u->id,
            'name'            => $u->name,
            'company_id'      => $u->company_id,
            'assignment_name' => $u->assignment?->name ?? '',
            'employment_type' => $u->employment_type ?? 'regular',
        ]);

        $companies = $isCrossCompany
            ? Company::where('code', '!=', 'SUPERADMIN')->active()->ordered()->get(['id', 'name'])
            : null;

        return Inertia::render('Leader/Announcements/Create', compact('users', 'companies'));
    }

    /** Leader: お知らせ送信 or 下書き保存 */
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

        $sender         = $request->user();
        $isDraft        = $request->boolean('is_draft');
        $isCrossCompany = $sender->isSuperAdmin() || $sender->company?->company_type === 'general';

        $targetCompanyId = null;
        $scopeCompanyId  = $sender->company_id;

        if ($isCrossCompany) {
            if ($request->filled('target_company_id')) {
                $targetCompanyId = (int) $request->target_company_id;
                $scopeCompanyId  = $targetCompanyId;
            } else {
                $scopeCompanyId = null;
            }
        }

        $announcement = Announcement::create([
            'sender_id'         => $sender->id,
            'target_type'       => $request->target_type,
            'title'             => $request->title,
            'content'           => $request->content,
            'target_company_id' => $targetCompanyId,
            'status'            => $isDraft ? 'draft' : 'sent',
        ]);

        $recipientIds = match ($request->target_type) {
            'all' => $scopeCompanyId
                ? User::where('company_id', $scopeCompanyId)->pluck('id')->toArray()
                : User::pluck('id')->toArray(),
            'employees_only' => $scopeCompanyId
                ? User::where('company_id', $scopeCompanyId)->whereIn('employment_type', ['regular', 'contract'])->pluck('id')->toArray()
                : User::whereIn('employment_type', ['regular', 'contract'])->pluck('id')->toArray(),
            'individual' => $request->user_ids ?? [],
        };

        if (! empty($recipientIds)) {
            $rows = array_map(fn ($uid) => [
                'announcement_id' => $announcement->id,
                'user_id'         => $uid,
                'created_at'      => now(),
                'updated_at'      => now(),
            ], array_values($recipientIds));

            AnnouncementRecipient::upsert($rows, ['announcement_id', 'user_id'], ['updated_at']);
        }

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $attachmentService->storeUploadedFile(
                    $file, $sender, Announcement::class, $announcement->id
                );
            }
        }

        $message = $isDraft ? '下書きを保存しました。' : 'お知らせを送信しました。';

        return redirect()->route('leader.announcements.index')->with('success', $message);
    }

    /** Leader: 編集フォーム */
    public function edit(Request $request, Announcement $announcement)
    {
        abort_if($announcement->sender_id !== $request->user()->id, 403);

        $announcement->load('attachments');

        $data = [
            'announcement' => [
                'id'          => $announcement->id,
                'title'       => $announcement->title,
                'content'     => $announcement->content,
                'status'      => $announcement->status,
                'target_type' => $announcement->target_type,
                'attachments' => $announcement->attachments->map(fn ($a) => [
                    'id'            => $a->id,
                    'url'           => asset('storage/' . $a->path),
                    'thumb_url'     => null,
                    'original_name' => $a->original_name,
                    'mime'          => $a->mime_type,
                ]),
            ],
        ];

        // 下書きの場合は受信者選択UIも渡す
        if ($announcement->status === 'draft') {
            $user           = $request->user();
            $isCrossCompany = $user->isSuperAdmin() || $user->company?->company_type === 'general';

            $userQuery = User::with(['assignment', 'department'])->whereNotNull('department_id')->orderBy('name');
            if (! $isCrossCompany) {
                $userQuery->where('company_id', $user->company_id);
            }
            $data['users'] = $userQuery->get()->map(fn ($u) => [
                'id'              => $u->id,
                'name'            => $u->name,
                'company_id'      => $u->company_id,
                'assignment_name' => $u->assignment?->name ?? '',
                'employment_type' => $u->employment_type ?? 'regular',
            ]);

            $data['companies'] = $isCrossCompany
                ? Company::where('code', '!=', 'SUPERADMIN')->active()->ordered()->get(['id', 'name'])
                : null;

            $data['announcement']['recipient_ids'] = $announcement->recipients()->pluck('user_id')->toArray();
        }

        return Inertia::render('Leader/Announcements/Edit', $data);
    }

    /** Leader: お知らせ更新 */
    public function update(Request $request, Announcement $announcement, AttachmentService $attachmentService)
    {
        abort_if($announcement->sender_id !== $request->user()->id, 403);

        $rules = [
            'title'                   => 'required|string|max:255',
            'content'                 => 'required|string',
            'attachments'             => 'nullable|array|max:10',
            'attachments.*'           => 'file|max:20480',
            'remove_attachment_ids'   => 'nullable|array',
            'remove_attachment_ids.*' => 'integer',
        ];

        // 下書きは宛先変更も可能
        if ($announcement->status === 'draft') {
            $rules['target_type']       = 'required|in:all,employees_only,individual';
            $rules['target_company_id'] = 'nullable|exists:companies,id';
            $rules['user_ids']          = 'required_if:target_type,individual|array';
            $rules['user_ids.*']        = 'integer|exists:users,id';
        }

        $request->validate($rules);

        $announcement->update([
            'title'   => $request->title,
            'content' => $request->content,
        ]);

        // 下書き: 宛先更新
        if ($announcement->status === 'draft') {
            $sender         = $request->user();
            $isCrossCompany = $sender->isSuperAdmin() || $sender->company?->company_type === 'general';

            $targetCompanyId = null;
            $scopeCompanyId  = $sender->company_id;

            if ($isCrossCompany) {
                if ($request->filled('target_company_id')) {
                    $targetCompanyId = (int) $request->target_company_id;
                    $scopeCompanyId  = $targetCompanyId;
                } else {
                    $scopeCompanyId = null;
                }
            }

            $announcement->update([
                'target_type'       => $request->target_type,
                'target_company_id' => $targetCompanyId,
            ]);

            $recipientIds = match ($request->target_type) {
                'all' => $scopeCompanyId
                    ? User::where('company_id', $scopeCompanyId)->pluck('id')->toArray()
                    : User::pluck('id')->toArray(),
                'employees_only' => $scopeCompanyId
                    ? User::where('company_id', $scopeCompanyId)->whereIn('employment_type', ['regular', 'contract'])->pluck('id')->toArray()
                    : User::whereIn('employment_type', ['regular', 'contract'])->pluck('id')->toArray(),
                'individual' => $request->user_ids ?? [],
            };

            $announcement->recipients()->delete();
            if (! empty($recipientIds)) {
                $rows = array_map(fn ($uid) => [
                    'announcement_id' => $announcement->id,
                    'user_id'         => $uid,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ], array_values($recipientIds));
                AnnouncementRecipient::upsert($rows, ['announcement_id', 'user_id'], ['updated_at']);
            }
        }

        if ($request->filled('remove_attachment_ids')) {
            foreach ($announcement->attachments()->whereIn('attachments.id', $request->remove_attachment_ids)->get() as $att) {
                $attachmentService->deleteAttachment($att);
            }
        }

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $attachmentService->storeUploadedFile(
                    $file, $request->user(), Announcement::class, $announcement->id
                );
            }
        }

        return redirect()->route('leader.announcements.show', $announcement)
            ->with('success', 'お知らせを更新しました。');
    }

    /** Leader: 下書きを送信 */
    public function send(Request $request, Announcement $announcement)
    {
        abort_if($announcement->sender_id !== $request->user()->id, 403);
        abort_if($announcement->status !== 'draft', 422);

        $announcement->update(['status' => 'sent']);

        return redirect()->route('leader.announcements.show', $announcement)
            ->with('success', 'お知らせを送信しました。');
    }

    /** Leader: お知らせ削除 */
    public function destroy(Request $request, Announcement $announcement, AttachmentService $attachmentService)
    {
        abort_if($announcement->sender_id !== $request->user()->id, 403);

        $announcement->load('attachments');

        foreach ($announcement->attachments as $att) {
            $attachmentService->deleteAttachment($att);
        }

        $announcement->recipients()->delete();
        $announcement->delete();

        return redirect()->route('leader.announcements.index')
            ->with('success', 'お知らせを削除しました。');
    }
}
