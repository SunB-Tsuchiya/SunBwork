<?php

namespace App\Http\Controllers\ProjectJobs;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectJobAssignmentUserController extends Controller
{
    public function create(Request $request)
    {
        $jobId = $request->query('job');
        $user = $request->user();

        // If no prefill params, keep existing behaviour and redirect to the job create flow
        if (!$jobId && !$request->query('sender_id') && !$request->query('desired_end_date') && !$request->query('desired_time')) {
            return redirect()->route('events.create_job');
        }

        // Build userProjects / userClients similar to EventController::createJob
        $userClients = [];
        $userProjects = [];
        try {
            $ptms = \App\Models\ProjectTeamMember::with(['projectJob.client'])
                ->where('user_id', $user->id)
                ->get();
            $jobs = $ptms->map(function ($ptm) {
                return $ptm->projectJob;
            })->filter();

            $userProjects = $jobs->map(function ($job) {
                return [
                    'id' => $job->id,
                    'title' => $job->title ?? ($job->name ?? null),
                    'client_id' => $job->client ? $job->client->id : null,
                ];
            })->values();

            $clients = $jobs->map(function ($job) {
                return $job->client;
            })->filter()->unique('id')->values();

            $userClients = $clients->map(function ($c) {
                return ['id' => $c->id, 'name' => $c->name ?? ($c->client_name ?? null)];
            })->values();
        } catch (\Throwable $__e) {
            $userClients = [];
            $userProjects = [];
        }

        // minimal members list (current user)
        $members = [];
        try {
            if ($user) $members = [['id' => $user->id, 'name' => $user->name]];
        } catch (\Throwable $__e) {
            $members = [];
        }

        $company = null;
        $department = null;
        try {
            if ($user && isset($user->company_id) && $user->company_id) {
                $company = \App\Models\Company::find($user->company_id);
            }
            if ($user && isset($user->department_id) && $user->department_id) {
                $department = \App\Models\Department::find($user->department_id);
            }
        } catch (\Throwable $__e) {
            $company = null;
            $department = null;
        }

        // lookup lists
        $types = [];
        $sizes = [];
        $stages = [];
        $statuses = [];
        try {
            $types = \App\Models\WorkItemType::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'company_id', 'department_id']);
            $sizes = \App\Models\Size::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'width', 'height', 'unit', 'company_id', 'department_id']);
            $stages = \App\Models\Stage::orderBy('sort_order')->orderBy('order_index')->get(['id', 'name', 'company_id', 'department_id']);
            $statuses = \App\Models\Status::orderBy('sort_order')->get(['id', 'name', 'slug', 'company_id', 'department_id']);
        } catch (\Throwable $__e) {
            $types = [];
            $sizes = [];
            $stages = [];
            $statuses = [];
        }

        // Build a single prefill assignment object for the form using query params and optional job source
        $prefill = [
            'project_job_id' => $request->query('projectJob') ?: null,
            'sender_id' => $request->query('sender_id') ?: ($user ? $user->id : null),
            'desired_end_date' => $request->query('desired_end_date') ?: null,
            'desired_time' => $request->query('desired_time') ?: null,
            'estimated_hours' => $request->query('estimated_hours') ?: null,
        ];

        $props = [
            'projectJob' => null,
            'userClients' => $userClients,
            'userProjects' => $userProjects,
            'members' => $members,
            'company' => $company,
            'department' => $department,
            'types' => $types,
            'sizes' => $sizes,
            'stages' => $stages,
            'statuses' => $statuses,
            // supply assignments array so AssignmentForm_user will prefill
            'assignments' => [$prefill],
        ];

        return Inertia::render('JobBox/create_user', $props);
    }

    public function edit(Request $request)
    {
        $user = $request->user();
        $jobId = $request->query('job');
        if (!$jobId) {
            return redirect()->route('events.create');
        }

        // load lookup lists
        try {
            $types = \App\Models\WorkItemType::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'company_id', 'department_id']);
            $sizes = \App\Models\Size::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'width', 'height', 'unit', 'company_id', 'department_id']);
            $stages = \App\Models\Stage::orderBy('sort_order')->orderBy('order_index')->get(['id', 'name', 'company_id', 'department_id']);
            $statuses = \App\Models\Status::orderBy('sort_order')->get(['id', 'name', 'slug', 'company_id', 'department_id']);
        } catch (\Throwable $__e) {
            $types = [];
            $sizes = [];
            $stages = [];
            $statuses = [];
        }

        // Attempt to find the existing ProjectJobAssignment to use as prefill
        $assignment = \App\Models\ProjectJobAssignment::with(['projectJob.client', 'user', 'size', 'stage', 'workItemType', 'statusModel'])->find($jobId);
        if (!$assignment) {
            // fallback to events.create when not found
            return redirect()->route('events.create', ['job' => $request->query('job')]);
        }

        $prefill = $assignment->toEventPrefill();

        $props = [
            'projectJob' => $assignment->projectJob ?? null,
            'userClients' => [],
            'userProjects' => [],
            'members' => auth()->user() ? [] : [],
            'company' => null,
            'department' => null,
            'types' => $types,
            'sizes' => $sizes,
            'stages' => $stages,
            'statuses' => $statuses,
            // supply assignments array so AssignmentForm_user will prefill
            'assignments' => [$prefill],
        ];

        return Inertia::render('MyJobBox/Edit_user', $props);
    }
}
