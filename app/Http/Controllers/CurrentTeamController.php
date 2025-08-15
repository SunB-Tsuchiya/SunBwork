<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Team;

class CurrentTeamController extends Controller
{
    /**
     * Update the user's current team.
     */
    public function update(Request $request)
    {
        $request->validate([
            'team_id' => 'required|exists:teams,id',
        ]);

        $user = Auth::user();
        $teamId = $request->team_id;

        // ユーザーが指定されたチームのメンバーかどうかを確認
        $team = Team::whereHas('users', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->findOrFail($teamId);

        // 現在のチームを更新
        $user->current_team_id = $teamId;
        $user->save();

        // チーム切り替え後は適切なダッシュボードにリダイレクト
        return $this->redirectToDashboard($user);
    }

    /**
     * ユーザーの権限に基づいて適切なダッシュボードにリダイレクト
     */
    private function redirectToDashboard($user)
    {
        // 管理者権限を持つ場合は管理者ダッシュボードへ
        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        // リーダー権限を持つ場合はリーダーダッシュボードへ
        if ($user->isLeader()) {
            return redirect()->route('leader.dashboard');
        }

        // コーディネーター権限を持つ場合はコーディネーターダッシュボードへ
        if ($user->isCoordinator()) {
            return redirect()->route('coordinator.dashboard');
        }

        // 一般ユーザーはユーザーダッシュボードへ
        return redirect()->route('user.dashboard');
    }
}
