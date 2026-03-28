<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Auth;

trait ChecksAdminPermission
{
    /**
     * Admin ロールのユーザーに対して権限フラグを確認し、
     * 権限がない場合は 403 を返す。
     * SuperAdmin および権限レコード未設定（null）の場合は通す。
     */
    protected function requireAdminPermission(string $key): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(403);
        }

        // SuperAdmin はすべて通す
        if ($user->isSuperAdmin()) {
            return;
        }

        // Admin ロール以外はこのチェック対象外
        if (! $user->isAdmin()) {
            return;
        }

        // 権限レコードが未設定の場合は全権限オン扱い
        $permission = $user->adminPermission;
        if ($permission === null) {
            return;
        }

        if (! $permission->{$key}) {
            abort(403, '権限がありません。');
        }
    }
}
