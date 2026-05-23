<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\JobAssignmentMessage;
use App\Models\ProgressSheet;
use App\Models\ProjectJob;
use App\Models\ProjectJobAssignment;
use App\Models\ProjectTeamMember;
use App\Models\ProjectSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ProjectJobController extends Controller
{
    /**
     * Return JSON list of progress sheets for a given project job.
     * Used by the "詳細を見る（進行表へ）" modal.
     */
    public function progressSheetsJson(Request $request, ProjectJob $projectJob)
    {
        $user = $request->user();

        $hasAccess = $projectJob->projectJobAssignments()->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)->orWhere('sender_id', $user->id);
        })->exists()
            || $projectJob->teamMembers()->where('user_id', $user->id)->exists();

        if (!$hasAccess) {
            return response()->json([], 403);
        }

        $sheets = ProgressSheet::where('project_job_id', $projectJob->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn($s) => ['id' => $s->id, 'name' => $s->name]);

        return response()->json($sheets);
    }

    /**
     * Return JSON list of both progress sheets and workflow sheets.
     * Used by the "進行表・管理表ジョブ" modal.
     */
    public function sheetsJson(Request $request, ProjectJob $projectJob)
    {
        $user = $request->user();

        $hasAccess = $projectJob->projectJobAssignments()->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)->orWhere('sender_id', $user->id);
        })->exists()
            || $projectJob->teamMembers()->where('user_id', $user->id)->exists();

        if (!$hasAccess) {
            return response()->json(['progress_sheets' => [], 'workflow_sheets' => []], 403);
        }

        $progressSheets = ProgressSheet::where('project_job_id', $projectJob->id)
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn($s) => ['id' => $s->id, 'name' => $s->name]);

        $workflowSheets = \App\Models\WorkflowSheet::where('project_job_id', $projectJob->id)
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn($s) => ['id' => $s->id, 'name' => $s->name]);

        return response()->json([
            'progress_sheets' => $progressSheets,
            'workflow_sheets'  => $workflowSheets,
        ]);
    }

    /**
     * Return JSON list of clients and projects accessible to the current user.
     * Used by the calendar modal for "ジョブ作成（進行表から）".
     */
    public function projectsJson(Request $request)
    {
        $user = $request->user();

        $assignmentJobIds = ProjectJobAssignment::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)->orWhere('sender_id', $user->id);
        })->pluck('project_job_id');

        $teamMemberJobIds = ProjectTeamMember::where('user_id', $user->id)
            ->pluck('project_job_id');

        // 案件リーダー・副リーダーとして紐づいている案件IDも含める
        $leaderJobIds = ProjectJob::where('user_id', $user->id)
            ->where('completed', false)
            ->pluck('id');

        $subLeaderJobIds = ProjectJob::whereHas('coordinators', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        })->where('completed', false)->pluck('id');

        $jobIds = $assignmentJobIds->merge($teamMemberJobIds)->merge($leaderJobIds)->merge($subLeaderJobIds)->unique();

        $jobs = ProjectJob::whereIn('id', $jobIds)
            ->where('completed', false)
            ->with('client')
            ->get(['id', 'title', 'client_id']);

        $clientsMap = [];
        foreach ($jobs as $job) {
            if ($job->client) {
                $clientsMap[$job->client->id] = ['id' => $job->client->id, 'name' => $job->client->name ?? '-'];
            }
        }

        $projects = $jobs->map(fn($j) => [
            'id'        => $j->id,
            'title'     => $j->title ?? $j->name ?? '-',
            'client_id' => $j->client_id,
        ])->values();

        return response()->json([
            'clients'  => array_values($clientsMap),
            'projects' => $projects,
        ]);
    }

    public function index(Request $request)
    {
        $user       = $request->user();
        $q          = $request->input('q', '');
        $period     = $request->input('period', '');
        $sortStatus = $request->input('sort_status', '');

        // 自分が受信者または発信者である割当、もしくはチームメンバーとして登録されている案件IDを取得
        $assignmentJobIds = ProjectJobAssignment::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->orWhere('sender_id', $user->id);
        })->pluck('project_job_id');

        $teamMemberJobIds = ProjectTeamMember::where('user_id', $user->id)
            ->pluck('project_job_id');

        $jobIds = $assignmentJobIds->merge($teamMemberJobIds)->unique();

        $query = ProjectJob::with('client')->whereIn('id', $jobIds);

        if ($q) {
            $query->where(function ($q2) use ($q) {
                $q2->where('title', 'like', "%{$q}%")
                   ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$q}%"));
            });
        }

        if ($period && $period !== 'all') {
            [$y, $m] = explode('-', $period);
            $query->whereYear('created_at', $y)->whereMonth('created_at', $m);
        }

        if ($sortStatus === 'asc') {
            $query->orderBy('completed')->orderBy('created_at', 'desc');
        } elseif ($sortStatus === 'desc') {
            $query->orderByDesc('completed')->orderBy('created_at', 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $jobs = $query->get();

        $monthOptions = [];
        for ($i = 0; $i < 12; $i++) {
            $d            = now()->subMonths($i);
            $monthOptions[] = [
                'value' => $d->format('Y-m'),
                'label' => $d->format('Y年n月'),
            ];
        }

        return Inertia::render('User/ProjectJobs/Index', [
            'jobs'         => $jobs,
            'monthOptions' => $monthOptions,
            'q'            => $q,
            'period'       => $period,
            'sortStatus'   => $sortStatus,
        ]);
    }



    public function show(Request $request, ProjectJob $projectJob)
    {
        $user = $request->user();

        // 自分が関係する案件かチェック（アサインメントまたはチームメンバー）
        $hasAccess = ProjectJobAssignment::where('project_job_id', $projectJob->id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhere('sender_id', $user->id);
            })->exists()
            || ProjectTeamMember::where('project_job_id', $projectJob->id)
                ->where('user_id', $user->id)->exists();

        if (!$hasAccess) {
            abort(403);
        }

        $projectJob->load(['teamMembers.user', 'user', 'client', 'coordinators', 'size']);

        $members = $projectJob->teamMembers->map(fn ($m) => [
            'id'      => $m->id,
            'user_id' => $m->user_id,
            'user'    => $m->user ? ['id' => $m->user->id, 'name' => $m->user->name] : null,
        ]);

        $hasSchedule = ProjectSchedule::where('project_job_id', $projectJob->id)->exists();

        $schedules = ProjectSchedule::where('project_job_id', $projectJob->id)
            ->orderBy('start_date')
            ->get(['id', 'name', 'description', 'start_date', 'end_date']);

        // 自分が発信者または受信者のジョブ履歴のみ
        $jobHistory = [];
        try {
            $jobHistory = JobAssignmentMessage::select('job_assignment_messages.*')
                ->join('project_job_assignments', 'job_assignment_messages.project_job_assignment_id', '=', 'project_job_assignments.id')
                ->where('project_job_assignments.project_job_id', $projectJob->id)
                ->where(function ($q) use ($user) {
                    $q->where('project_job_assignments.user_id', $user->id)
                      ->orWhere('project_job_assignments.sender_id', $user->id);
                })
                ->with([
                    'sender',
                    'projectJobAssignment.projectJob.client',
                    'projectJobAssignment',
                    'projectJobAssignment.statusModel',
                    'message.recipients.user',
                    'message.fromUser',
                    'projectJobAssignment.user',
                ])
                ->orderBy('job_assignment_messages.created_at', 'desc')
                ->get();

            $jobHistory->transform(function ($msg) {
                try {
                    if (isset($msg->projectJobAssignment->statusModel)) {
                        $sm = $msg->projectJobAssignment->statusModel;
                        $msg->projectJobAssignment->status = [
                            'id'   => $sm->id,
                            'key'  => $sm->key ?? $sm->slug ?? null,
                            'name' => $sm->name,
                        ];
                    }
                } catch (\Throwable $_) {}
                return $msg;
            });
        } catch (\Throwable $e) {
            Log::warning('Failed to build jobHistory for user project job show', [
                'error'          => $e->getMessage(),
                'project_job_id' => $projectJob->id,
            ]);
        }

        // 進行表リンク済みで jobHistory に未登録の割当を合成エントリとして追加
        $sheetLinkedAssignmentIds = [];
        try {
            $sheetLinkedAssignmentIds = DB::table('progress_cells')
                ->join('progress_rows', 'progress_rows.id', '=', 'progress_cells.row_id')
                ->join('progress_sheets', 'progress_sheets.id', '=', 'progress_rows.sheet_id')
                ->where('progress_sheets.project_job_id', $projectJob->id)
                ->whereNotNull('progress_cells.assignment_id')
                ->pluck('progress_cells.assignment_id')
                ->unique()
                ->map(fn ($id) => (int) $id)
                ->toArray();

            if (!empty($sheetLinkedAssignmentIds)) {
                $existingAids = collect(
                    $jobHistory instanceof \Illuminate\Support\Collection ? $jobHistory->toArray() : (array) $jobHistory
                )->map(fn ($m) => (int) ($m['project_job_assignment_id'] ?? $m['project_job_assignment']['id'] ?? 0))
                 ->filter()->unique()->toArray();

                $missingIds = array_values(array_diff($sheetLinkedAssignmentIds, $existingAids));

                if (!empty($missingIds)) {
                    $missings = ProjectJobAssignment::whereIn('id', $missingIds)
                        ->where('project_job_id', $projectJob->id)
                        ->with(['user', 'statusModel'])
                        ->get();

                    $synths = $missings->map(function ($a) {
                        $sm = $a->statusModel;
                        return [
                            'id'                        => null,
                            'project_job_assignment_id' => $a->id,
                            'subject'                   => $a->title,
                            'body'                      => null,
                            'created_at'                => $a->created_at,
                            'read_at'                   => $a->read_at,
                            'sender'                    => $a->user
                                ? ['id' => $a->user->id, 'name' => $a->user->name]
                                : null,
                            'message'                   => null,
                            'project_job_assignment'    => [
                                'id'               => $a->id,
                                'title'            => $a->title,
                                'user_id'          => $a->user_id,
                                'desired_end_date' => $a->desired_end_date?->format('Y-m-d'),
                                'start_time'       => $a->start_time,
                                'completed'        => (bool) $a->completed,
                                'scheduled'        => (bool) ($a->scheduled ?? false),
                                'scheduled_at'     => $a->scheduled_at,
                                'read_at'          => $a->read_at,
                                'status'           => $sm
                                    ? ['id' => $sm->id, 'key' => $sm->key ?? $sm->slug ?? null, 'name' => $sm->name]
                                    : null,
                                'user'             => $a->user
                                    ? ['id' => $a->user->id, 'name' => $a->user->name]
                                    : null,
                            ],
                        ];
                    })->toArray();

                    $base = $jobHistory instanceof \Illuminate\Support\Collection
                        ? $jobHistory->toArray()
                        : (array) $jobHistory;
                    $jobHistory = array_merge($base, $synths);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to add sheet-linked synthetic jobHistory entries', [
                'error'          => $e->getMessage(),
                'project_job_id' => $projectJob->id,
            ]);
        }

        // 自己割当（sender_id = user_id）で jobHistory 未収録のものを synthetic エントリとして追加
        try {
            $jhArr2 = $jobHistory instanceof \Illuminate\Support\Collection ? $jobHistory->toArray() : (array) $jobHistory;
            $coveredAids = collect($jhArr2)
                ->map(fn ($m) => (int) ($m['project_job_assignment_id'] ?? $m['project_job_assignment']['id'] ?? 0))
                ->filter()->unique()->toArray();

            $selfAssigned = ProjectJobAssignment::where('project_job_id', $projectJob->id)
                ->where('user_id', $user->id)
                ->whereColumn('sender_id', 'user_id')   // 自己割当のみ
                ->whereNotIn('id', $coveredAids)
                ->with(['user', 'statusModel'])
                ->get();

            if ($selfAssigned->isNotEmpty()) {
                $selfSynths = $selfAssigned->map(function ($a) {
                    $sm = $a->statusModel;
                    return [
                        'id'                        => null,
                        'project_job_assignment_id' => $a->id,
                        'subject'                   => $a->title,
                        'body'                      => null,
                        'created_at'                => $a->created_at,
                        'read_at'                   => $a->read_at,
                        'sender'                    => $a->user
                            ? ['id' => $a->user->id, 'name' => $a->user->name]
                            : null,
                        'message'                   => null,
                        'project_job_assignment'    => [
                            'id'               => $a->id,
                            'title'            => $a->title,
                            'user_id'          => $a->user_id,
                            'desired_end_date' => $a->desired_end_date?->format('Y-m-d'),
                            'start_time'       => $a->start_time,
                            'completed'        => (bool) $a->completed,
                            'scheduled'        => (bool) ($a->scheduled ?? false),
                            'scheduled_at'     => $a->scheduled_at,
                            'read_at'          => $a->read_at,
                            'status'           => $sm
                                ? ['id' => $sm->id, 'key' => $sm->key ?? $sm->slug ?? null, 'name' => $sm->name]
                                : null,
                            'user'             => $a->user
                                ? ['id' => $a->user->id, 'name' => $a->user->name]
                                : null,
                        ],
                    ];
                })->toArray();

                $jobHistory = array_merge(
                    $jobHistory instanceof \Illuminate\Support\Collection ? $jobHistory->toArray() : (array) $jobHistory,
                    $selfSynths
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to add self-assigned synthetic jobHistory entries', [
                'error'          => $e->getMessage(),
                'project_job_id' => $projectJob->id,
            ]);
        }

        // jobHistory の各エントリにカレンダーイベント情報を付加（作業日表示用）
        try {
            $jhArr = $jobHistory instanceof \Illuminate\Support\Collection ? $jobHistory->toArray() : (array) $jobHistory;
            $assignmentIds = collect($jhArr)
                ->map(fn ($m) => (int) ($m['project_job_assignment_id'] ?? $m['project_job_assignment']['id'] ?? 0))
                ->filter()->unique()->values()->toArray();

            if (!empty($assignmentIds)) {
                $eventsByAssignment = DB::table('events')
                    ->whereIn('project_job_assignment_id', $assignmentIds)
                    ->whereNotNull('starts_at')
                    ->orderBy('starts_at')
                    ->get(['project_job_assignment_id', 'starts_at', 'ends_at'])
                    ->keyBy('project_job_assignment_id');

                $jobHistory = array_map(function ($m) use ($eventsByAssignment) {
                    $aid = (int) ($m['project_job_assignment_id'] ?? $m['project_job_assignment']['id'] ?? 0);
                    if ($aid && isset($eventsByAssignment[$aid])) {
                        $ev = $eventsByAssignment[$aid];
                        $m['event_starts_at'] = $ev->starts_at;
                        $m['event_ends_at']   = $ev->ends_at;
                    }
                    return $m;
                }, $jhArr);
            } else {
                $jobHistory = $jhArr;
            }
        } catch (\Throwable $_) {
            // non-fatal
        }

        $subCoordinators = $projectJob->coordinators->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]);

        // 進行表（名前一覧のみ。詳細は user.progress_sheets.show で取得）
        $progressSheets = ProgressSheet::where('project_job_id', $projectJob->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'sort_order'])
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'sort_order' => $s->sort_order]);

        // 管理シート（名前一覧のみ）
        $workflowSheets = \App\Models\WorkflowSheet::where('project_job_id', $projectJob->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'sort_order', 'created_at'])
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'sort_order' => $s->sort_order]);

        // 校正依頼済みのアサインメントIDセット（ボタン無効化用）
        $requestedAssignmentIds = [];
        try {
            $requestedAssignmentIds = \App\Models\ProofRequest::where('project_job_id', $projectJob->id)
                ->whereNotNull('project_job_assignment_id')
                ->whereNotIn('status', ['completed'])
                ->pluck('project_job_assignment_id')
                ->map(fn ($id) => (int) $id)
                ->toArray();
        } catch (\Throwable $e) {}

        return Inertia::render('User/ProjectJobs/Show', [
            'job'                      => $projectJob,
            'subCoordinators'          => $subCoordinators,
            'members'                  => $members,
            'hasSchedule'              => $hasSchedule,
            'schedules'                => $schedules,
            'jobHistory'               => $jobHistory,
            'progressSheets'           => $progressSheets,
            'workflowSheets'           => $workflowSheets,
            'requestedAssignmentIds'   => $requestedAssignmentIds,
            'sheetLinkedAssignmentIds' => $sheetLinkedAssignmentIds,
        ]);
    }
}
