<?php

namespace App\Http\Controllers\ProjectJobs;

use App\Http\Controllers\Controller;
use App\Models\ProjectJob;
use App\Models\JobAssignmentMessage;
use Illuminate\Http\Request;

class JobBoxController extends Controller
{
    public function index(ProjectJob $projectJob, Request $request)
    {
        $q = $request->input('q');

        $messages = JobAssignmentMessage::whereHas('projectJobAssignment', function ($qry) use ($projectJob) {
            $qry->where('project_job_id', $projectJob->id);
        })
        ->with('sender')
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

    public function show(ProjectJob $projectJob, JobAssignmentMessage $message)
    {
        // mark read
        if (! $message->read_at) {
            $message->read_at = now();
            $message->save();
        }

        $message->load('sender');

        return inertia('JobBox/Show', [
            'projectJob' => $projectJob,
            'message' => $message,
        ]);
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

        // Create JobAssignmentMessage record
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

        // Broadcast event for real-time notifications
        try {
            $message->load('recipients');
            event(new \App\Events\MessageCreated($message));
        } catch (\Throwable $__e) {
            // non-fatal
        }

        return redirect()->route('project_jobs.jobbox.index', ['projectJob' => $projectJob->id])->with('success', 'JobBox: メッセージを送信しました');
    }
}
