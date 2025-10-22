<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\AttachmentService;

class AttachmentController extends Controller
{
    // 認可付きでストレージ内添付ファイルを配信する
    // クエリ ?path=attachments/xxx か ?id=123 を受け付ける
    public function stream(Request $request)
    {
        $user = $request->user();

        $path = $request->query('path');
        $id = $request->query('id');

        if (!$path && !$id) {
            abort(400, 'path or id is required');
        }

        if ($id) {
            $att = Attachment::find($id);
            if (!$att) abort(404);
            $path = $att->path;
        }

        // normalize
        $path = ltrim((string)$path, '\\/');

        // only allow under attachments/ or chat/ or bot/
        if (!str_starts_with($path, 'attachments/') && !str_starts_with($path, 'chat/') && !str_starts_with($path, 'bot/')) {
            // disallow arbitrary paths
            abort(403, '不正なパスです');
        }

        if (!Storage::disk('public')->exists($path)) {
            abort(404);
        }
        $svc = new AttachmentService();
        try {
            $fullPath = $svc->diskPath($path ?? $path);
        } catch (\RuntimeException $e) {
            // diskPath throws when not found; map to 404
            abort(404);
        }

        // Authorization: prefer checking polymorphic links (attachmentables) first,
        return response()->file($fullPath);
        if (isset($att)) {
            try {
                // If the attachment is linked to one or more messages via attachmentables,
                // check message sender/recipient permissions.
                $linkedMessage = $att->messages()->with('recipients')->first();
                if ($linkedMessage) {
                    $isAllowed = ($linkedMessage->from_user_id === $user->id) || $linkedMessage->recipients->pluck('user_id')->contains($user->id);
                    if (!$isAllowed) abort(403, 'このファイルにアクセスする権限がありません');
                } else {
                    // No message link via pivot: fallback to ownership by user_id or admin
                    if ($att->user_id && $att->user_id !== $user->id) {
                        if (!($user->user_role ?? '') || $user->user_role !== 'admin') {
                            abort(403, 'このファイルにアクセスする権限がありません');
                        }
                    }
                }
            } catch (\Exception $ex) {
                Log::warning('attachment auth lookup failed: ' . $ex->getMessage());
            }
        } else {
            // best-effort: try to find Attachment by path and apply same logic
            try {
                $maybe = Attachment::where('path', $path)->first();
                if ($maybe) {
                    $linkedMessage = $maybe->messages()->with('recipients')->first();
                    if ($linkedMessage) {
                        $isAllowed = ($linkedMessage->from_user_id === $user->id) || $linkedMessage->recipients->pluck('user_id')->contains($user->id);
                        if (!$isAllowed) abort(403, 'このファイルにアクセスする権限がありません');
                    } else {
                        if ($maybe->user_id && $maybe->user_id !== $user->id) {
                            if (!($user->user_role ?? '') || $user->user_role !== 'admin') {
                                abort(403, 'このファイルにアクセスする権限がありません');
                            }
                        }
                    }
                }
            } catch (\Exception $ex) {
                Log::warning('attachment auth lookup failed: ' . $ex->getMessage());
            }
        }

        $full = Storage::disk('public')->path($path);
        return response()->file($full);
    }

    /**
     * Delete an attachment record and its storage files (authorized users only).
     */
    public function destroy(Request $request, Attachment $attachment)
    {
        $user = $request->user();

        // Authorization: allow when the uploader or an admin deletes,
        // or when the attachment is linked to a Diary owned by the current user.
        $isOwner = ($attachment->user_id && intval($attachment->user_id) === intval($user->id));
        $isAdmin = ($user->user_role ?? '') === 'admin';
        $isDiaryOwnerLink = false;
        try {
            if ($attachment->diaries()->where('user_id', $user->id)->exists()) {
                $isDiaryOwnerLink = true;
            }
        } catch (\Throwable $__e) {
            // ignore and continue
        }

        if (!($isOwner || $isAdmin || $isDiaryOwnerLink)) {
            abort(403, 'この操作を行う権限がありません');
        }

        // Delegate full deletion work to AttachmentService which will remove files, pivots and linked messages safely
        $svc = new AttachmentService();
        try {
            $ok = $svc->deleteAttachment($attachment, $user);
            if (!$ok) {
                return response()->json(['error' => 'could_not_delete'], 500);
            }
        } catch (\Throwable $e) {
            Log::warning('AttachmentController::destroy: service deletion failed: ' . $e->getMessage(), ['attachment_id' => $attachment->id ?? null]);
            return response()->json(['error' => 'could_not_delete'], 500);
        }

        return response()->json(['message' => 'deleted']);
    }

    /**
     * Delete an attachment by lookup (path or attachment_id in request body).
     * Useful for frontend clients that only have a storage path and not the DB id.
     */
    public function destroyByPath(Request $request)
    {
        $user = $request->user();
        $path = $request->input('path');
        $id = $request->input('attachment_id');

        if (!$path && !$id) {
            return response()->json(['error' => 'path_or_id_required'], 400);
        }

        $attachment = null;
        if ($id) {
            $attachment = Attachment::find($id);
        }
        if (!$attachment && $path) {
            $p = ltrim((string)$path, '\\/');
            $attachment = Attachment::where('path', $p)->first();
            if (!$attachment) {
                // try basename fallback
                $basename = basename($p);
                $attachment = Attachment::where('path', 'like', '%' . $basename)->first();
            }
        }

        if (!$attachment) {
            return response()->json(['error' => 'not_found'], 404);
        }

        // Perform same authorization checks as destroy()
        $isOwner = ($attachment->user_id && intval($attachment->user_id) === intval($user->id));
        $isAdmin = ($user->user_role ?? '') === 'admin';
        $isDiaryOwnerLink = false;
        try {
            if ($attachment->diaries()->where('user_id', $user->id)->exists()) {
                $isDiaryOwnerLink = true;
            }
        } catch (\Throwable $__e) {
            // ignore and continue
        }

        if (!($isOwner || $isAdmin || $isDiaryOwnerLink)) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        $svc = new AttachmentService();
        try {
            $ok = $svc->deleteAttachment($attachment, $user);
            if (!$ok) {
                return response()->json(['error' => 'could_not_delete'], 500);
            }
        } catch (\Throwable $e) {
            Log::warning('AttachmentController::destroyByPath: service deletion failed: ' . $e->getMessage(), ['attachment_id' => $attachment->id ?? null]);
            return response()->json(['error' => 'could_not_delete'], 500);
        }

        return response()->json(['message' => 'deleted']);
    }
}
