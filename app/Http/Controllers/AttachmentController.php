<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

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

        // Authorization: prefer checking polymorphic links (attachmentables) first,
        // then fall back to legacy columns like message_id/user_id for compatibility.
        if (isset($att)) {
            try {
                // If the attachment is linked to one or more messages via attachmentables,
                // check message sender/recipient permissions.
                $linkedMessage = $att->messages()->with('recipients')->first();
                if ($linkedMessage) {
                    $isAllowed = ($linkedMessage->from_user_id === $user->id) || $linkedMessage->recipients->pluck('user_id')->contains($user->id);
                    if (!$isAllowed) abort(403, 'このファイルにアクセスする権限がありません');
                } else {
                    // No message link via pivot; fall back to legacy message_id column if present
                    if ($att->message_id) {
                        $msg = $att->message()->with('recipients')->first();
                        if ($msg) {
                            $isAllowed = ($msg->from_user_id === $user->id) || $msg->recipients->pluck('user_id')->contains($user->id);
                            if (!$isAllowed) abort(403, 'このファイルにアクセスする権限がありません');
                        }
                    } else {
                        // No message relation: allow owner (owner_type/owner_id or user_id) or admins
                        // If owner_type/owner_id present, allow when matches current user (owner may be a User model)
                        if ($att->owner_type && $att->owner_id) {
                            try {
                                if ($att->owner_type === 'App\\Models\\User' && intval($att->owner_id) !== intval($user->id)) {
                                    if (!($user->user_role ?? '') || $user->user_role !== 'admin') abort(403, 'このファイルにアクセスする権限がありません');
                                }
                            } catch (\Throwable $ex) {
                                Log::warning('owner auth lookup failed: ' . $ex->getMessage());
                            }
                        } else {
                            if ($att->user_id && $att->user_id !== $user->id) {
                                if (!($user->user_role ?? '') || $user->user_role !== 'admin') {
                                    abort(403, 'このファイルにアクセスする権限がありません');
                                }
                            }
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
                        if ($maybe->owner_type && $maybe->owner_id) {
                            try {
                                if ($maybe->owner_type === 'App\\Models\\User' && intval($maybe->owner_id) !== intval($user->id)) {
                                    if (!($user->user_role ?? '') || $user->user_role !== 'admin') abort(403, 'このファイルにアクセスする権限がありません');
                                }
                            } catch (\Throwable $ex) {
                                Log::warning('owner auth lookup failed: ' . $ex->getMessage());
                            }
                        } else {
                            if ($maybe->user_id && $maybe->user_id !== $user->id) {
                                if (!($user->user_role ?? '') || $user->user_role !== 'admin') {
                                    abort(403, 'このファイルにアクセスする権限がありません');
                                }
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
}
