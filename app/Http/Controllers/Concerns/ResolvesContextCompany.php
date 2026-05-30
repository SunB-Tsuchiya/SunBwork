<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Auth;

trait ResolvesContextCompany
{
    /**
     * 現在のリクエストにおける「有効な会社 ID」を返す。
     *
     * - SuperAdmin: セッションのコンテキスト company_id を返す（null = グローバルモード）
     * - 一般ユーザー: 自分の company_id を返す
     */
    protected function contextCompanyId(): ?int
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }
        if ($user->isSuperAdmin()) {
            return session('superadmin_context.company_id');
        }
        return $user->company_id;
    }
}
