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

        // Authorization: if we have an Attachment model, prefer it
        if (isset($att)) {
            // If attachment is linked to a message, check that the current user is sender or a recipient
            if ($att->message_id) {
                try {
                    $msg = $att->message()->with('recipients')->first();
                    if ($msg) {
                        $isAllowed = ($msg->from_user_id === $user->id) || $msg->recipients->pluck('user_id')->contains($user->id);
                        if (!$isAllowed) {
                            abort(403, 'このファイルにアクセスする権限がありません');
                        }
                    }
                } catch (\Exception $ex) {
                    Log::warning('message auth lookup failed: ' . $ex->getMessage());
                }
            } else {
                // allow owner or admins
                if ($att->user_id && $att->user_id !== $user->id) {
                    if (!($user->user_role ?? '') || $user->user_role !== 'admin') {
                        abort(403, 'このファイルにアクセスする権限がありません');
                    }
                }
            }
        } else {
            // best-effort: try to find Attachment by path
            try {
                $maybe = Attachment::where('path', $path)->first();
                if ($maybe) {
                    if ($maybe->user_id && $maybe->user_id !== $user->id) {
                        if (!($user->user_role ?? '') || $user->user_role !== 'admin') {
                            abort(403, 'このファイルにアクセスする権限がありません');
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
