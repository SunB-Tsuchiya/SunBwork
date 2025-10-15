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
            $query = Message::where('from_user_id', $user->id)->with('recipients.user')->withCount('attachments');
        } else {
            $query = Message::whereHas('recipients', function ($q) use ($user) {
                $q->where('user_id', $user->id);
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
        $message->attachments = $message->attachments->map(function ($att) {
            $url = null;
            $public = null;
            if ($att->status === 'ready' && $att->path) {
                $url = route('api.attachments.stream', ['path' => $att->path]);
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
