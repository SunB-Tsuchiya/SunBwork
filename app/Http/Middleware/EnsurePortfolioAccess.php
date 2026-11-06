<?php

namespace App\Http\Middleware;

use App\Services\Portfolio\PortfolioAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * ポートフォリオ機能の全ルートを保護する。
 * 対象は SuperAdmin・Admin（常時可）と、個人許可がオンのユーザー（ロール不問）。判定は PortfolioAccess::canUse。
 * 会社の絞り込み（サンエー印刷の除外）は deny_general と併用して行う。
 */
class EnsurePortfolioAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            abort(403, 'Portfolio access required.');
        }

        if (PortfolioAccess::canUse($user)) {
            return $next($request);
        }

        abort(403, 'Portfolio access required.');
    }
}
