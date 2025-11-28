<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Lightweight middleware to log incoming attachment stream requests early in
 * the pipeline so we can see whether cookies and query params reach the app.
 * Registered only in the web middleware group during debugging.
 */
class AttachmentDebugMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            // Support both legacy API-prefixed stream path and new web /attachments/stream
            if ($request->is('api/attachments/stream') || $request->is('api/attachments/stream*') || $request->is('attachments/stream') || $request->is('attachments/stream*')) {
                $cookieHeader = $request->header('cookie');
                Log::info('AttachmentDebugMiddleware: incoming request', [
                    'uri' => $request->getRequestUri(),
                    'method' => $request->method(),
                    'query' => $request->query(),
                    'has_cookie_header' => !empty($cookieHeader),
                    'ip' => $request->ip(),
                ]);
            }
        } catch (\Throwable $__e) {
            // ignore
        }

        return $next($request);
    }
}
