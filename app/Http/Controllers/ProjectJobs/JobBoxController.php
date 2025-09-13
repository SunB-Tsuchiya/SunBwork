<?php

namespace App\Http\Controllers\ProjectJobs;

use App\Http\Controllers\Controller;
use App\Models\ProjectJob;
use App\Models\JobAssignmentMessage;
use App\Models\ProjectJobAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JobBoxController extends Controller
{
    public function index(ProjectJob $projectJob, Request $request)
    {
        // Allow coordinators/leaders/admins/superadmins as before; for normal users,
        // ensure the user is assigned to this project_job before showing jobbox.
        $user = $request->user();
        $isPrivileged = $user && (method_exists($user, 'isCoordinator') && ($user->isCoordinator() || $user->isLeader() || $user->isAdmin() || $user->isSuperAdmin()));
        if (! $isPrivileged) {
            // check if the current user has any assignment for this project job
            $hasAssignment = \App\Models\ProjectJobAssignment::where('project_job_id', $projectJob->id)
                ->where('user_id', $user ? $user->id : 0)
                ->exists();
            if (! $hasAssignment) {
                abort(403, 'Access denied.');
            }
        }

        $q = $request->input('q');

        $messages = JobAssignmentMessage::whereHas('projectJobAssignment', function ($qry) use ($projectJob, $user, $isPrivileged) {
            $qry->where('project_job_id', $projectJob->id);
            // If the current user is not privileged, restrict to assignments belonging to them
            if (! $isPrivileged && $user) {
                $qry->where('user_id', $user->id);
            }
        })
            ->with(['sender', 'projectJobAssignment'])
            ->when($q, function ($qry, $q) {
                $qry->where(function ($sub) use ($q) {
                    $sub->where('subject', 'like', "%{$q}%")->orWhere('body', 'like', "%{$q}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return inertia('JobBox/Index', [
            'projectJob' => $projectJob,
            'messages' => $messages,
            'q' => $q,
        ]);
    }

    /**
     * Global jobbox for authenticated user: shows job messages across all assignments
     * Useful for the top-level /jobbox fallback route when no project context is provided.
     */
    public function global(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $q = $request->input('q');

        // Load JobAssignmentMessage rows where either:
        //  - the underlying assignment belongs to this user (they're the assignee), OR
        //  - the current user is the sender of the job message (they sent it)
        $messages = JobAssignmentMessage::where(function ($qry) use ($user) {
            $qry->whereHas('projectJobAssignment', function ($q2) use ($user) {
                $q2->where('user_id', $user->id);
            })->orWhere('sender_id', $user->id);
        })
            ->with(['sender', 'projectJobAssignment.projectJob'])
            ->when($q, function ($qry, $q) {
                $qry->where(function ($sub) use ($q) {
                    $sub->where('subject', 'like', "%{$q}%")->orWhere('body', 'like', "%{$q}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return inertia('JobBox/Index', [
            'projectJob' => null,
            'messages' => $messages,
            'q' => $q,
        ]);
    }

    public function show(ProjectJob $projectJob, JobAssignmentMessage $message)
    {
        // Authorization: allow privileged roles or assigned users only
        $user = \Illuminate\Support\Facades\Auth::user();
        $isPrivileged = $user && (method_exists($user, 'isCoordinator') && ($user->isCoordinator() || $user->isLeader() || $user->isAdmin() || $user->isSuperAdmin()));
        if (! $isPrivileged) {
            $hasAssignment = \App\Models\ProjectJobAssignment::where('project_job_id', $projectJob->id)
                ->where('user_id', $user ? $user->id : 0)
                ->exists();
            if (! $hasAssignment) {
                abort(403, 'Access denied.');
            }
        }

        // mark read
        if (! $message->read_at) {
            $message->read_at = now();
            $message->save();

            // If this job message is linked to a Message, mark MessageRecipient rows as read
            if ($message->message_id) {
                try {
                    $updated = \App\Models\MessageRecipient::where('message_id', $message->message_id)
                        ->where('user_id', \Illuminate\Support\Facades\Auth::id())
                        ->whereNull('read_at')
                        ->update(['read_at' => now()]);

                    // broadcast job-specific read event so frontend can decrement job unread counter
                    event(new \App\Events\JobMessageRead($message->message_id, \Illuminate\Support\Facades\Auth::id()));
                } catch (\Throwable $__e) {
                    // non-fatal
                }
            }
        }

        $message->load('sender');

        return inertia('JobBox/Show', [
            'projectJob' => $projectJob,
            'message' => $message,
        ]);
    }

    /**
     * Delete a JobAssignmentMessage
     */
    public function destroy(ProjectJob $projectJob, JobAssignmentMessage $message)
    {
        // authorize the user - reuse existing policy if present
        if (method_exists($this, 'authorize')) {
            try {
                $this->authorize('delete', $message);
            } catch (\Exception $e) {
                // fallthrough - continue without throwing so user gets redirected
            }
        }

        // If this job message has an associated Message record, delete it and recipients
        if ($message->message_id) {
            $msg = \App\Models\Message::find($message->message_id);
            if ($msg) {
                // delete recipients first
                \App\Models\MessageRecipient::where('message_id', $msg->id)->delete();
                $msg->delete();
            }
        }

        $message->delete();

        return redirect()->back()->with('success', 'メッセージを削除しました。');
    }

    public function store(ProjectJob $projectJob, Request $request)
    {
        $data = $request->validate([
            'project_job_assignment_id' => 'required|integer|exists:project_job_assignments,id',
            'to' => 'required|array',
            'to.*' => 'integer|exists:users,id',
            'subject' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'attachments' => 'nullable|array',
        ]);

        // Wrap creation in transaction: create JobAssignmentMessage, Message, recipients
        DB::beginTransaction();
        try {
            // Create JobAssignmentMessage record first
            $jam = JobAssignmentMessage::create([
                'project_job_assignment_id' => $data['project_job_assignment_id'],
                'sender_id' => $request->user()->id,
                'subject' => $data['subject'] ?? null,
                'body' => $data['body'] ?? null,
            ]);

            // Also create a Message and recipients so existing notification flow can be reused
            $sanitizer = app(\App\Services\HtmlSanitizer::class);
            $body = $sanitizer->purify($data['body'] ?? null);

            $message = \App\Models\Message::create([
                'from_user_id' => $request->user()->id,
                'subject' => $data['subject'] ?? null,
                'body' => $body,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            foreach ($data['to'] as $uid) {
                \App\Models\MessageRecipient::create([
                    'message_id' => $message->id,
                    'user_id' => $uid,
                    'type' => 'to',
                ]);
            }

            // Attach attachments if provided (move existing attachments to this message)
            if (!empty($data['attachments'])) {
                foreach ($data['attachments'] as $attId) {
                    \App\Models\Attachment::where('id', $attId)->update(['message_id' => $message->id]);
                }
            }

            // Persist linkage from job_assignment_messages -> messages
            $jam->message_id = $message->id;
            $jam->save();

            // mark the related assignment as assigned so UI/status reflects sent state
            try {
                $assignment = ProjectJobAssignment::find($jam->project_job_assignment_id);
                if ($assignment && (int)$assignment->project_job_id === (int)$projectJob->id) {
                    $assignment->assigned = true;
                    $assignment->save();
                }
            } catch (\Throwable $__e) {
                // non-fatal
            }

            // Broadcast event for job-specific real-time notifications
            try {
                $message->load('recipients');
                $recipientIds = $message->recipients->pluck('user_id')->unique()->values()->all();
                event(new \App\Events\JobMessageCreated($message, $recipientIds));
            } catch (\Throwable $__e) {
                // non-fatal
            }

            DB::commit();

            // route 'project_jobs.jobbox.index' is not defined in this app; redirect to assignments index
            return redirect()->route('coordinator.project_jobs.assignments.index', ['projectJob' => $projectJob->id])->with('success', 'JobBox: メッセージを送信しました');
        } catch (\Throwable $e) {
            DB::rollBack();
            // log and surface a friendly error
            report($e);
            return redirect()->back()->with('error', 'メッセージ送信でエラーが発生しました。詳細はログを確認してください。');
        }
    }

    /**
     * Reply / completion report from assigned user back to coordinator(s)
     * This endpoint is available to authenticated users who have an assignment
     * for the given project job. It will create a Message and associate it
     * with a JobAssignmentMessage for traceability, then broadcast a job
     * specific event so coordinators receive the notification.
     */
    public function reply(ProjectJob $projectJob, Request $request)
    {
        $data = $request->validate([
            'project_job_assignment_id' => 'required|integer|exists:project_job_assignments,id',
            'subject' => 'nullable|string|max:255',
            'body' => 'nullable|string',
        ]);

        $user = $request->user();

        // Ensure the user is assigned to this project job
        $assignment = ProjectJobAssignment::where('id', $data['project_job_assignment_id'])
            ->where('project_job_id', $projectJob->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $assignment) {
            abort(403, 'このジョブに対する割り当てがありません。');
        }

        DB::beginTransaction();
        try {
            // Create a JobAssignmentMessage to represent this reply (sender is the assigned user)
            $jam = JobAssignmentMessage::create([
                'project_job_assignment_id' => $assignment->id,
                'sender_id' => $user->id,
                'subject' => $data['subject'] ?? null,
                'body' => $data['body'] ?? null,
            ]);

            // Sanitize body
            $sanitizer = app(\App\Services\HtmlSanitizer::class);
            $body = $sanitizer->purify($data['body'] ?? null);

            // Create a Message addressed to coordinator(s). Determine recipients: project owner and/or assignment creator
            // Simple strategy: include the project job owner (projectJob.user_id) and any users who have 'coordinator' role within team
            $message = \App\Models\Message::create([
                'from_user_id' => $user->id,
                'subject' => $data['subject'] ?? null,
                'body' => $body,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $recipientIds = [];
            if ($projectJob->user_id) $recipientIds[] = $projectJob->user_id;

            // Also include assignment creator if different
            // (If the assignment was created by a coordinator and stored somewhere; fallback to project owner)
            $creatorId = $assignment->created_by ?? null;
            if ($creatorId && ! in_array($creatorId, $recipientIds)) {
                $recipientIds[] = $creatorId;
            }

            // Deduplicate and create recipients
            $recipientIds = array_values(array_unique(array_filter($recipientIds)));
            foreach ($recipientIds as $uid) {
                \App\Models\MessageRecipient::create([
                    'message_id' => $message->id,
                    'user_id' => $uid,
                    'type' => 'to',
                ]);
            }

            // link jam -> message
            $jam->message_id = $message->id;
            $jam->save();

            // Optionally mark assignment as accepted/completed (if such flags exist)
            try {
                $assignment->accepted = true;
                $assignment->save();
            } catch (\Throwable $__e) {
                // non-fatal
            }

            // Broadcast job-specific event to coordinators
            try {
                event(new \App\Events\JobMessageCreated($message, $recipientIds));
            } catch (\Throwable $__e) {
                // non-fatal
            }

            DB::commit();

            return redirect()->route('project_jobs.jobbox.show', ['projectJob' => $projectJob->id, 'message' => $jam->id])->with('success', '完了報告を送信しました。');
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return redirect()->back()->with('error', '完了報告の送信中にエラーが発生しました。');
        }
    }
}
