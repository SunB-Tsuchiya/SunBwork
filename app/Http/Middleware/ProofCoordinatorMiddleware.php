<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ProofCoordinatorMiddleware
{
    /**
     * 校正コーディネーターエリアへのアクセス制御。
     *
     * 許可:
     *   - proof_coordinator ロール
     *   - admin / superadmin
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            abort(403, 'ProofCoordinator access required.');
        }

        $user = Auth::user();

        $allowed =
            $user->isProofCoordinator() ||
            $user->isAdmin() ||
            $user->isSuperAdmin();

        if (! $allowed) {
            abort(403, 'ProofCoordinator access required.');
        }

        return $next($request);
    }
}
