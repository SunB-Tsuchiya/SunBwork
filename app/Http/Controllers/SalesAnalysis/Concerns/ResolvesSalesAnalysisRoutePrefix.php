<?php

namespace App\Http\Controllers\SalesAnalysis\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * 売上分析ルートは superadmin/admin/clerk の各ロールグループ内に複製登録されている
 * （AppLayoutのcurrentRouteContextがルート名プレフィックスでロールタブを判定するため）。
 * Vue側がroute()を組み立てる際に使うプレフィックスをここで解決する。
 *
 * 2026-09-05修正: 以前はAuth::user()の実際のロールから常に決定していたため、
 * SuperAdminが `/admin/sales-analysis/...` や `/clerk/sales-analysis/...` を開いても
 * 常に「superadmin」タブが表示されてしまう不具合があった（SuperAdminは全プレフィックスに
 * アクセスできるため、他ロールの画面をプレビューする用途で使われる）。
 * 実際にアクセスされたルート名のプレフィックスを優先し、ロール判定はそれが取得できない
 * 場合のフォールバックとしてのみ使う。
 */
trait ResolvesSalesAnalysisRoutePrefix
{
    private function salesAnalysisRoutePrefix(): string
    {
        $routeName = RouteFacade::currentRouteName();

        foreach (['superadmin', 'admin', 'clerk'] as $prefix) {
            if ($routeName && str_starts_with($routeName, "{$prefix}.sales_analysis")) {
                return $prefix;
            }
        }

        $user = Auth::user();

        return match (true) {
            $user->isSuperAdmin() => 'superadmin',
            $user->isAdmin() => 'admin',
            $user->isClerk() => 'clerk',
            default => 'superadmin',
        };
    }
}
