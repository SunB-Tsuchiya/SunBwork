<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\Diary;
use App\Models\Event;
use App\Models\ProjectJobAssignment;
use App\Models\UserMonthlyBreak;
use App\Models\UserMonthlySchedule;
use App\Models\Worktype;
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
            $events = $events->map(function ($e) {
                $arr = $e->toArray();
                $startVal = $e->start ?? ($arr['start'] ?? null);
                if (empty($startVal) && isset($arr['starts_at'])) $startVal = $arr['starts_at'];
                if (empty($startVal) && isset($arr['startsAt'])) $startVal = $arr['startsAt'];
                $endVal = $e->end ?? ($arr['end'] ?? null);
                if (empty($endVal) && isset($arr['ends_at'])) $endVal = $arr['ends_at'];
                if (empty($endVal) && isset($arr['endsAt'])) $endVal = $arr['endsAt'];
                $descVal = $e->description ?? ($arr['description'] ?? null);
                if (empty($descVal) && isset($arr['body'])) $descVal = $arr['body'];
                return [
                    'id'                           => $e->id,
                    'title'                        => $e->title,
                    'start'                        => $startVal,
                    'end'                          => $endVal,
                    'allDay'                       => $arr['allDay'] ?? false,
                    'description'                  => $descVal,
                    'color'                        => $arr['color'] ?? ($e->color ?? null),
                    'project_job_assignment_id'    => $arr['project_job_assignment_id'] ?? ($e->project_job_assignment_id ?? null),
                    'extendedProps'                => array_merge($arr['extendedProps'] ?? [], [
                        'project_job_assignment_id' => $arr['project_job_assignment_id'] ?? ($e->project_job_assignment_id ?? null),
                        'description'               => $descVal,
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
