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
            'subject' => 'nullable|string|max:255',
            'body' => 'nullable|string',
        ]);

        $msg = JobAssignmentMessage::create([
            'project_job_assignment_id' => $data['project_job_assignment_id'],
            'sender_id' => $request->user()->id,
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'] ?? null,
        ]);

        // TODO: reuse Messages notification/email sending here if needed.

        return back()->with('success', 'JobBox message sent');
    }
}
