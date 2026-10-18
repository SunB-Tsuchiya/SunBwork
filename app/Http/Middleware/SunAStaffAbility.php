<?php

namespace App\Http\Middleware;

use App\Support\SunA\GeneralAffairs\StaffAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 総務の権限（社員情報・年末調整）を確認する。使い方: ->middleware('suna_staff:view_employees')
 */
class SunAStaffAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        if (! StaffAccess::for($request->user())->can($ability)) {
            abort(403, 'この機能を利用する権限がありません。');
        }

        return $next($request);
    }
}
