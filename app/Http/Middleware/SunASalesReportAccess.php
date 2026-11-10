<?php

namespace App\Http\Middleware;

use App\Services\SunA\Sales\SunaCompanyContext;
use App\Support\SunA\Sales\ReportAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * サンエー印刷 売上レポートのルート保護。使い方: ->middleware('suna_sales_report:view') / ':import' / ':manage'
 *
 * 判定が通ったら、その会社を SunaCompanyContext に設定する（SunA/Sales の Model は会社が未設定だと例外になる）。
 * リクエストの終了時にコンテキストを解除する。サン・ブレーン等の対象外の会社・許可の無い人は 403。
 */
class SunASalesReportAccess
{
    public function handle(Request $request, Closure $next, string $ability = 'view'): Response
    {
        $user = $request->user();

        $allowed = match ($ability) {
            'import' => ReportAccess::canImport($user),
            'manage' => ReportAccess::canManage($user),
            default => ReportAccess::canView($user),
        };
        if (! $allowed) {
            abort(403, 'この機能を利用する権限がありません。');
        }

        SunaCompanyContext::set((int) ReportAccess::companyIdFor($user));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        SunaCompanyContext::clear();
    }
}
