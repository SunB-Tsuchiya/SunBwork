<?php

namespace App\Http\Controllers;

use App\Models\JobRequest;
use App\Models\ProjectJobAssignment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Events\JobRequestCreated;

class JobRequestsController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $requests = JobRequest::where('to_user_id', $user->id)
            ->with(['fromUser', 'assignment.projectJob'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('JobRequests/Index', [
            'requests' => $requests,
        ]);
    }

    public function show(JobRequest $jobRequest)
    {
        $this->authorize('view', $jobRequest);
        $jobRequest->load(['fromUser', 'assignment.projectJob']);
        return Inertia::render('JobRequests/Show', [
            'request' => $jobRequest,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_job_id' => 'nullable|exists:project_jobs,id',
            'project_job_assignment_id' => 'nullable|exists:project_job_assignments,id',
            'to_user_id' => 'required|exists:users,id',
            'message' => 'nullable|string',
        ]);

        $data['from_user_id'] = Auth::id();
        $r = JobRequest::create($data + ['status' => 'sent']);

        // Broadcast real-time notification to recipient
        try {
            event(new JobRequestCreated($r));
        } catch (\Throwable $e) {
            // don't fail the request if broadcasting fails
            logger()->warning('JobRequestCreated broadcast failed: ' . $e->getMessage());
        }

        return redirect()->route('job_requests.index');
    }

    public function accept(JobRequest $jobRequest)
    {
        $this->authorize('update', $jobRequest);

        if ($jobRequest->status === 'accepted') {
            return back();
        }

        $jobRequest->status = 'accepted';
        $jobRequest->accepted_at = now();
        $jobRequest->save();

        // Mark linked assignment accepted if present
        if ($jobRequest->project_job_assignment_id) {
            $assignment = ProjectJobAssignment::find($jobRequest->project_job_assignment_id);
            if ($assignment) {
                $assignment->accepted = true;
                $assignment->save();
            }
        }

        try {
            event(new \App\Events\JobRequestAccepted($jobRequest));
        } catch (\Throwable $e) {
            logger()->warning('JobRequestAccepted broadcast failed: ' . $e->getMessage());
        }

        return redirect()->route('job_requests.show', $jobRequest->id);
    }
}
