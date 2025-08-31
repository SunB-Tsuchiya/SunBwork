<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Message;
use App\Models\MessageRecipient;
use App\Events\MessageCreated;
use Illuminate\Support\Facades\Auth;
use App\Services\HtmlSanitizer;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $folder = $request->query('folder', 'inbox');

        if ($folder === 'sent') {
            $messages = Message::where('from_user_id', $user->id)->with('recipients.user')->orderByDesc('created_at')->paginate(20);
        } else {
            $messages = Message::whereHas('recipients', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })->with('fromUser')->orderByDesc('created_at')->paginate(20);
        }

        return Inertia::render('Messages/Index', [
            'messages' => $messages,
            'folder' => $folder,
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Messages/Create', [
            'users' => [],
        ]);
    }

    public function show(Request $request, Message $message)
    {
        $this->authorize('view', $message);
        $message->load('fromUser', 'recipients.user');

        // mark as read for current user
        $user = $request->user();
        if ($user) {
            $updated = \App\Models\MessageRecipient::where('message_id', $message->id)->where('user_id', $user->id)->whereNull('read_at')->update(['read_at' => now()]);
            if ($updated) {
                try {
                    event(new \App\Events\MessageRead($message, $user->id));
                } catch (\Throwable $__e) {
                }
            }
        }

        return Inertia::render('Messages/Show', [
            'message' => $message,
        ]);
    }

    public function markRead(Request $request, Message $message)
    {
        $user = $request->user();
        if (!$user) return response()->json(['error' => 'unauthenticated'], 401);
        $updated = \App\Models\MessageRecipient::where('message_id', $message->id)->where('user_id', $user->id)->whereNull('read_at')->update(['read_at' => now()]);
        if ($updated) {
            try {
                event(new \App\Events\MessageRead($message, $user->id));
            } catch (\Throwable $__e) {
            }
        }
        return response()->json(['ok' => true]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'to' => 'required|array',
            'to.*' => 'integer|exists:users,id',
            'cc' => 'nullable|array',
            'cc.*' => 'integer|exists:users,id',
            'bcc' => 'nullable|array',
            'bcc.*' => 'integer|exists:users,id',
            'attachments' => 'nullable|array',
        ]);

    // server-side sanitize body using centralized HtmlSanitizer service
    $sanitizer = app(HtmlSanitizer::class);
    $body = $sanitizer->purify($data['body'] ?? null);

        $message = Message::create([
            'from_user_id' => $request->user()->id,
            'subject' => $data['subject'] ?? null,
            'body' => $body,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $createRecipient = function ($userId, $type) use ($message) {
            MessageRecipient::create([
                'message_id' => $message->id,
                'user_id' => $userId,
                'type' => $type,
            ]);
        };

        foreach ($data['to'] as $uid) {
            $createRecipient($uid, 'to');
        }
        foreach ($data['cc'] ?? [] as $uid) {
            $createRecipient($uid, 'cc');
        }
        foreach ($data['bcc'] ?? [] as $uid) {
            $createRecipient($uid, 'bcc');
        }

        // attach uploaded attachments (client uploads to /api/uploads and returns attachment ids)
        if (!empty($data['attachments'])) {
            foreach ($data['attachments'] as $attId) {
                \App\Models\Attachment::where('id', $attId)->update(['message_id' => $message->id]);
            }
        }

        // Broadcast to recipients for real-time notification
        try {
            $message->load('recipients');
            event(new MessageCreated($message));
        } catch (\Throwable $e) {
            // non-fatal
        }

        return redirect()->route('messages.index', ['folder' => 'sent']);
    }
}
