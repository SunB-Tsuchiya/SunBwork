<?php

namespace App\Http\Middleware;

use App\Models\SalesAnalysisPermission;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * 売上分析機能への全ルートを保護する。
 * 対象は SuperAdmin（常時可）と、個人許可がオンの Admin/Clerk のみ。
 * Leader・Coordinator・User はロールに関わらず常に拒否する。
 */
class EnsureSalesAnalysisAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            abort(403, 'Sales analysis access required.');
        }

        $user = Auth::user();

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if ($user->isAdmin() || $user->isClerk()) {
            $allowed = SalesAnalysisPermission::where('user_id', $user->id)
                ->where('enabled', true)
                ->exists();

            if ($allowed) {
                return $next($request);
            }
        }

        abort(403, 'Sales analysis access required.');
    }
}
