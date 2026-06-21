<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Department;
use App\Models\EventItemType;
use App\Models\MeetingDefinition;
use App\Models\MeetingRoom;
use App\Models\UserMonthlyBreak;
use App\Models\UserMonthlySchedule;
use App\Models\Worktype;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class CalendarController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $worktypes       = [];
        $dailyWorktypes  = [];
        $dailyBreaks     = [];
        $defaultBreak    = ['start' => '12:00', 'end' => '13:00'];
        $defaultWorktype = null;
        $eventItemTypes  = [];
        $meetingDefinitions = [];
        $rooms           = [];
        $companies       = [];
        $departments     = [];

        if ($user) {
            // 勤務形態一覧
            try {
                $wq = Worktype::orderBy('sort_order');
                if ($user->company_id) {
                    $wq->where('company_id', $user->company_id);
                }
                $worktypes = $wq->get(['id', 'name', 'start_time', 'end_time'])->toArray();
            } catch (\Throwable $e) {
                Log::error('CalendarController worktypes: ' . $e->getMessage());
            }

            // ユーザー設定（デフォルト勤務形態・休憩）
            try {
                $setting = $user->userSetting()->with('worktype')->first();
                if ($setting?->worktype) {
                    $defaultWorktype = [
                        'id'         => $setting->worktype->id,
                        'name'       => $setting->worktype->name,
                        'start_time' => $setting->worktype->start_time,
                        'end_time'   => $setting->worktype->end_time,
                    ];
                }
                if ($setting?->lunch_start && $setting?->lunch_end) {
                    $defaultBreak = ['start' => $setting->lunch_start, 'end' => $setting->lunch_end];
                }
            } catch (\Throwable $e) {
                Log::error('CalendarController userSetting: ' . $e->getMessage());
            }

            // 日ごと勤務形態（±3ヶ月）
            try {
                $fromYm = now()->subMonths(3)->format('Y-m');
                $toYm   = now()->addMonths(3)->format('Y-m');
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
                Log::error('CalendarController dailyWorktypes: ' . $e->getMessage());
            }

            // 日ごと休憩設定（±3ヶ月）
            try {
                $fromYm = now()->subMonths(3)->format('Y-m');
                $toYm   = now()->addMonths(3)->format('Y-m');
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
                Log::error('CalendarController dailyBreaks: ' . $e->getMessage());
            }

            // EventModal 用データ（Schedule と同一）
            try {
                $eventItemTypes = EventItemType::orderBy('id')->get(['id', 'name', 'slug'])->toArray();
            } catch (\Throwable $e) {
                Log::error('CalendarController eventItemTypes: ' . $e->getMessage());
            }

            try {
                $meetingDefinitions = MeetingDefinition::where('company_id', $user->company_id)
                    ->orderBy('id')
                    ->get()
                    ->toArray();
            } catch (\Throwable $e) {
                Log::error('CalendarController meetingDefinitions: ' . $e->getMessage());
            }

            try {
                $rooms = MeetingRoom::where('company_id', $user->company_id)
                    ->orderBy('sort_order')
                    ->get()
                    ->toArray();
            } catch (\Throwable $e) {
                Log::error('CalendarController rooms: ' . $e->getMessage());
            }

            try {
                $companies   = Company::orderBy('name')->get(['id', 'name'])->toArray();
                $departments = Department::orderBy('sort_order')->get(['id', 'name', 'company_id'])->toArray();
            } catch (\Throwable $e) {
                Log::error('CalendarController companies/departments: ' . $e->getMessage());
            }
        }

        return Inertia::render('Calendar', [
            'user'               => $user,
            'eventItemTypes'     => $eventItemTypes,
            'meetingDefinitions' => $meetingDefinitions,
            'rooms'              => $rooms,
            'companies'          => $companies,
            'departments'        => $departments,
            'worktypes'          => $worktypes,
            'dailyWorktypes'     => $dailyWorktypes,
            'dailyBreaks'        => $dailyBreaks,
            'defaultBreak'       => $defaultBreak,
            'defaultWorktype'    => $defaultWorktype,
        ]);
    }
}
