<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Models\Diary;
use App\Models\Event;
use App\Models\ProjectJobAssignment;
use App\Models\ProjectJobAssignmentByMyself;
use App\Models\ProgressCell;
use App\Models\UserMonthlySchedule;
use App\Models\Worktype;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Eager load related models so Vue pages can safely access them without extra queries
        $user = Auth::user()->load([
            'company',
            'department',
            'assignment',
            'teams.company',
            'teams.department',
            'currentTeam.company',
            'currentTeam.department',
        ]);

        // Build a simple current_team payload (company/department names included) for the frontend
        $currentTeam = $user->currentTeam;
        if ($currentTeam) {
            $user->current_team = [
                'id' => $currentTeam->id,
                'name' => $currentTeam->name,
                'team_type' => $currentTeam->team_type,
                'company_name' => $currentTeam->company->name ?? null,
                'department_name' => $currentTeam->department->name ?? null,
            ];
        } else {
            $user->current_team = null;
        }

        // available_teams: include loaded teams (with company/department relations)
        $user->available_teams = $user->teams->map(function ($t) {
            return [
                'id' => $t->id,
                'name' => $t->name,
                'team_type' => $t->team_type,
                'company' => $t->company ? ['id' => $t->company->id, 'name' => $t->company->name] : null,
                'department' => $t->department ? ['id' => $t->department->id, 'name' => $t->department->name] : null,
            ];
        })->toArray();

        // URLパスでVueコンポーネントを振り分け
        $path = $request->path();
        if (str_starts_with($path, 'superadmin/')) {
            $component = 'SuperAdmin/Dashboard';
        } elseif (str_starts_with($path, 'admin/')) {
            $component = 'Admin/Dashboard';
        } elseif (str_starts_with($path, 'leader/')) {
            $component = 'Leader/Dashboard';
        } elseif (str_starts_with($path, 'coordinator/')) {
            $component = 'Coordinator/Dashboard';
        } elseif (str_starts_with($path, 'clerk/')) {
            $component = 'Clerk/Dashboard';
        } elseif (str_starts_with($path, 'user/')) {
            $component = 'Dashboard';
        } else {
            // ログイン直後など: user_roleで自動リダイレクト
            if ($user->user_role === 'superadmin') {
                return redirect()->route('superadmin.dashboard');
            } elseif ($user->user_role === 'admin') {
                return redirect()->route('admin.dashboard');
            } elseif ($user->user_role === 'leader') {
                return redirect()->route('leader.dashboard');
            } elseif ($user->user_role === 'coordinator') {
                return redirect()->route('coordinator.dashboard');
            } elseif ($user->user_role === 'clerk') {
                return redirect()->route('clerk.dashboard');
            } else {
                $component = 'Dashboard';
            }
        }

        // カレンダー用データ（User Dashboard のみ）
        $diaries = [];
        $events = [];
        $jobs = [];
        if ($component === 'Dashboard') {
            $diary_from = now()->subDays(20)->startOfDay();
            $diary_to   = now()->endOfDay();
            $event_from = now()->subMonth(1)->startOfMonth();
            $event_to   = now()->addMonth(1)->endOfMonth();

            $diaries = Diary::where('user_id', $user->id)
                ->when(Schema::hasColumn('diaries', 'date'), fn($q) =>
                    $q->whereBetween('date', [$diary_from, $diary_to])
                )
                ->get();

            $eventQuery = Event::where('user_id', $user->id);
            $select = ['id', 'title'];
            if (Schema::hasColumn('events', 'starts_at')) {
                $eventQuery->whereBetween('starts_at', [$event_from, $event_to]);
                $select[] = 'starts_at';
            } elseif (Schema::hasColumn('events', 'start')) {
                $eventQuery->whereBetween('start', [$event_from, $event_to]);
                $select[] = 'start';
            }
            foreach (['ends_at', 'end', 'body', 'description', 'project_job_assignment_id'] as $col) {
                if (Schema::hasColumn('events', $col)) $select[] = $col;
            }
            $rawEvents = $eventQuery->get($select);

            // CalendarController と同じ色判定ロジック: is_self_assigned / has_progress_cell を付与
            $assignmentIds = $rawEvents->pluck('project_job_assignment_id')->filter()->unique()->values()->all();
            $progressAssignmentIds = [];
            $proofAssignmentIds = [];
            $assignmentSenders = [];
            if (!empty($assignmentIds)) {
                try {
                    $progressAssignmentIds = ProgressCell::whereIn('assignment_id', $assignmentIds)
                        ->pluck('assignment_id')->map(fn($v) => (int)$v)->all();
                } catch (\Throwable $ex) {
                    Log::error('DashboardController progressAssignmentIds error: ' . $ex->getMessage());
                }
                try {
                    $proofAssignmentIds = ProjectJobAssignment::whereIn('id', $assignmentIds)
                        ->where('job_type', 'proof')
                        ->pluck('id')->map(fn($v) => (int)$v)->all();
                } catch (\Throwable $ex) {
                    Log::error('DashboardController proofAssignmentIds error: ' . $ex->getMessage());
                }
                try {
                    $senders = ProjectJobAssignment::whereIn('id', $assignmentIds)
                        ->pluck('sender_id', 'id')->map(fn($v) => $v === null ? null : (int)$v)->all();
                } catch (\Throwable $ex) {
                    $senders = [];
                }
                try {
                    $bySenders = [];
                    if (class_exists(ProjectJobAssignmentByMyself::class)) {
                        $bySenders = ProjectJobAssignmentByMyself::whereIn('id', $assignmentIds)
                            ->pluck('sender_id', 'id')->map(fn($v) => $v === null ? null : (int)$v)->all();
                    }
                } catch (\Throwable $ex) {
                    $bySenders = [];
                }
                foreach ($senders as $k => $v) $assignmentSenders[$k] = $v;
                foreach ($bySenders as $k => $v) $assignmentSenders[$k] = $v;
                foreach ($assignmentSenders as $k => $v) {
                    $assignmentSenders[$k] = $v === null ? null : (int)$v;
                }
            }

            // 校正ジョブ（pja101: source_assignment_id または coordinator_assignment_id が設定）は
            // 自己割当でなく依頼ジョブとして扱い、紫色表示にする
            $assignmentFlags = [];
            if (!empty($assignmentIds)) {
                try {
                    $metaRows = ProjectJobAssignment::whereIn('id', $assignmentIds)
                        ->get(['id', 'source_assignment_id', 'coordinator_assignment_id'])
                        ->keyBy('id')
                        ->toArray();
                    foreach ($metaRows as $k => $r) {
                        $assignmentFlags[$k] = [
                            'has_source'      => !empty($r['source_assignment_id']),
                            'has_coordinator' => !empty($r['coordinator_assignment_id']),
                        ];
                    }
                } catch (\Throwable $ex) {
                    Log::error('DashboardController assignmentFlags error: ' . $ex->getMessage());
                }
            }

            $events = $rawEvents->map(function ($e) use ($progressAssignmentIds, $proofAssignmentIds, $assignmentSenders, $user, $assignmentFlags) {
                $arr = $e->toArray();
                $startVal = $e->start ?? $arr['start'] ?? $arr['starts_at'] ?? $arr['startsAt'] ?? null;
                $endVal   = $e->end   ?? $arr['end']   ?? $arr['ends_at']   ?? $arr['endsAt']   ?? null;
                $descVal  = $e->description ?? $arr['description'] ?? $arr['body'] ?? null;
                $pjaId    = $arr['project_job_assignment_id'] ?? ($e->project_job_assignment_id ?? null);
                $hasProgress = $pjaId ? in_array((int)$pjaId, $progressAssignmentIds, true) : false;
                // source_assignment_id / coordinator_assignment_id ありのジョブ（pja101等）は紫色表示
                if (!$hasProgress && $pjaId && isset($assignmentFlags[$pjaId])) {
                    $hasProgress = !empty($assignmentFlags[$pjaId]['has_source'])
                                || !empty($assignmentFlags[$pjaId]['has_coordinator']);
                }
                $isSelfAssigned = false;
                if ($pjaId && isset($assignmentSenders[$pjaId])) {
                    $senderId = $assignmentSenders[$pjaId];
                    $isSelfAssigned = $senderId !== null && $senderId === ($user ? $user->id : null);
                }
                $isProofJob = $pjaId ? in_array((int)$pjaId, $proofAssignmentIds ?? [], true) : false;
                $color = $arr['color'] ?? ($e->color ?? null);
                if (empty($color)) {
                    if ($hasProgress) $color = '#7C3AED';
                    elseif ($isProofJob) $color = '#DB2777';
                    elseif ($isSelfAssigned) $color = '#4F46E5';
                    else $color = '#059669';
                }
                return [
                    'id'                         => $e->id,
                    'title'                      => $e->title,
                    'start'                      => $startVal,
                    'end'                        => $endVal,
                    'allDay'                     => $arr['allDay'] ?? false,
                    'description'                => $descVal,
                    'color'                      => $color,
                    'project_job_assignment_id'  => $pjaId,
                    'extendedProps'              => array_merge($arr['extendedProps'] ?? [], [
                        'project_job_assignment_id' => $pjaId,
                        'description'               => $descVal,
                        'has_progress_cell'          => $hasProgress,
                        'is_self_assigned'           => $isSelfAssigned,
                    ]),
                ];
            })->values();

            $jobs = ProjectJobAssignment::where('user_id', $user->id)
                ->where(fn($q) => $q->where('accepted', true)->orWhere('assigned', true))
                ->with('projectJob')
                ->get()
                ->map(fn($a) => [
                    'id'             => $a->id,
                    'title'          => $a->title ?: ($a->projectJob?->name ?? '無題'),
                    'preferred_date' => $a->desired_start_date?->format('Y-m-d'),
                ]);
        }

        // ユーザー設定からカレンダー表示モードと基本勤務形態を取得（User Dashboard のみ）
        $calendarView    = 'timeGridWeek';
        $defaultWorktype = null;
        $worktypes       = [];
        $dailyWorktypes  = [];

        if ($component === 'Dashboard') {
            // 会社の勤務形態一覧
            try {
                $worktypeQuery = Worktype::orderBy('sort_order');
                if ($user->company_id) {
                    $worktypeQuery->where('company_id', $user->company_id);
                }
                $worktypes = $worktypeQuery->get(['id', 'name', 'start_time', 'end_time'])->toArray();
            } catch (\Throwable $e) {
                Log::error('DashboardController worktypes error: ' . $e->getMessage());
            }

            // ユーザー設定
            try {
                $setting = $user->userSetting()->with('worktype')->first();
                if ($setting) {
                    if ($setting->calendar_view) {
                        $calendarView = $setting->calendar_view;
                    }
                    if ($setting->worktype) {
                        $defaultWorktype = [
                            'id'         => $setting->worktype->id,
                            'name'       => $setting->worktype->name,
                            'start_time' => $setting->worktype->start_time,
                            'end_time'   => $setting->worktype->end_time,
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::error('DashboardController userSetting error: ' . $e->getMessage());
            }

            // 日ごと勤務形態（±3ヶ月）：月次 JSON を展開
            try {
                $fromYm = now()->subMonths(3)->format('Y-m');
                $toYm   = now()->addMonths(3)->format('Y-m');
                $dailyWorktypes = [];
                UserMonthlySchedule::where('user_id', $user->id)
                    ->whereBetween('year_month', [$fromYm, $toYm])
                    ->get(['year_month', 'schedule'])
                    ->each(function ($ms) use (&$dailyWorktypes) {
                        foreach (($ms->schedule ?? []) as $dd => $worktypeId) {
                            if ($worktypeId) {
                                $dailyWorktypes[] = [
                                    'date'        => $ms->year_month . '-' . $dd,
                                    'worktype_id' => (int) $worktypeId,
                                ];
                            }
                        }
                    });
            } catch (\Throwable $e) {
                Log::error('DashboardController dailyWorktypes error: ' . $e->getMessage());
                $dailyWorktypes = [];
            }
        }

        return Inertia::render($component, [
            'user'            => $user,
            'diaries'         => $diaries,
            'events'          => $events,
            'jobs'            => $jobs,
            'calendarView'    => $calendarView,
            'defaultWorktype' => $defaultWorktype,
            'worktypes'       => $worktypes,
            'dailyWorktypes'  => $dailyWorktypes,
        ]);
    }
}
