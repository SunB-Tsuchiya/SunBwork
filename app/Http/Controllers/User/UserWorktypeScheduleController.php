<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\UserMonthlySchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * カレンダーの「週間日程設定」（日別の勤務形態）を保存するコントローラー。
 *
 * 実データは `user_monthly_schedules`（1ユーザー×1ヶ月＝1行、schedule は
 * {"01": worktype_id, "15": worktype_id, ...} の JSON）に保存する。
 *
 * 旧実装は日別1行の `user_daily_worktypes` テーブルと `UserDailyWorktype` モデルを
 * 使っていたが、行数削減のため 2026-03-28 の migration
 * `replace_daily_worktypes_with_monthly_schedules` で月次 JSON 方式へ移行し、
 * テーブルとモデルは削除済み。
 *
 * ルート名（`user.daily_worktypes.store`）と URL（`/user/daily-worktypes`）は
 * 「日別の勤務形態を保存する」という機能を表す名前として維持している。
 */
class UserWorktypeScheduleController extends Controller
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

        // user_monthly_schedules テーブルが未マイグレーションの場合は 503 を返す
        if (!\Illuminate\Support\Facades\Schema::hasTable('user_monthly_schedules')) {
            \Illuminate\Support\Facades\Log::error('user_monthly_schedules table not found. Run: php artisan migrate');
            return response()->json(['error' => 'migration_required'], 503);
        }

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
