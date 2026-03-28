<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\UserMonthlySchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserDailyWorktypeController extends Controller
{
    /**
     * 週間日程を保存（月次 JSON に upsert）
     * Body: { days: [{ date: 'YYYY-MM-DD', worktype_id: int|null }] }
     */
    public function store(Request $request)
    {
        $request->validate([
            'days'               => 'required|array|max:14',
            'days.*.date'        => 'required|date_format:Y-m-d',
            'days.*.worktype_id' => 'nullable|integer|exists:worktypes,id',
        ]);

        $user = Auth::user();

        // 月ごとにグループ化して JSON を更新
        $byMonth = collect($request->days)->groupBy(fn($d) => substr($d['date'], 0, 7));

        foreach ($byMonth as $ym => $days) {
            $monthly  = UserMonthlySchedule::firstOrNew(
                ['user_id' => $user->id, 'year_month' => $ym],
                ['schedule' => []]
            );
            $schedule = $monthly->schedule ?? [];

            foreach ($days as $day) {
                $dd = substr($day['date'], 8, 2); // 'DD'
                if (empty($day['worktype_id'])) {
                    unset($schedule[$dd]);
                } else {
                    $schedule[$dd] = $day['worktype_id'];
                }
            }

            if (empty($schedule)) {
                $monthly->exists ? $monthly->delete() : null;
            } else {
                $monthly->schedule = $schedule;
                $monthly->save();
            }
        }

        return response()->json(['ok' => true]);
    }
}
