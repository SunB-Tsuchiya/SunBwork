<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\Diary;
use App\Models\Event;
use App\Models\ProjectJobAssignment;
use App\Models\ProjectJobAssignmentByMyself;
use App\Models\UserMonthlyBreak;
use App\Models\UserMonthlySchedule;
use App\Models\Worktype;
use App\Models\ProgressCell;
use Illuminate\Support\Facades\Schema;

class CalendarController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $diaries = [];
        $events = [];
        if ($user) {
            $diary_from = now()->subDays(20)->startOfDay();
            $diary_to = now()->endOfDay();
            $event_from = now()->subMonth(1)->startOfMonth();
            $event_to = now()->addMonth(1)->endOfMonth();
            $diaryQuery = Diary::where('user_id', $user->id);
            if (Schema::hasColumn('diaries', 'date')) {
                $diaryQuery->where('date', '>=', $diary_from)->where('date', '<=', $diary_to);
            }
            $diaries = $diaryQuery->get();
            $eventQuery = Event::where('user_id', $user->id);
            $select = ['id', 'title'];
            if (Schema::hasColumn('events', 'starts_at')) {
                $eventQuery->where('starts_at', '>=', $event_from)->where('starts_at', '<=', $event_to);
                $select[] = 'starts_at';
            } elseif (Schema::hasColumn('events', 'start')) {
                $eventQuery->where('start', '>=', $event_from)->where('start', '<=', $event_to);
                $select[] = 'start';
            }
            if (Schema::hasColumn('events', 'ends_at')) {
                $select[] = 'ends_at';
            } elseif (Schema::hasColumn('events', 'end')) {
                $select[] = 'end';
            }
            if (Schema::hasColumn('events', 'body')) {
                $select[] = 'body';
            } elseif (Schema::hasColumn('events', 'description')) {
                $select[] = 'description';
            }
            if (Schema::hasColumn('events', 'project_job_assignment_id')) {
                $select[] = 'project_job_assignment_id';
            }
            $events = $eventQuery->get($select);
            // Determine which project_job_assignments referenced by events have progress cells
            $assignmentIds = $events->pluck('project_job_assignment_id')->filter()->unique()->values()->all();
            $progressAssignmentIds = [];
            // 校正ジョブ（job_type='proof'）の assignment ID を取得（UTC 保存の時刻を正しく変換するため）
            $proofAssignmentIds = [];
            if (!empty($assignmentIds)) {
                try {
                    $proofAssignmentIds = ProjectJobAssignment::whereIn('id', $assignmentIds)
                        ->where('job_type', 'proof')
                        ->pluck('id')
                        ->map(fn($v) => (int)$v)
                        ->all();
                } catch (\Throwable $ex) {
                    \Illuminate\Support\Facades\Log::error('CalendarController proofAssignmentIds error: ' . $ex->getMessage());
                }
            }
            if (!empty($assignmentIds)) {
                try {
                    $progressAssignmentIds = ProgressCell::whereIn('assignment_id', $assignmentIds)->pluck('assignment_id')->map(fn($v) => (int)$v)->all();
                } catch (\Throwable $ex) {
                    \Illuminate\Support\Facades\Log::error('CalendarController progressAssignmentIds error: ' . $ex->getMessage());
                }
            }

            // load basic assignment info (sender_id) to allow self-assigned detection
            // include both canonical assignments and user-created (by_myself) assignments
            $assignmentSenders = [];
            if (!empty($assignmentIds)) {
                $senders = [];
                try {
                    $senders = ProjectJobAssignment::whereIn('id', $assignmentIds)->pluck('sender_id', 'id')->map(fn($v) => $v === null ? null : (int)$v)->all();
                } catch (\Throwable $ex) {
                    \Illuminate\Support\Facades\Log::error('CalendarController assignmentSenders error: ' . $ex->getMessage());
                }

                // also check the by_myself table if present (user-created assignments)
                $bySenders = [];
                try {
                    if (class_exists(\App\Models\ProjectJobAssignmentByMyself::class)) {
                        $bySenders = ProjectJobAssignmentByMyself::whereIn('id', $assignmentIds)->pluck('sender_id', 'id')->map(fn($v) => $v === null ? null : (int)$v)->all();
                    }
                } catch (\Throwable $ex) {
                    \Illuminate\Support\Facades\Log::error('CalendarController by_myself assignmentSenders error: ' . $ex->getMessage());
                }

                // Also load assignment metadata (source_assignment_id / supersedes_assignment_id)
                $assignmentFlags = [];
                try {
                    $metaRows = ProjectJobAssignment::whereIn('id', $assignmentIds)->get(['id', 'source_assignment_id', 'supersedes_assignment_id'])->keyBy('id')->toArray();
                    foreach ($metaRows as $k => $r) {
                        $assignmentFlags[$k] = [
                            'has_source' => !empty($r['source_assignment_id']),
                            'has_supersedes' => !empty($r['supersedes_assignment_id']),
                        ];
                    }
                    if (class_exists(\App\Models\ProjectJobAssignmentByMyself::class)) {
                        $metaBy = ProjectJobAssignmentByMyself::whereIn('id', $assignmentIds)->get(['id', 'source_assignment_id', 'supersedes_assignment_id'])->keyBy('id')->toArray();
                        foreach ($metaBy as $k => $r) {
                            $assignmentFlags[$k] = [
                                'has_source' => !empty($r['source_assignment_id']),
                                'has_supersedes' => !empty($r['supersedes_assignment_id']),
                            ];
                        }
                    }
                } catch (\Throwable $ex) {
                    \Illuminate\Support\Facades\Log::error('CalendarController assignmentFlags error: ' . $ex->getMessage());
                }

                // merge—values from by_myself override canonical if present
                // preserve assignment id keys (avoid array_merge which reindexes numeric keys)
                $assignmentSenders = [];
                if (is_array($senders)) {
                    foreach ($senders as $k => $v) {
                        $assignmentSenders[$k] = $v;
                    }
                }
                if (is_array($bySenders)) {
                    foreach ($bySenders as $k => $v) {
                        // by_myself overrides canonical
                        $assignmentSenders[$k] = $v;
                    }
                }
            }
                
                // $assignmentSenders already contains merged sender ids from canonical and by_myself tables.
                // Ensure keys are preserved and values are cast to int (avoid array_map which reindexes keys).
                if (!empty($assignmentSenders) && is_array($assignmentSenders)) {
                    foreach ($assignmentSenders as $k => $v) {
                        $assignmentSenders[$k] = $v === null ? null : (int)$v;
                    }
                } else {
                    $assignmentSenders = [];
                }

            // controller start

            $events = $events->map(function ($e) use ($progressAssignmentIds, $assignmentSenders, $user, $assignmentFlags, $proofAssignmentIds) {
                $arr = $e->toArray();
                $pjIdForProof = $arr['project_job_assignment_id'] ?? ($e->project_job_assignment_id ?? null);
                $isProofEvent = $pjIdForProof && in_array((int)$pjIdForProof, $proofAssignmentIds, true);
                // 校正ジョブは starts_at が UTC 保存のため、getRawOriginal で UTC として変換した ISO 文字列を使う
                if ($isProofEvent) {
                    $rawStart = $e->getRawOriginal('starts_at');
                    $rawEnd   = $e->getRawOriginal('ends_at');
                    $startVal = $rawStart ? \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $rawStart, 'UTC')->toIso8601String() : null;
                    $endVal   = $rawEnd   ? \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $rawEnd,   'UTC')->toIso8601String() : null;
                } else {
                    $startVal = $e->start ?? ($arr['start'] ?? null);
                    if (empty($startVal) && isset($arr['starts_at'])) $startVal = $arr['starts_at'];
                    if (empty($startVal) && isset($arr['startsAt'])) $startVal = $arr['startsAt'];
                    $endVal = $e->end ?? ($arr['end'] ?? null);
                    if (empty($endVal) && isset($arr['ends_at'])) $endVal = $arr['ends_at'];
                    if (empty($endVal) && isset($arr['endsAt'])) $endVal = $arr['endsAt'];
                }
                $descVal = $e->description ?? ($arr['description'] ?? null);
                if (empty($descVal) && isset($arr['body'])) $descVal = $arr['body'];
                $pjId = $arr['project_job_assignment_id'] ?? ($e->project_job_assignment_id ?? null);
                $hasProgress = false;
                if ($pjId) {
                    $hasProgress = in_array((int)$pjId, $progressAssignmentIds, true);
                    // treat assignments with a source or supersedes link as progress-linked as well
                    if (!$hasProgress && isset($assignmentFlags[$pjId])) {
                        $hasProgress = !empty($assignmentFlags[$pjId]['has_source']) || !empty($assignmentFlags[$pjId]['has_supersedes']);
                    }
                }
                $isSelfAssigned = false;
                if ($pjId) {
                    if (isset($assignmentSenders[$pjId])) {
                        $senderId = $assignmentSenders[$pjId];
                        $isSelfAssigned = $senderId !== null && $senderId === ($user ? $user->id : null);
                    }
                }

                // color mapping: progress -> purple, self-assigned -> indigo, default -> green
                $color = $arr['color'] ?? ($e->color ?? null);
                if (empty($color)) {
                    if ($hasProgress) $color = '#7C3AED';
                    elseif ($isSelfAssigned) $color = '#4F46E5';
                    else $color = '#059669';
                }

                return [
                    'id'                           => $e->id,
                    'title'                        => $e->title,
                    'start'                        => $startVal,
                    'end'                          => $endVal,
                    'allDay'                       => $arr['allDay'] ?? false,
                    'description'                  => $descVal,
                    'color'                        => $color,
                    'project_job_assignment_id'    => $pjId,
                    'extendedProps'                => array_merge($arr['extendedProps'] ?? [], [
                        'project_job_assignment_id' => $pjId,
                        'description'               => $descVal,
                        'has_progress_cell'         => $hasProgress,
                        'is_self_assigned'          => $isSelfAssigned,
                    ]),
                ];
            })->values();

            

            $jobs = ProjectJobAssignment::where('user_id', $user->id)
                ->where(function ($q) {
                    $q->where('accepted', true)->orWhere('assigned', true);
                })
                ->with('projectJob')
                ->get()
                ->map(function ($a) {
                    return [
                        'id'             => $a->id,
                        'title'          => $a->title ?: ($a->projectJob ? $a->projectJob->name : '無題'),
                        'details'        => $a->detail ?? ($a->projectJob ? $a->projectJob->detail : null),
                        'preferred_date' => $a->desired_start_date ? $a->desired_start_date->format('Y-m-d') : null,
                        'scheduled_at'   => null,
                        'assigned_at'    => $a->created_at,
                    ];
                });
        }

        // ユーザー設定からカレンダー表示モードと基本勤務形態を取得
        $calendarView    = 'timeGridWeek';
        $defaultWorktype = null;
        $worktypes       = [];
        $dailyWorktypes  = [];
        $dailyBreaks     = [];
        $defaultBreak    = ['start' => '12:00', 'end' => '13:00'];

        if ($user) {
            // 会社の勤務形態一覧（company_id が null の SuperAdmin は全社分を取得）
            try {
                $worktypeQuery = Worktype::orderBy('sort_order');
                if ($user->company_id) {
                    $worktypeQuery->where('company_id', $user->company_id);
                }
                $worktypes = $worktypeQuery->get(['id', 'name', 'start_time', 'end_time'])->toArray();
                \Illuminate\Support\Facades\Log::info('CalendarController worktypes', [
                    'user_id'        => $user->id,
                    'company_id'     => $user->company_id,
                    'worktypes_count' => count($worktypes),
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('CalendarController worktypes error: ' . $e->getMessage());
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
                \Illuminate\Support\Facades\Log::error('CalendarController userSetting error: ' . $e->getMessage());
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
                \Illuminate\Support\Facades\Log::error('CalendarController dailyWorktypes error: ' . $e->getMessage());
                $dailyWorktypes = [];
            }

            // グローバル休憩設定（user_settings.lunch_start/lunch_end）
            try {
                $setting = $setting ?? $user->userSetting()->first();
                if ($setting && $setting->lunch_start && $setting->lunch_end) {
                    $defaultBreak = ['start' => $setting->lunch_start, 'end' => $setting->lunch_end];
                }
            } catch (\Throwable $e) {
                // non-fatal
            }

            // 日ごと休憩設定（±3ヶ月）
            try {
                $fromYm = now()->subMonths(3)->format('Y-m');
                $toYm   = now()->addMonths(3)->format('Y-m');
                $dailyBreaks = [];
                UserMonthlyBreak::where('user_id', $user->id)
                    ->whereBetween('year_month', [$fromYm, $toYm])
                    ->get(['year_month', 'schedule'])
                    ->each(function ($mb) use (&$dailyBreaks) {
                        foreach (($mb->schedule ?? []) as $dd => $entry) {
                            if (!empty($entry['start']) && !empty($entry['end'])) {
                                $dailyBreaks[] = [
                                    'date'  => $mb->year_month . '-' . $dd,
                                    'start' => $entry['start'],
                                    'end'   => $entry['end'],
                                ];
                            }
                        }
                    });
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('CalendarController dailyBreaks error: ' . $e->getMessage());
                $dailyBreaks = [];
            }
        }

        return Inertia::render('Calendar', [
            'user'            => $user,
            'diaries'         => $diaries,
            'events'          => $events,
            'jobs'            => $jobs ?? [],
            'calendarView'    => $calendarView,
            'defaultWorktype' => $defaultWorktype,
            'worktypes'       => $worktypes,
            'dailyWorktypes'  => $dailyWorktypes,
            'dailyBreaks'     => $dailyBreaks,
            'defaultBreak'    => $defaultBreak,
        ]);
    }
}
