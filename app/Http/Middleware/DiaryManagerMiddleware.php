<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class DiaryManagerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            abort(403, '認証が必要です');
        }

        $user = Auth::user();

        if (! DB::table('diary_team_leaders')->where('user_id', $user->id)->exists()) {
            abort(403, '日報管理権限がありません');
        }

        return $next($request);
    }
}
