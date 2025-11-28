<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Message;
use App\Models\MessageRecipient;
use App\Events\MessageCreated;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\HtmlSanitizer;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $folder = $request->query('folder', 'inbox');
        // Server-side sorting: accept sort_by (subject|from|attachments|time) and sort_dir (asc|desc)
        $sortBy = $request->query('sort_by');
        $sortDir = strtolower($request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        // build base query and ensure attachments count is available for ordering
        if ($folder === 'sent') {
            // For sent folder, show messages authored by the user but exclude those
            // the sender has already moved to trash (we represent per-sender trash
            // using a MessageRecipient row with deleted_at set for the sender).
            $query = Message::where('from_user_id', $user->id)
                ->whereDoesntHave('recipients', function ($q) use ($user) {
                    $q->where('user_id', $user->id)->whereNotNull('deleted_at');
                })
                ->with('recipients.user')
                ->withCount('attachments');
        } elseif ($folder === 'trash') {
            // messages where current user's recipient row has deleted_at set
            $query = Message::whereHas('recipients', function ($q) use ($user) {
                $q->where('user_id', $user->id)->whereNotNull('deleted_at');
            })->with('fromUser')->withCount('attachments');
        } else {
            // inbox: only recipient rows that are not deleted
            $query = Message::whereHas('recipients', function ($q) use ($user) {
                $q->where('user_id', $user->id)->whereNull('deleted_at');
            })->with('fromUser')->withCount('attachments');
        }

        // apply safe ordering based on allowed fields
        if ($sortBy) {
            switch ($sortBy) {
                case 'subject':
                    $query->orderBy('subject', $sortDir);
                    break;
                case 'attachments':
                    $query->orderBy('attachments_count', $sortDir);
                    break;
                case 'time':
                    // order by sent_at then created_at as fallback
                    $query->orderBy('sent_at', $sortDir)->orderBy('created_at', $sortDir);
                    break;
                case 'from':
                    // join users table to sort by sender name (safe: select messages.* to keep models)
                    $query = $query->leftJoin('users', 'users.id', '=', 'messages.from_user_id')->select('messages.*')->orderBy('users.name', $sortDir);
                    break;
                default:
                    $query->orderByDesc('created_at');
            }
        } else {
            $query->orderByDesc('created_at');
        }

        // paginate and keep query string so pagination links preserve sort params
        $messages = $query->paginate(20)->appends($request->query());

        return Inertia::render('Messages/Index', [
            'messages' => $messages,
            'folder' => $folder,
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
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

        // load attachments for this message (if any) and expose public URLs when ready
        $message->load('attachments');
        // Use AttachmentService to normalize attachment meta so all UIs get consistent fields
        try {
            $svc = new \App\Services\AttachmentService();
            $message->attachments = $message->attachments->map(function ($att) use ($svc) {
                $meta = $svc->formatResponseMeta([
                    'path' => $att->path,
                    'url' => null,
                    'original_name' => $att->original_name,
                    'mime' => $att->mime_type,
                    'size' => $att->size,
                    'attachment_id' => $att->id,
                ]);
                // back-compat: expose public storage URL as public_url
                $public = null;
                if (!empty($meta['path'])) {
                    $public = asset('storage/' . ltrim($meta['path'], '/'));
                }
                return array_merge([
                    'id' => $att->id,
                    'original_name' => $att->original_name,
                    'mime_type' => $att->mime_type,
                    'size' => $att->size,
                    'status' => $att->status,
                    'path' => $att->path,
                ], $meta, ['public_url' => $public]);
            })->values();
        } catch (\Throwable $__e) {
            // fallback to previous behavior on error
            $message->attachments = $message->attachments->map(function ($att) {
                $url = null;
                $public = null;
                if ($att->status === 'ready' && $att->path) {
                    // prefer web-stream route so browser sessions authenticate correctly
                    $url = route('attachments.stream', ['path' => $att->path]);
                    $public = asset('storage/' . ltrim($att->path, '/'));
                }
                return [
                    'id' => $att->id,
                    'original_name' => $att->original_name,
                    'mime_type' => $att->mime_type,
                    'size' => $att->size,
                    'status' => $att->status,
                    'url' => $url,
                    'public_url' => $public,
                    'path' => $att->path,
                ];
            })->values();
        }

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

        // Debug log: record that show() was invoked and include minimal message payload
        try {
            Log::info('MessageController@show invoked', [
                'id' => $message->id,
                'subject' => $message->subject,
                'attachments_count' => $message->attachments->count(),
            ]);
        } catch (\Throwable $__e) {
            // ignore logging failures
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
            'save_as' => 'nullable|string',
        ]);

        // server-side sanitize body using centralized HtmlSanitizer service
        $sanitizer = app(HtmlSanitizer::class);
        $body = $sanitizer->purify($data['body'] ?? null);

        $isDraft = isset($data['save_as']) && $data['save_as'] === 'draft';
        $message = Message::create([
            'from_user_id' => $request->user()->id,
            'subject' => $data['subject'] ?? null,
            'body' => $body,
            'status' => $isDraft ? 'draft' : 'sent',
            'sent_at' => $isDraft ? null : now(),
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
                try {
                    $att = \App\Models\Attachment::find($attId);
                    if ($att) {
                        // Prefer polymorphic pivot attach; attachTo returns bool
                        $ok = $att->attachTo($message);
                        if (! $ok) {
                            // Do NOT write the legacy attachments.message_id column here.
                            // The system should rely on the attachmentables pivot and the migration
                            // tooling to create any missing links. Log for later inspection.
                            logger()->warning('MessageController: attachTo returned false; attachment not linked via pivot', ['att_id' => $attId, 'message_id' => $message->id]);
                        }
                    } else {
                        logger()->warning('MessageController: attachment id not found', ['att_id' => $attId]);
                    }
                } catch (\Throwable $__e) {
                    logger()->warning('MessageController: failed to attach attachment to message', ['att_id' => $attId, 'message_id' => $message->id, 'error' => $__e->getMessage()]);
                }
            }
        }

        // Broadcast to recipients for real-time notification
        try {
            $message->load('recipients');
            event(new MessageCreated($message));
        } catch (\Throwable $e) {
            // non-fatal
        }

        return redirect()->route('messages.index', ['folder' => $isDraft ? 'drafts' : 'sent']);
    }

    /**
     * Move a message to the current user's Trash (per-recipient soft-delete).
     */
    public function trash(Request $request, Message $message)
    {
        $user = $request->user();
        if (!$user) return response()->json(['error' => 'unauthenticated'], 401);

        // Ensure the user is a recipient of the message. If the user is the
        // sender, allow a per-sender trash by creating a MessageRecipient row
        // for them (type 'to' is used for sender copy so it fits the enum) and
        // marking it deleted. This keeps the per-recipient semantics without
        // removing the message for other recipients.
        $mr = MessageRecipient::where('message_id', $message->id)->where('user_id', $user->id)->first();

        if (!$mr) {
            // If the current user is the author/sender, create a recipient row
            // to represent the sender's mailbox copy and mark it deleted.
            if ($message->from_user_id === $user->id) {
                try {
                    $mr = MessageRecipient::create([
                        'message_id' => $message->id,
                        'user_id' => $user->id,
                        'type' => 'to',
                        'deleted_at' => now(),
                    ]);
                } catch (\Throwable $__e) {
                    // creation failed for some reason
                    return response()->json(['error' => 'not_found'], 404);
                }
                return response()->json(['ok' => true]);
            }
            return response()->json(['error' => 'not_found'], 404);
        }

        $mr->deleted_at = now();
        $mr->save();

        return response()->json(['ok' => true]);
    }

    /**
     * Permanently remove the message for the current user.
     * If there are no remaining recipients after removal, also remove the message
     * and any attached files via AttachmentService.
     */
    public function destroy(Request $request, Message $message)
    {
        $user = $request->user();
        if (!$user) return response()->json(['error' => 'unauthenticated'], 401);

        $mr = MessageRecipient::where('message_id', $message->id)->where('user_id', $user->id)->first();
        if (!$mr) {
            // No recipient row found for user: nothing to permanently delete for them
            return response()->json(['error' => 'not_found'], 404);
        }

        // Only allow permanent deletion when the message is already in the user's trash
        if ($mr->deleted_at === null) {
            return response()->json(['error' => 'not_in_trash'], 403);
        }

        // remove the recipient row permanently
        try {
            $mr->delete();
        } catch (\Throwable $e) {
            return response()->json(['error' => 'could_not_delete_recipient'], 500);
        }

        // If no recipients remain, delete the message and attachments to free storage
        try {
            $remaining = $message->recipients()->count();
            if ($remaining === 0) {
                // load attachments and delete via AttachmentService for safe cleanup
                $message->load('attachments');
                $svc = new \App\Services\AttachmentService();
                foreach ($message->attachments ?? [] as $att) {
                    try {
                        // First, remove the pivot linking this attachment to the message.
                        try {
                            \Illuminate\Support\Facades\DB::table('attachmentables')
                                ->where('attachment_id', $att->id)
                                ->where('attachable_type', \App\Models\Message::class)
                                ->where('attachable_id', $message->id)
                                ->delete();
                        } catch (\Throwable $_pivotEx) {
                            \Illuminate\Support\Facades\Log::warning('Message destroy: failed to remove pivot for attachment', ['attachment_id' => $att->id ?? null, 'message_id' => $message->id, 'error' => $_pivotEx->getMessage()]);
                        }

                        // If the attachment is still referenced by other attachables, do not delete files/db row.
                        $stillReferenced = false;
                        try {
                            $stillReferenced = (bool) \Illuminate\Support\Facades\DB::table('attachmentables')->where('attachment_id', $att->id)->exists();
                        } catch (\Throwable $_existsEx) {
                            // on error, err on the side of safety and assume it is still referenced
                            $stillReferenced = true;
                        }

                        if ($stillReferenced) {
                            \Illuminate\Support\Facades\Log::info('Message destroy: attachment left in place because it is referenced elsewhere', ['attachment_id' => $att->id ?? null, 'message_id' => $message->id]);
                            continue;
                        }

                        // No other references — safe to delete fully
                        $svc->deleteAttachment($att, $user);
                    } catch (\Throwable $__e) {
                        // continue deleting others
                        \Illuminate\Support\Facades\Log::warning('Message destroy: failed to delete attachment', ['attachment_id' => $att->id ?? null, 'error' => $__e->getMessage()]);
                    }
                }
                try {
                    $message->delete();
                } catch (\Throwable $__e) {
                    \Illuminate\Support\Facades\Log::warning('Message destroy: failed to delete message', ['message_id' => $message->id, 'error' => $__e->getMessage()]);
                }
            }
        } catch (\Throwable $e) {
            // log and continue
            \Illuminate\Support\Facades\Log::warning('Message destroy: cleanup failed', ['message_id' => $message->id, 'error' => $e->getMessage()]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Accept a job request using the messages-based flow.
     * This updates the JobRequest status and linked assignment (if present).
     */
    public function acceptJobRequest(Request $request, \App\Models\JobRequest $jobRequest)
    {
        $this->authorize('update', $jobRequest);

        if ($jobRequest->status === 'accepted') {
            return back();
        }

        $jobRequest->status = 'accepted';
        $jobRequest->accepted_at = now();
        $jobRequest->save();

        if ($jobRequest->project_job_assignment_id) {
            $assignment = \App\Models\ProjectJobAssignment::find($jobRequest->project_job_assignment_id);
            if ($assignment) {
                $assignment->accepted = true;
                $assignment->save();
            }
        }

        // Optionally, mark any MessageRecipient rows that reference this job request as read.
        try {
            // If a message was created for this job request and linked via attachments or metadata,
            // this code is a no-op unless application logic populates such links.
        } catch (\Throwable $__e) {
            // ignore
        }

        try {
            event(new \App\Events\JobRequestAccepted($jobRequest));
        } catch (\Throwable $e) {
            logger()->warning('JobRequestAccepted broadcast failed: ' . $e->getMessage());
        }

        // Redirect user to messages index to reflect messages-based flow
        return redirect()->route('messages.index');
    }
}
