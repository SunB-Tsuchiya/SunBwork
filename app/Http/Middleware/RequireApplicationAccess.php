<?php

namespace App\Http\Middleware;

use App\Models\UserApplicationAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 申請機能を使う権限（申請者管理のスイッチ）がない人を止める。
 * 使い方: RequireApplicationAccess::class . ':transport'（その申請）／ ':any'（どれか 1 つでも使える）
 */
class RequireApplicationAccess
{
    public function handle(Request $request, Closure $next, string $feature = 'any'): Response
    {
        $user = $request->user();
        $keys = $user ? UserApplicationAccess::enabledKeys($user) : [];
        $ok = $feature === 'any' ? count($keys) > 0 : in_array($feature, $keys, true);

        abort_unless($ok, 403, 'この申請を使う権限がありません。必要な場合は、管理者に、申請者管理での設定を相談してください。');

        return $next($request);
    }
}
