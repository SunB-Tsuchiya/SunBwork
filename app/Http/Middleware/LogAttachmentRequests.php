<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Temporary middleware to log incoming requests that touch attachment routes.
 * Install temporarily in the 'api' group to diagnose malformed or unauthenticated
 * requests during debugging. Remove when no longer needed.
 */
class LogAttachmentRequests
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            $uri = $request->getRequestUri();
            // Only log requests that look related to attachments to reduce noise
            if (stripos($uri, 'attach') !== false) {
                $hasSession = $request->cookies->has(config('session.cookie'));
                Log::info('AttachmentRequest', [
                    'method' => $request->method(),
                    'uri' => $uri,
                    'path' => $request->path(),
                    'query' => $request->getQueryString(),
                    'has_session_cookie' => $hasSession,
                    'remote_addr' => $request->ip(),
                ]);
            }
        } catch (\Throwable $e) {
            // avoid breaking requests during diagnostics
            Log::warning('LogAttachmentRequests middleware error: ' . $e->getMessage());
        }

        return $next($request);
    }
}
