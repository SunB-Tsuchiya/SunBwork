<?php

namespace App\Http\Controllers\SalesAnalysis\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * 売上分析ルートは superadmin/admin/clerk の各ロールグループ内に複製登録されている
 * （AppLayoutのcurrentRouteContextがルート名プレフィックスでロールタブを判定するため）。
 * Vue側がroute()を組み立てる際に使うプレフィックスをここで解決する。
 */
trait ResolvesSalesAnalysisRoutePrefix
{
    private function salesAnalysisRoutePrefix(): string
    {
        $user = Auth::user();

        return match (true) {
            $user->isSuperAdmin() => 'superadmin',
            $user->isAdmin() => 'admin',
            $user->isClerk() => 'clerk',
            default => 'superadmin',
        };
    }
}
