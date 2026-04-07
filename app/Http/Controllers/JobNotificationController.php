<?php

namespace App\Http\Controllers;

use App\Models\JobAssignmentMessage;
use App\Models\JobNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class JobNotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $group = $request->query('group', 'day');
        $days  = (int) $request->query('days', 30);
        if (! in_array($days, [7, 30, 90])) {
            $days = 30;
        }

        $since = Carbon::now()->subDays($days)->startOfDay();

        $notifications = JobNotification::where('recipient_id', $user->id)
            ->where('created_at', '>=', $since)
            ->with(['sender', 'projectJob'])
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('JobNotifications/Index', [
            'notifications' => $notifications,
            'filters'       => [
                'group' => $group,
                'days'  => $days,
            ],
        ]);
    }

    public function show(Request $request, JobNotification $jobNotification)
    {
        $user = $request->user();

        if ($jobNotification->recipient_id !== $user->id) {
            abort(403);
        }

        if (is_null($jobNotification->read_at)) {
            $jobNotification->update(['read_at' => now()]);

            // 割当のステータスも「確認済み」に更新する
            if ($jobNotification->assignment_id) {
                try {
                    $assignment = \App\Models\ProjectJobAssignment::find($jobNotification->assignment_id);
                    if ($assignment && ! $assignment->read_at) {
                        $assignment->read_at = now();
                        if (\Illuminate\Support\Facades\Schema::hasTable('statuses')
                            && \Illuminate\Support\Facades\Schema::hasColumn('project_job_assignments', 'status_id')
                            && ! $assignment->completed
                        ) {
                            $status = \Illuminate\Support\Facades\DB::table('statuses')
                                ->where('key', 'confirmed')
                                ->first();
                            if (! $status) {
                                $status = (object) [
                                    'id' => \Illuminate\Support\Facades\DB::table('statuses')->insertGetId([
                                        'key' => 'confirmed', 'name' => '確認済み',
                                        'created_at' => now(), 'updated_at' => now(),
                                    ]),
                                ];
                            }
                            $assignment->status_id = $status->id;
                        }
                        $assignment->save();

                        // JobAssignmentMessage も既読にする
                        JobAssignmentMessage::where('project_job_assignment_id', $assignment->id)
                            ->whereNull('read_at')
                            ->update(['read_at' => now()]);
                    }
                } catch (\Throwable $e) {
                    // 非致命的：通知の既読は完了しているので握りつぶす
                }
            }
        }

        $type         = $jobNotification->type;
        $projectJobId = $jobNotification->project_job_id;
        $assignmentId = $jobNotification->assignment_id;

        // new_job（自分へのジョブ依頼）→ JobBox 詳細へ
        // completed（自分が依頼主）→ Coordinator 側 JobBox 詳細へ
        if (in_array($type, ['new_job', 'completed']) && $assignmentId) {
            $message = \App\Models\JobAssignmentMessage::where('project_job_assignment_id', $assignmentId)
                ->orderBy('id')
                ->first();

            if ($message) {
                $isCoordinatorSide = in_array($type, ['completed'])
                    || $user->isCoordinator() || $user->isClerk()
                    || $user->isAdmin() || $user->isSuperAdmin();

                $jobboxRoute = $isCoordinatorSide
                    ? 'project_jobs.jobbox.show'
                    : 'user.project_jobs.jobbox.show';

                try {
                    return redirect()->route($jobboxRoute, [
                        'projectJob' => $projectJobId,
                        'message'    => $message->id,
                    ])->with('from', 'project');
                } catch (\Throwable $e) {
                    // fallthrough to project_job show
                }
            }

            // JobAssignmentMessage がない場合（進行管理表経由の自己割当等）→ MyJobBox へ
            if ($type === 'new_job') {
                try {
                    return redirect()->route('user.myjobbox.show', ['assignment' => $assignmentId]);
                } catch (\Throwable $e) {
                    // fallthrough
                }
            }
        }

        // new_job_info / completed_info / progress_* / fallback → 案件詳細
        $projectJobRoute = match (true) {
            $user->isAdmin() || $user->isSuperAdmin() => 'coordinator.project_jobs.show',
            $user->isCoordinator() || $user->isClerk() => 'coordinator.project_jobs.show',
            $user->isLeader() => 'leader.project_jobs.show',
            default => 'user.project_jobs.show',
        };

        try {
            return redirect()->route($projectJobRoute, ['projectJob' => $projectJobId]);
        } catch (\Throwable $e) {
            return redirect()->route('coordinator.project_jobs.show', ['projectJob' => $projectJobId]);
        }
    }
}
