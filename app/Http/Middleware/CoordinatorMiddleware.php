<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CoordinatorMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check() || (!Auth::user()->isCoordinator() && !Auth::user()->isLeader() && !Auth::user()->isAdmin() && !Auth::user()->isSuperAdmin())) {
            abort(403, 'Coordinator or Admin access required.');
        }
        return $next($request);
    }
}
