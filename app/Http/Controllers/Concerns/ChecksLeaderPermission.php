<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Auth;

trait ChecksLeaderPermission
{
    /**
     * Leader ロールのユーザーに対して権限フラグを確認し、
     * 権限がない場合は 403 を返す。
     * SuperAdmin / Admin ロールは通す。
     * 権限レコード未設定（null）の場合は全権限オン扱い。
     */
    protected function requireLeaderPermission(string $key): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(403);
        }

        // SuperAdmin・Admin はチェック対象外
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return;
        }

        // Leader ロール以外はこのチェック対象外
        if (! $user->isLeader()) {
            return;
        }

        // 権限レコードが未設定の場合は全権限オン扱い
        $permission = $user->leaderPermission;
        if ($permission === null) {
            return;
        }

        if (! $permission->{$key}) {
            abort(403, '権限がありません。');
        }
    }
}
