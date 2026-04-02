<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ClerkMiddleware
{
    /**
     * Clerk エリアへのアクセス制御。
     *
     * 許可:
     *   - clerk ロール
     *   - admin / superadmin
     *   - leader のうち 部署リーダー（isDepartmentLeader）のみ
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            abort(403, 'Clerk access required.');
        }

        $user = Auth::user();

        $allowed =
            $user->isClerk() ||
            $user->isAdmin() ||
            $user->isSuperAdmin() ||
            ($user->isLeader() && $user->isDepartmentLeader());

        if (! $allowed) {
            abort(403, 'Clerk access required.');
        }

        return $next($request);
    }
}
