<?php

namespace App\Http\Controllers\Bot;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AiConversation;
use App\Models\AiMessage;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;

class AiHistoryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $scope = $request->query('scope', 'mine'); // 'mine' or 'all'

        $isAdmin = $user && (
            (method_exists($user, 'isAdmin') && $user->isAdmin()) ||
            (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) ||
            in_array(($user->user_role ?? ''), ['admin', 'superadmin'])
        );

        // include the user relation so frontend (admin) can display user info
        $query = AiConversation::withCount('messages')->with('user')->latest();
        if (!$isAdmin || $scope !== 'all') {
            // non-admins always only see their own; admins see all only when scope=all
            $query->where('user_id', $user ? $user->id : 0);
        }

        $convs = $query->paginate(20);
        // If this is an XHR / AJAX request (axios from the frontend), return JSON payload
        if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
            // return paginated data as array; frontend will handle res.data or res.data.data
            return response()->json($convs->toArray());
        }
        return Inertia::render('Bot/AiHistoryIndex', ['conversations' => $convs]);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $conv = AiConversation::with('messages')->findOrFail($id);

        // authorize: owner or admin
        $isAdmin = $user && (
            (method_exists($user, 'isAdmin') && $user->isAdmin()) ||
            (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) ||
            in_array(($user->user_role ?? ''), ['admin', 'superadmin'])
        );
        if ($conv->user_id && $user && $user->id !== $conv->user_id && !$isAdmin) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        return Inertia::render('Bot/AiHistoryShow', ['conversation' => $conv]);
    }

    public function showJson(Request $request, $id)
    {
        $conv = AiConversation::with('messages')->findOrFail($id);
        // sanitize message meta before returning
        $arr = $conv->toArray();
        if (!empty($arr['messages']) && is_array($arr['messages'])) {
            foreach ($arr['messages'] as &$m) {
                $m['meta'] = $this->sanitizeMeta($m['meta'] ?? null, $request);
            }
        }
        return response()->json($arr);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'system_prompt' => 'nullable|string',
            'messages' => 'nullable|array'
        ]);

        $conv = AiConversation::create([
            'user_id' => $request->user() ? $request->user()->id : null,
            'title' => $data['title'] ?? null,
            'system_prompt' => $data['system_prompt'] ?? null,
        ]);

        if (!empty($data['messages'])) {
            foreach ($data['messages'] as $m) {
                $meta = $this->sanitizeMeta($m['meta'] ?? null, $request);
                AiMessage::create([
                    'ai_conversation_id' => $conv->id,
                    'user_id' => $request->user() ? $request->user()->id : null,
                    'role' => $m['role'] ?? 'user',
                    'content' => $m['content'] ?? '',
                    'meta' => $meta,
                ]);
            }
        }

        return response()->json($conv);
    }

    public function update(Request $request, $id)
    {
        $conv = AiConversation::findOrFail($id);
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'system_prompt' => 'nullable|string',
            'messages' => 'nullable|array'
        ]);

        // update title/system_prompt if provided
        if (isset($data['title'])) $conv->title = $data['title'];
        if (isset($data['system_prompt'])) $conv->system_prompt = $data['system_prompt'];
        $conv->save();

        // append any new messages (we'll always append the full list for simplicity)
        if (!empty($data['messages'])) {
            // remove existing messages and recreate from provided list to ensure canonical order
            $conv->messages()->delete();
            foreach ($data['messages'] as $m) {
                $meta = $this->sanitizeMeta($m['meta'] ?? null, $request);
                AiMessage::create([
                    'ai_conversation_id' => $conv->id,
                    'user_id' => $request->user() ? $request->user()->id : null,
                    'role' => $m['role'] ?? 'user',
                    'content' => $m['content'] ?? '',
                    'meta' => $meta,
                ]);
            }
        }

        return response()->json($conv->fresh());
    }

    /**
     * Delete a conversation and its related messages and attachments.
     */
    public function destroy(Request $request, $id)
    {
        $conv = AiConversation::with('messages')->find($id);
        if (!$conv) return response()->json(['error' => 'not found'], 404);

        // Authorization: only owner or admin can delete (basic check)
        $user = $request->user();
        if ($user && $conv->user_id && $user->id !== $conv->user_id) {
            // project uses user_role / isAdmin() / isSuperAdmin helper - allow superadmin as well
            $isAdmin = (
                (method_exists($user, 'isAdmin') && $user->isAdmin()) ||
                (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) ||
                in_array(($user->user_role ?? ''), ['admin', 'superadmin'])
            );
            if (!$isAdmin) {
                return response()->json(['error' => 'forbidden'], 403);
            }
        }

        // Collect attachments referenced in messages (meta.file.path) and via attachmentables pivot
        $attachmentService = new \App\Services\AttachmentService();
        $attachmentIds = [];
        try {
            foreach ($conv->messages as $m) {
                if (!empty($m->meta) && is_array($m->meta) && !empty($m->meta['file'])) {
                    $file = $m->meta['file'];
                    if (!empty($file['attachment_id'])) $attachmentIds[] = $file['attachment_id'];
                }
            }
        } catch (\Throwable $_e) {
            // ignore
        }

        // Also find attachments linked via attachmentables pivot to AiConversation
        try {
            $pivotRows = DB::table('attachmentables')
                ->where('attachable_type', \App\Models\AiConversation::class)
                ->where('attachable_id', $conv->id)
                ->pluck('attachment_id')
                ->toArray();
            foreach ($pivotRows as $pid) $attachmentIds[] = $pid;
        } catch (\Throwable $_e) {
            // ignore
        }

        // Unique ids
        $attachmentIds = array_values(array_filter(array_unique($attachmentIds)));

        DB::beginTransaction();
        try {
            // delete messages
            $conv->messages()->delete();
            // delete pivot rows for this conversation
            DB::table('attachmentables')->where('attachable_type', \App\Models\AiConversation::class)->where('attachable_id', $conv->id)->delete();
            // delete conversation
            $conv->delete();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => 'delete failed', 'message' => $e->getMessage()], 500);
        }

        // Cleanup attachments after transaction (use service which will remove storage and any remaining pivots)
        foreach ($attachmentIds as $aid) {
            try {
                $att = \App\Models\Attachment::find($aid);
                if ($att) $attachmentService->deleteAttachment($att);
            } catch (\Throwable $_e) {
                // log and continue
                logger()->warning('AiHistoryController::destroy cleanup attach failed: ' . $_e->getMessage());
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * Sanitize incoming message meta before saving to DB.
     * Ensures any file.url or file.path uses allowed schemes/locations.
     */
    protected function sanitizeMeta($meta, Request $request)
    {
        if (!$meta || !is_array($meta)) return null;
        // only consider 'file' key for now
        if (isset($meta['file']) && is_array($meta['file'])) {
            $file = $this->sanitizeFileMeta($meta['file']);
            if ($file) return ['file' => $file];
        }
        return null;
    }

    protected function sanitizeFileMeta(array $fileMeta)
    {
        $out = [];
        // allow original_name and mime and size
        if (isset($fileMeta['original_name'])) $out['original_name'] = substr($fileMeta['original_name'], 0, 255);
        if (isset($fileMeta['mime'])) $out['mime'] = substr($fileMeta['mime'], 0, 100);
        if (isset($fileMeta['size'])) $out['size'] = intval($fileMeta['size']);

        // prefer internal path if present and safe
        if (!empty($fileMeta['path'])) {
            $p = ltrim($fileMeta['path'], '\/');
            // only allow paths inside 'bot/' or 'chat/' storage prefixes
            if (Str::startsWith($p, ['bot/', 'chat/', 'attachments/'])) {
                $out['path'] = $p;
                $out['url'] = Storage::url($p);
                return $out;
            }
        }

        // otherwise, allow only http/https URLs that point to our domain or are explicitly allowed hosts
        if (!empty($fileMeta['url']) && filter_var($fileMeta['url'], FILTER_VALIDATE_URL)) {
            try {
                $u = $fileMeta['url'];
                $host = parse_url($u, PHP_URL_HOST);
                // allow if host is empty (relative) or matches our app url host
                $appHost = parse_url(config('app.url') ?? URL::to('/'), PHP_URL_HOST);
                if ($host === $appHost || $host === null) {
                    $out['url'] = $u;
                    return $out;
                }
            } catch (\Exception $e) {
                // fallthrough
            }
        }

        // nothing safe found
        return null;
    }
}
