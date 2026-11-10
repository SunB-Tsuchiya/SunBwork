<?php

namespace App\Http\Middleware;

use App\Support\SunA\Area;
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

        // サンエー印刷（一般会社）: ロールの上下ではなく、所属部署のエリア（総務・経理）で決める。管理者も自分のエリアだけ（SuperAdmin は全部）
        if (Area::isGeneral($user)) {
            abort_unless(Area::canEnterClerk($user), 403, 'このエリアは、総務・経理の部署の人だけが利用できます。');

            return $next($request);
        }

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
