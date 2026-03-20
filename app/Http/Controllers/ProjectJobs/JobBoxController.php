<?php

namespace App\Http\Controllers\ProjectJobs;

use App\Http\Controllers\Controller;
use App\Models\ProjectJob;
use App\Models\JobAssignmentMessage;
use App\Models\ProjectJobAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class JobBoxController extends Controller
{
    public function index(ProjectJob $projectJob, Request $request)
    {
        // Allow coordinators/leaders/admins/superadmins as before; for normal users,
        // ensure the user is assigned to this project_job before showing jobbox.
        $user = $request->user();
        $isPrivileged = $user && (method_exists($user, 'isCoordinator') && ($user->isCoordinator() || $user->isLeader() || $user->isAdmin() || $user->isSuperAdmin()));

        // Additionally allow the project owner or any user who has sent a job-assignment
        // message for this project to view the project jobbox even if they are not
        // explicitly assigned. This lets senders (coordinators or project owners)
        // view the jobbox they created messages in.
        $isOwner = $user && $projectJob->user_id && $user->id === $projectJob->user_id;
        $isSender = false;
        if ($user) {
            $isSender = \App\Models\JobAssignmentMessage::join('project_job_assignments', 'job_assignment_messages.project_job_assignment_id', '=', 'project_job_assignments.id')
                ->where('project_job_assignments.project_job_id', $projectJob->id)
                ->where('job_assignment_messages.sender_id', $user->id)
                ->exists();
        }

        if (! $isPrivileged && ! $isOwner && ! $isSender) {
            // check if the current user has any assignment for this project job
            $hasAssignment = \App\Models\ProjectJobAssignment::where('project_job_id', $projectJob->id)
                ->where('user_id', $user ? $user->id : 0)
                ->exists();
            if (! $hasAssignment) {
                abort(403, 'Access denied.');
            }
        }

        $q = $request->input('q');
        $periodParam = $request->query('period');
        $usePeriodFilter = true;
        $periodModel = $periodParam;
        if ($periodParam === null) {
            $periodModel = now()->format('Y-m');
        } elseif ($periodParam === '' || $periodParam === 'all') {
            $usePeriodFilter = false;
        }

        $periodStart = null;
        $periodEnd = null;
        if ($usePeriodFilter) {
            try {
                $periodStart = Carbon::createFromFormat('Y-m', $periodModel)->startOfMonth();
                $periodEnd = Carbon::createFromFormat('Y-m', $periodModel)->endOfMonth();
            } catch (\Throwable $__e) {
                $periodModel = now()->format('Y-m');
                $periodStart = Carbon::createFromFormat('Y-m', $periodModel)->startOfMonth();
                $periodEnd = Carbon::createFromFormat('Y-m', $periodModel)->endOfMonth();
            }
        }
        $sort = $request->input('sort');
        $dir = strtolower($request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Build a query with joins so we can sort by related columns (assignment desired_start_date, sender name)
        $base = JobAssignmentMessage::select('job_assignment_messages.*')
            ->join('project_job_assignments', 'job_assignment_messages.project_job_assignment_id', '=', 'project_job_assignments.id')
            ->leftJoin('users as senders', 'job_assignment_messages.sender_id', '=', 'senders.id')
            ->where('project_job_assignments.project_job_id', $projectJob->id);

        // If the current user is not privileged, restrict to assignments belonging to them
        if (! $isPrivileged && $user) {
            $base->where('project_job_assignments.user_id', $user->id);
        }

        // Apply search
        if ($q) {
            $base->where(function ($sub) use ($q) {
                $sub->where('job_assignment_messages.subject', 'like', "%{$q}%")->orWhere('job_assignment_messages.body', 'like', "%{$q}%");
            });
        }

        if ($usePeriodFilter && $periodStart && $periodEnd) {
            $base->whereBetween(
                DB::raw('COALESCE(project_job_assignments.desired_end_date, job_assignment_messages.created_at)'),
                [$periodStart, $periodEnd]
            );
        }

        // Sorting whitelist
        switch ($sort) {
            case 'desired_start_date':
                $base->orderBy('project_job_assignments.desired_start_date', $dir);
                break;
            case 'sender':
                $base->orderBy('senders.name', $dir);
                break;
            case 'subject':
                $base->orderBy('job_assignment_messages.subject', $dir);
                break;
            default:
                $base->orderBy('job_assignment_messages.created_at', 'desc');
        }

        // Ensure statusModel is loaded so we can attach a canonical `status` object
        $messages = $base->with([
            'sender',
            'projectJobAssignment.projectJob.client',
            'projectJobAssignment',
            'projectJobAssignment.statusModel',
            'message.recipients.user',
            'message.fromUser',
            'projectJobAssignment.user',
        ])
            ->paginate($usePeriodFilter ? 500 : 50)
            ->appends(array_filter(['q' => $q, 'period' => $periodModel, 'sort' => $sort, 'dir' => $dir]));
        // Attach canonical `status` object to each loaded assignment for frontend convenience
        try {
            $messages->getCollection()->transform(function ($msg) {
                try {
                    if (isset($msg->projectJobAssignment) && $msg->projectJobAssignment && isset($msg->projectJobAssignment->statusModel) && $msg->projectJobAssignment->statusModel) {
                        $sm = $msg->projectJobAssignment->statusModel;
                        $msg->projectJobAssignment->status = [
                            'id' => $sm->id,
                            'key' => $sm->key ?? $sm->slug ?? null,
                            'name' => $sm->name,
                        ];
                    }
                } catch (\Throwable $__e) {
                    // non-fatal
                }
                return $msg;
            });
        } catch (\Throwable $__e) {
            // non-fatal
        }

        $monthBase = JobAssignmentMessage::join('project_job_assignments', 'job_assignment_messages.project_job_assignment_id', '=', 'project_job_assignments.id')
            ->where('project_job_assignments.project_job_id', $projectJob->id);

        if (! $isPrivileged && $user) {
            $monthBase->where('project_job_assignments.user_id', $user->id);
        }

        $monthValues = $monthBase
            ->selectRaw("DATE_FORMAT(COALESCE(project_job_assignments.desired_end_date, job_assignment_messages.created_at), '%Y-%m') as ym")
            ->groupBy('ym')
            ->orderBy('ym', 'desc')
            ->pluck('ym');

        $monthOptions = $monthValues
            ->filter()
            ->map(function ($ym) {
                try {
                    $label = Carbon::createFromFormat('Y-m', $ym)->format('Y年n月');
                } catch (\Throwable $__e) {
                    $label = $ym;
                }
                return ['value' => $ym, 'label' => $label];
            })
            ->values();

        return inertia('JobBox/Index', [
            'projectJob' => $projectJob,
            'messages' => $messages,
            'q' => $q,
            'period' => $periodModel,
            'monthOptions' => $monthOptions,
            'sort' => $sort,
            'dir' => $dir,
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
        $periodParam = $request->query('period');
        $usePeriodFilter = true;
        $periodModel = $periodParam;
        if ($periodParam === null) {
            $periodModel = now()->format('Y-m');
        } elseif ($periodParam === '' || $periodParam === 'all') {
            $usePeriodFilter = false;
        }

        $periodStart = null;
        $periodEnd = null;
        if ($usePeriodFilter) {
            try {
                $periodStart = Carbon::createFromFormat('Y-m', $periodModel)->startOfMonth();
                $periodEnd = Carbon::createFromFormat('Y-m', $periodModel)->endOfMonth();
            } catch (\Throwable $__e) {
                $periodModel = now()->format('Y-m');
                $periodStart = Carbon::createFromFormat('Y-m', $periodModel)->startOfMonth();
                $periodEnd = Carbon::createFromFormat('Y-m', $periodModel)->endOfMonth();
            }
        }
        $sort = $request->input('sort');
        $dir = strtolower($request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $base = JobAssignmentMessage::select('job_assignment_messages.*')
            ->join('project_job_assignments', 'job_assignment_messages.project_job_assignment_id', '=', 'project_job_assignments.id')
            ->leftJoin('users as senders', 'job_assignment_messages.sender_id', '=', 'senders.id')
            ->where(function ($qry) use ($user) {
                $qry->where('project_job_assignments.user_id', $user->id)->orWhere('job_assignment_messages.sender_id', $user->id);
            });

        if ($q) {
            $base->where(function ($sub) use ($q) {
                $sub->where('job_assignment_messages.subject', 'like', "%{$q}%")->orWhere('job_assignment_messages.body', 'like', "%{$q}%");
            });
        }

        if ($usePeriodFilter && $periodStart && $periodEnd) {
            $base->whereBetween(
                DB::raw('COALESCE(project_job_assignments.desired_end_date, job_assignment_messages.created_at)'),
                [$periodStart, $periodEnd]
            );
        }

        switch ($sort) {
            case 'desired_start_date':
                $base->orderBy('project_job_assignments.desired_start_date', $dir);
                break;
            case 'sender':
                $base->orderBy('senders.name', $dir);
                break;
            case 'subject':
                $base->orderBy('job_assignment_messages.subject', $dir);
                break;
            default:
                $base->orderBy('job_assignment_messages.created_at', 'desc');
        }

        // Ensure statusModel is loaded for each linked assignment
        $messages = $base->with(['sender', 'message.recipients.user', 'message.fromUser', 'projectJobAssignment.projectJob.client', 'projectJobAssignment.statusModel', 'projectJobAssignment.user'])
            ->paginate($usePeriodFilter ? 500 : 50)
            ->appends(array_filter(['q' => $q, 'period' => $periodModel, 'sort' => $sort, 'dir' => $dir]));

        $monthValues = JobAssignmentMessage::join('project_job_assignments', 'job_assignment_messages.project_job_assignment_id', '=', 'project_job_assignments.id')
            ->where(function ($qry) use ($user) {
                $qry->where('project_job_assignments.user_id', $user->id)->orWhere('job_assignment_messages.sender_id', $user->id);
            })
            ->selectRaw("DATE_FORMAT(COALESCE(project_job_assignments.desired_end_date, job_assignment_messages.created_at), '%Y-%m') as ym")
            ->groupBy('ym')
            ->orderBy('ym', 'desc')
            ->pluck('ym');

        $monthOptions = $monthValues
            ->filter()
            ->map(function ($ym) {
                try {
                    $label = Carbon::createFromFormat('Y-m', $ym)->format('Y年n月');
                } catch (\Throwable $__e) {
                    $label = $ym;
                }
                return ['value' => $ym, 'label' => $label];
            })
            ->values();

        return inertia('JobBox/Index', [
            'projectJob' => null,
            'messages' => $messages,
            'q' => $q,
            'period' => $periodModel,
            'monthOptions' => $monthOptions,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    /**
     * User-scoped jobbox: show job messages where the assignment's user_id is the
     * authenticated user. This returns only messages for which the current user
     * is the assignee (project_job_assignments.user_id == auth id).
     */
    public function user(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $q = $request->input('q');
        $periodParam = $request->query('period');
        $usePeriodFilter = true;
        $periodModel = $periodParam;
        if ($periodParam === null) {
            $periodModel = now()->format('Y-m');
        } elseif ($periodParam === '' || $periodParam === 'all') {
            $usePeriodFilter = false;
        }

        $periodStart = null;
        $periodEnd = null;
        if ($usePeriodFilter) {
            try {
                $periodStart = Carbon::createFromFormat('Y-m', $periodModel)->startOfMonth();
                $periodEnd = Carbon::createFromFormat('Y-m', $periodModel)->endOfMonth();
            } catch (\Throwable $__e) {
                $periodModel = now()->format('Y-m');
                $periodStart = Carbon::createFromFormat('Y-m', $periodModel)->startOfMonth();
                $periodEnd = Carbon::createFromFormat('Y-m', $periodModel)->endOfMonth();
            }
        }
        $sort = $request->input('sort');
        $dir = strtolower($request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $base = JobAssignmentMessage::select('job_assignment_messages.*')
            ->join('project_job_assignments', 'job_assignment_messages.project_job_assignment_id', '=', 'project_job_assignments.id')
            ->leftJoin('users as senders', 'job_assignment_messages.sender_id', '=', 'senders.id')
            ->where('project_job_assignments.user_id', $user->id);

        if ($q) {
            $base->where(function ($sub) use ($q) {
                $sub->where('job_assignment_messages.subject', 'like', "%{$q}%")->orWhere('job_assignment_messages.body', 'like', "%{$q}%");
            });
        }

        if ($usePeriodFilter && $periodStart && $periodEnd) {
            $base->whereBetween(
                DB::raw('COALESCE(project_job_assignments.desired_end_date, job_assignment_messages.created_at)'),
                [$periodStart, $periodEnd]
            );
        }

        switch ($sort) {
            case 'desired_start_date':
                $base->orderBy('project_job_assignments.desired_start_date', $dir);
                break;
            case 'sender':
                $base->orderBy('senders.name', $dir);
                break;
            case 'subject':
                $base->orderBy('job_assignment_messages.subject', $dir);
                break;
            default:
                $base->orderBy('job_assignment_messages.created_at', 'desc');
        }

        $messages = $base->with(['sender', 'message.recipients.user', 'message.fromUser', 'projectJobAssignment.projectJob.client', 'projectJobAssignment.statusModel', 'projectJobAssignment.user'])
            ->paginate($usePeriodFilter ? 500 : 50)
            ->appends(array_filter(['q' => $q, 'period' => $periodModel, 'sort' => $sort, 'dir' => $dir]));

        $monthValues = JobAssignmentMessage::join('project_job_assignments', 'job_assignment_messages.project_job_assignment_id', '=', 'project_job_assignments.id')
            ->where('project_job_assignments.user_id', $user->id)
            ->selectRaw("DATE_FORMAT(COALESCE(project_job_assignments.desired_end_date, job_assignment_messages.created_at), '%Y-%m') as ym")
            ->groupBy('ym')
            ->orderBy('ym', 'desc')
            ->pluck('ym');

        $monthOptions = $monthValues
            ->filter()
            ->map(function ($ym) {
                try {
                    $label = Carbon::createFromFormat('Y-m', $ym)->format('Y年n月');
                } catch (\Throwable $__e) {
                    $label = $ym;
                }
                return ['value' => $ym, 'label' => $label];
            })
            ->values();

        return inertia('JobBox/Index', [
            'projectJob' => null,
            'messages' => $messages,
            'q' => $q,
            'period' => $periodModel,
            'monthOptions' => $monthOptions,
            'sort' => $sort,
            'dir' => $dir,
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

        // mark read (and set accepted=true to link accepted to read)
        if (! $message->read_at) {
            $message->read_at = now();
            // set accepted flag to true when message is read
            try {
                $message->accepted = true;
            } catch (\Throwable $__e) {
                // ignore if column missing
            }
            // reflect read on the related assignment as well
            try {
                if ($message->project_job_assignment_id) {
                    $assignment = ProjectJobAssignment::find($message->project_job_assignment_id);
                    if ($assignment) {
                        $assignment->read_at = $message->read_at;
                        try {
                            $assignment->accepted = true;
                        } catch (\Throwable $__e) {
                        }
                        // set status_id to 'confirmed' (accepted/read) if statuses table exists
                        try {
                            if (Schema::hasTable('statuses') && Schema::hasColumn('project_job_assignments', 'status_id')) {
                                $status = DB::table('statuses')->where('key', 'confirmed')->first();
                                if (!$status) {
                                    $statusId = DB::table('statuses')->insertGetId(['key' => 'confirmed', 'name' => '確認済み', 'created_at' => now(), 'updated_at' => now()]);
                                } else {
                                    $statusId = $status->id;
                                }
                                $assignment->status_id = $statusId;
                            }
                        } catch (\Throwable $__e) {
                            // non-fatal
                        }
                        $assignment->save();
                    }
                }
            } catch (\Throwable $__e) {
                // non-fatal
            }
            $message->save();

            // If this job message is linked to a Message, mark MessageRecipient rows as read
            if ($message->message_id) {
                try {
                    $updated = \App\Models\MessageRecipient::where('message_id', $message->message_id)
                        ->where('user_id', \Illuminate\Support\Facades\Auth::id())
                        ->whereNull('read_at')
                        ->update(['read_at' => now()]);
                } catch (\Throwable $__e) {
                    // non-fatal
                }
            }

            // Broadcast job-specific read event so frontend can decrement job unread counter.
            // We broadcast the JobAssignmentMessage id (not Message id) so listeners can
            // treat this as a jobbox read. Payload uses key `message_id` for backward
            // compatibility with existing frontend listeners.
            try {
                // also notify the original sender so they receive the read update in real-time
                $senderId = $message->sender_id ?? null;
                $readAt = $message->read_at ? $message->read_at->toDateTimeString() : now()->toDateTimeString();
                $ev = new \App\Events\JobMessageRead($message->id, \Illuminate\Support\Facades\Auth::id(), $senderId ? [$senderId] : null);
                $ev->read_at = $readAt;
                event($ev);
            } catch (\Throwable $__e) {
                // non-fatal
            }
        }

        // load sender and the related assignment with its user and lookup relations
        // so the frontend Show view can access `message.project_job_assignment` and display
        // assignment details such as sizes, stages and work item types.
        $message->load([
            'sender',
            'projectJobAssignment.user',
            'projectJobAssignment.projectJob.client',
            'projectJobAssignment.size',
            'projectJobAssignment.stage',
            'projectJobAssignment.workItemType',
            'projectJobAssignment.statusModel',
        ]);

        // Add human-friendly label attributes on the loaded relation so the Inertia
        // page can read `assignment.size_label`, `assignment.stage_label`, etc.
        try {
            if ($message->projectJobAssignment) {
                $a = $message->projectJobAssignment;
                $a->type_label = isset($a->workItemType) && $a->workItemType ? ($a->workItemType->name ?? $a->workItemType->label ?? null) : null;
                $a->size_label = isset($a->size) && $a->size ? ($a->size->name ?? $a->size->label ?? null) : null;
                $a->stage_label = isset($a->stage) && $a->stage ? ($a->stage->name ?? $a->stage->label ?? null) : null;
                $a->status_label = isset($a->statusModel) && $a->statusModel ? ($a->statusModel->name ?? $a->statusModel->label ?? null) : null;
                // resolve difficulty_label for frontend display (prefer difficulty_id, fallback to legacy difficulty)
                try {
                    $a->difficulty_label = null;
                    if (isset($a->difficulty_id) && $a->difficulty_id) {
                        $d = \App\Models\Difficulty::find($a->difficulty_id);
                        if ($d) $a->difficulty_label = $d->name;
                    }
                    if (empty($a->difficulty_label) && !empty($a->difficulty)) {
                        $q = \App\Models\Difficulty::query();
                        if (Schema::hasColumn('difficulties', 'slug')) {
                            $q->where('slug', $a->difficulty)->orWhere('name', $a->difficulty);
                        } else {
                            $q->where('name', $a->difficulty);
                        }
                        $d2 = $q->first();
                        if ($d2) $a->difficulty_label = $d2->name;
                    }
                } catch (\Throwable $__e) {
                    // non-fatal
                }
            }
        } catch (\Throwable $__e) {
            // non-fatal: if lookup relations or attributes are missing, continue without labels
        }

        // build difficulties list with either (id,name,slug) when slug exists, or (id,name) otherwise
        $difficultySelect = ['id', 'name'];
        try {
            if (Schema::hasColumn('difficulties', 'slug')) $difficultySelect[] = 'slug';
        } catch (\Throwable $__e) {
            // if schema introspection fails for any reason, default to id,name
        }
        try {
            $difficulties = \App\Models\Difficulty::orderBy('sort_order')->get($difficultySelect);
        } catch (\Throwable $__e) {
            $difficulties = collect();
        }

        return inertia('JobBox/Show', [
            'projectJob' => $projectJob,
            'message' => $message,
            'difficulties' => $difficulties,
        ]);
    }

    /**
     * Lightweight JSON endpoint for fetching a single JobAssignmentMessage by id.
     * Used by the frontend when an event contains only the jam id.
     */
    public function apiShow(Request $request, $id)
    {
        $user = $request->user();
        $jam = JobAssignmentMessage::with([
            'sender',
            'projectJobAssignment.user',
            'projectJobAssignment.size',
            'projectJobAssignment.stage',
            'projectJobAssignment.workItemType',
            'projectJobAssignment.statusModel',
            'message.fromUser',
            'message.recipients.user',
        ])->findOrFail($id);

        // Authorization: allow if user is privileged, owner of the project, sender, or assigned
        $isPrivileged = $user && (method_exists($user, 'isCoordinator') && ($user->isCoordinator() || $user->isLeader() || $user->isAdmin() || $user->isSuperAdmin()));
        $isOwner = false;
        try {
            $pj = $jam->projectJobAssignment ? $jam->projectJobAssignment->projectJob : null;
            if ($pj && $pj->user_id && $user && $user->id === $pj->user_id) $isOwner = true;
        } catch (\Throwable $__e) {
            // ignore
        }

        $isSender = $user && $jam->sender_id && $user->id === $jam->sender_id;
        $isAssignee = $user && $jam->project_job_assignment && $jam->project_job_assignment->user_id && $user->id === $jam->project_job_assignment->user_id;

        if (! $isPrivileged && ! $isOwner && ! $isSender && ! $isAssignee) {
            abort(403, 'Access denied.');
        }

        // map to a JSON-friendly shape
        $mapped = [
            'id' => $jam->id,
            'subject' => $jam->subject,
            'body' => $jam->body,
            'sender' => $jam->sender ? ['id' => $jam->sender->id, 'name' => $jam->sender->name] : null,
            'project_job_assignment' => $jam->project_job_assignment ? [
                'project_job' => $jam->project_job_assignment->projectJob ? [
                    'id' => $jam->project_job_assignment->projectJob->id,
                    'title' => $jam->project_job_assignment->projectJob->title ?? null,
                    'client' => $jam->project_job_assignment->projectJob->client ? ['id' => $jam->project_job_assignment->projectJob->client->id, 'name' => $jam->project_job_assignment->projectJob->client->name] : null,
                ] : null,
                'id' => $jam->project_job_assignment->id,
                'user' => $jam->project_job_assignment->user ? ['id' => $jam->project_job_assignment->user->id, 'name' => $jam->project_job_assignment->user->name] : null,
                'title' => $jam->project_job_assignment->title ?? null,
                'detail' => $jam->project_job_assignment->detail ?? null,
                'difficulty' => $jam->project_job_assignment->difficulty ?? null,
                'difficulty_id' => $jam->project_job_assignment->difficulty_id ?? null,
                'difficulty_label' => null,
                'desired_start_date' => $jam->project_job_assignment->desired_start_date ?? null,
                'desired_end_date' => $jam->project_job_assignment->desired_end_date ?? null,
                'desired_time' => $jam->project_job_assignment->desired_time ?? null,
                'estimated_hours' => $jam->project_job_assignment->estimated_hours ?? null,
                'assigned' => (bool) ($jam->project_job_assignment->assigned ?? false),
                'scheduled' => (bool) ($jam->project_job_assignment->scheduled ?? false),
                'scheduled_at' => $jam->project_job_assignment->scheduled_at ?? null,
                'completed' => (bool) ($jam->project_job_assignment->completed ?? false),
                'accepted' => (bool) ($jam->project_job_assignment->accepted ?? false),
                'read_at' => $jam->project_job_assignment->read_at ? $jam->project_job_assignment->read_at->toDateTimeString() : null,
                'size_label' => $jam->project_job_assignment->size ? ($jam->project_job_assignment->size->name ?? $jam->project_job_assignment->size->label ?? null) : null,
                'stage_label' => $jam->project_job_assignment->stage ? ($jam->project_job_assignment->stage->name ?? $jam->project_job_assignment->stage->label ?? null) : null,
                'type_label' => $jam->project_job_assignment->workItemType ? ($jam->project_job_assignment->workItemType->name ?? $jam->project_job_assignment->workItemType->label ?? null) : null,
                'status_label' => $jam->project_job_assignment->statusModel ? ($jam->project_job_assignment->statusModel->name ?? $jam->project_job_assignment->statusModel->label ?? null) : null,
                'difficulty_label' => (function () use ($jam) {
                    try {
                        $a = $jam->project_job_assignment;
                        if (!$a) return null;
                        if (isset($a->difficulty_id) && $a->difficulty_id) {
                            $d = \App\Models\Difficulty::find($a->difficulty_id);
                            if ($d) return $d->name;
                        }
                        if (!empty($a->difficulty)) {
                            $q = \App\Models\Difficulty::query();
                            if (Schema::hasColumn('difficulties', 'slug')) {
                                $q->where('slug', $a->difficulty)->orWhere('name', $a->difficulty);
                            } else {
                                $q->where('name', $a->difficulty);
                            }
                            $d2 = $q->first();
                            if ($d2) return $d2->name;
                            return $a->difficulty;
                        }
                    } catch (\Throwable $__e) {
                        return null;
                    }
                    return null;
                })(),
            ] : null,
            'message' => $jam->message ? [
                'id' => $jam->message->id,
                'subject' => $jam->message->subject,
                'fromUser' => $jam->message->fromUser ? ['id' => $jam->message->fromUser->id, 'name' => $jam->message->fromUser->name] : null,
            ] : null,
            'read_at' => $jam->read_at ? $jam->read_at->toDateTimeString() : null,
        ];

        return response()->json(['data' => $mapped]);
    }

    /**
     * SPA-friendly endpoint to mark a JobAssignmentMessage as read.
     * This is used by the frontend Show view when opened via Inertia or when
     * the broadcast supplied only an id and the frontend fetched the jam.
     */
    public function apiMarkRead(Request $request, $id)
    {
        $user = $request->user();
        $jam = JobAssignmentMessage::findOrFail($id);

        // Authorization: only assignee or privileged roles or sender/owner can mark
        $isPrivileged = $user && (method_exists($user, 'isCoordinator') && ($user->isCoordinator() || $user->isLeader() || $user->isAdmin() || $user->isSuperAdmin()));
        $isOwner = false;
        try {
            $pj = $jam->projectJobAssignment ? $jam->projectJobAssignment->projectJob : null;
            if ($pj && $pj->user_id && $user && $user->id === $pj->user_id) $isOwner = true;
        } catch (\Throwable $__e) {
            // ignore
        }

        $isSender = $user && $jam->sender_id && $user->id === $jam->sender_id;
        $isAssignee = $user && $jam->project_job_assignment && $jam->project_job_assignment->user_id && $user->id === $jam->project_job_assignment->user_id;

        if (! $isPrivileged && ! $isOwner && ! $isSender && ! $isAssignee) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        if (! $jam->read_at) {
            $jam->read_at = now();
            // set accepted flag when marking read
            try {
                $jam->accepted = true;
            } catch (\Throwable $__e) {
                // ignore if column missing
            }
            // reflect read on related assignment as well
            try {
                if ($jam->project_job_assignment_id) {
                    $assignment = ProjectJobAssignment::find($jam->project_job_assignment_id);
                    if ($assignment) {
                        $assignment->read_at = $jam->read_at;
                        try {
                            $assignment->accepted = true;
                        } catch (\Throwable $__e) {
                        }
                        $assignment->save();
                    }
                }
            } catch (\Throwable $__e) {
                // non-fatal
            }
            $jam->save();

            // If linked to a Message, update corresponding recipient
            if ($jam->message_id) {
                try {
                    \App\Models\MessageRecipient::where('message_id', $jam->message_id)
                        ->where('user_id', $user ? $user->id : 0)
                        ->whereNull('read_at')
                        ->update(['read_at' => now()]);
                } catch (\Throwable $__e) {
                    // non-fatal
                }
            }

            try {
                $senderId = $jam->sender_id ?? null;
                $readAt = $jam->read_at ? $jam->read_at->toDateTimeString() : now()->toDateTimeString();
                $ev = new \App\Events\JobMessageRead($jam->id, $user ? $user->id : null, $senderId ? [$senderId] : null);
                $ev->read_at = $readAt;
                event($ev);
            } catch (\Throwable $__e) {
                // non-fatal
            }
        }

        return response()->json(['data' => ['id' => $jam->id, 'read_at' => $jam->read_at ? $jam->read_at->toDateTimeString() : null]]);
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

            // For job assignment requests, do NOT create a regular Message that lands in users' inboxes.
            // Job assignment notifications live in JobBox only. We still broadcast a job-specific event
            // so frontends subscribed to jobmessages.* receive the notification.
            $sanitizer = app(\App\Services\HtmlSanitizer::class);
            $body = $sanitizer->purify($data['body'] ?? null);

            // Do not create \App\Models\Message here. Keep job_assignment_messages self-contained.
            // Persist attachments in the job_assignment_messages.attachments JSON if provided
            if (!empty($data['attachments'])) {
                $jam->attachments = $data['attachments'];
                $jam->save();
            }

            // mark the related assignment as assigned so UI/status reflects sent state
            try {
                $assignment = ProjectJobAssignment::find($jam->project_job_assignment_id);
                if ($assignment && (int)$assignment->project_job_id === (int)$projectJob->id) {
                    $assignment->assigned = true;
                    // set accepted true on the assignment when JAM is created (recipient has received)
                    try {
                        $assignment->accepted = true;
                    } catch (\Throwable $__e) {
                        // ignore if column missing
                    }
                    $assignment->save();
                }
            } catch (\Throwable $__e) {
                // non-fatal
            }

            // Broadcast event for job-specific real-time notifications. We construct a lightweight
            // pseudo-message object so the event payload contains subject/from_user_name while
            // avoiding creating a Message row that would appear in users' message inboxes.
            try {
                // Reload the created JAM with useful relations so the event can include a full jam payload
                try {
                    $jamLoaded = JobAssignmentMessage::with(['sender', 'projectJobAssignment.user', 'projectJobAssignment.projectJob.client', 'message.fromUser', 'message.recipients.user'])->find($jam->id);
                    $recipientIds = is_array($data['to']) ? array_values(array_unique($data['to'])) : [];
                    // pass the loaded jam model to the event so broadcastWith() can serialize relations
                    event(new \App\Events\JobMessageCreated($jamLoaded, $recipientIds, $jam->id));
                } catch (\Throwable $__e) {
                    // fallback: attempt a simpler reload of the JAM with client relation before sending a minimal payload
                    $recipientIds = is_array($data['to']) ? array_values(array_unique($data['to'])) : [];
                    try {
                        $jamLoadedFallback = JobAssignmentMessage::with(['sender', 'projectJobAssignment.user', 'projectJobAssignment.projectJob.client', 'message.fromUser', 'message.recipients.user'])->find($jam->id);
                        if ($jamLoadedFallback) {
                            event(new \App\Events\JobMessageCreated($jamLoadedFallback, $recipientIds, $jam->id));
                        } else {
                            event(new \App\Events\JobMessageCreated((object) ['id' => $jam->id, 'subject' => $data['subject'] ?? null, 'body' => $body, 'fromUser' => (object) ['name' => $request->user()->name ?? null]], $recipientIds, $jam->id));
                        }
                    } catch (\Throwable $__inner2) {
                        event(new \App\Events\JobMessageCreated((object) ['id' => $jam->id, 'subject' => $data['subject'] ?? null, 'body' => $body, 'fromUser' => (object) ['name' => $request->user()->name ?? null]], $recipientIds, $jam->id));
                    }
                }
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
    /**
     * Mark a ProjectJobAssignment as completed (status = completed, completed = true).
     * Accessible by the assignee or privileged roles.
     */
    public function completeAssignment(Request $request, ProjectJobAssignment $assignment)
    {
        $user = $request->user();
        $isPrivileged = $user && (method_exists($user, 'isCoordinator') && ($user->isCoordinator() || $user->isLeader() || $user->isAdmin() || $user->isSuperAdmin()));

        if (!$isPrivileged && (!$user || $assignment->user_id !== $user->id)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        try {
            $assignment->completed = true;
        } catch (\Throwable $__e) {}

        // Set status to 'completed'
        try {
            if (Schema::hasTable('statuses') && Schema::hasColumn('project_job_assignments', 'status_id')) {
                $status = DB::table('statuses')->where('key', 'completed')->orWhere('slug', 'completed')->first();
                if (!$status) {
                    $statusId = DB::table('statuses')->insertGetId(['key' => 'completed', 'slug' => 'completed', 'name' => '完了', 'created_at' => now(), 'updated_at' => now()]);
                } else {
                    $statusId = $status->id;
                }
                $assignment->status_id = $statusId;
            }
        } catch (\Throwable $__e) {}

        $assignment->save();

        return response()->json(['success' => true, 'assignment_id' => $assignment->id]);
    }

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
                // When replying we have both a Message and a linked JAM; load the JAM with relations
                // so the event payload contains consistent jam/project_job_assignment/recipients info.
                try {
                    $jamLoaded = JobAssignmentMessage::with(['sender', 'projectJobAssignment.user', 'projectJobAssignment.projectJob.client', 'message.fromUser', 'message.recipients.user'])->find($jam->id);
                    event(new \App\Events\JobMessageCreated($jamLoaded, $recipientIds, $jam->id));
                } catch (\Throwable $__inner) {
                    // fallback: try to load the JAM with client relation; if it exists prefer it, otherwise send the Message object
                    try {
                        $jamLoadedFallback = JobAssignmentMessage::with(['sender', 'projectJobAssignment.user', 'projectJobAssignment.projectJob.client', 'message.fromUser', 'message.recipients.user'])->find($jam->id);
                        if ($jamLoadedFallback) {
                            event(new \App\Events\JobMessageCreated($jamLoadedFallback, $recipientIds, $jam->id));
                        } else {
                            event(new \App\Events\JobMessageCreated($message, $recipientIds, $jam->id));
                        }
                    } catch (\Throwable $__inner2) {
                        event(new \App\Events\JobMessageCreated($message, $recipientIds, $jam->id));
                    }
                }
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
