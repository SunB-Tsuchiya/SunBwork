<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserLunchBreakController extends Controller
{
    /**
     * ランチブレーク設定を保存する
     * - start / end を "HH:MM" 形式で受け取る
     * - 制約: 60分以内（end - start <= 60min）
     */
    public function store(Request $request)
    {
        $request->validate([
            'start' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'end'   => ['required', 'regex:/^\d{2}:\d{2}$/'],
        ]);

        $start = $request->input('start');
        $end   = $request->input('end');

        // 時間計算
        [$sh, $sm] = array_map('intval', explode(':', $start));
        [$eh, $em] = array_map('intval', explode(':', $end));
        $startMins = $sh * 60 + $sm;
        $endMins   = $eh * 60 + $em;

        if ($endMins <= $startMins) {
            return response()->json(['error' => '終了時刻は開始時刻より後にしてください'], 422);
        }
        if (($endMins - $startMins) > 60) {
            return response()->json(['error' => '休憩時間は60分を超えることができません'], 422);
        }

        $user = Auth::user();
        $user->userSetting()->updateOrCreate(
            ['user_id' => $user->id],
            ['lunch_start' => $start, 'lunch_end' => $end]
        );

        return response()->json(['start' => $start, 'end' => $end]);
    }
}
