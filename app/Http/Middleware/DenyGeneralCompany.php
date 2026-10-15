<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DenyGeneralCompany
{
    /**
     * 一般会社（company_type = 'general'）のユーザーをサン・ブレーン専用エリアから締め出す。
     *
     * 'general' 所属のユーザーだけを拒否する（SuperAdmin・サン・ブレーン・会社未設定は常に通過）。
     * company_type:sunbrain と違い、会社未設定ユーザーを巻き込まないため既存挙動に影響しない。
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isSuperAdmin() && $user->company?->company_type === 'general') {
            abort(403, 'この機能はご利用いただけません。');
        }

        return $next($request);
    }
}
