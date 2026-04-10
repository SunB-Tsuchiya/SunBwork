<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\UserMonthlyBreak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class UserDailyBreakController extends Controller
{
    /**
     * 週間休憩設定を保存（月次 JSON に upsert）
     * Body: { days: [{ date: 'YYYY-MM-DD', break_start: 'HH:MM'|null, break_end: 'HH:MM'|null }] }
     * break_start / break_end が両方 null → その日の休憩設定を削除
     */
    public function store(Request $request)
    {
        $request->validate([
            'days'               => 'required|array|max:14',
            'days.*.date'        => 'required|date_format:Y-m-d',
            'days.*.break_start' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'days.*.break_end'   => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
        ]);

        if (!Schema::hasTable('user_monthly_breaks')) {
            \Illuminate\Support\Facades\Log::error('user_monthly_breaks table not found. Run: php artisan migrate');
            return response()->json(['error' => 'migration_required'], 503);
        }

        $user = Auth::user();

        // 月ごとにグループ化して JSON を更新
        $byMonth = collect($request->days)->groupBy(fn($d) => substr($d['date'], 0, 7));

        foreach ($byMonth as $ym => $days) {
            $monthly  = UserMonthlyBreak::firstOrNew(
                ['user_id' => $user->id, 'year_month' => $ym],
                ['schedule' => []]
            );
            $schedule = $monthly->schedule ?? [];

            foreach ($days as $day) {
                $dd = substr($day['date'], 8, 2); // 'DD'
                if (empty($day['break_start']) || empty($day['break_end'])) {
                    unset($schedule[$dd]);
                } else {
                    $schedule[$dd] = [
                        'start' => $day['break_start'],
                        'end'   => $day['break_end'],
                    ];
                }
            }

            if (empty($schedule)) {
                if ($monthly->exists) $monthly->delete();
            } else {
                $monthly->schedule = $schedule;
                $monthly->save();
            }
        }

        return response()->json(['ok' => true]);
    }
}
