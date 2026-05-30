<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCompanyType
{
    /**
     * Handle an incoming request.
     *
     * SuperAdmin はコンテキスト問わず常に通過させる。
     * 一般ユーザーは所属会社の company_type が $types に含まれる場合のみ通過。
     */
    public function handle(Request $request, Closure $next, string ...$types): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        // SuperAdmin は会社タイプに関係なくアクセス可能
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $company = $user->company;
        if (! $company || ! in_array($company->company_type, $types, true)) {
            abort(403, 'この機能はご利用いただけません。');
        }

        return $next($request);
    }
}
