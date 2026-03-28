<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureIsRepresentativeLeader
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || ! $user->isLeader() || ! $user->isRepresentativeLeader()) {
            abort(403, '代表者リーダーのみアクセスできます。');
        }

        return $next($request);
    }
}
