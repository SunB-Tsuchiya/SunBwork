<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // リバースプロキシ（さくらレンタルサーバー等）を信頼する
        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Ensure API routes can authenticate first-party SPA requests using
        // Sanctum's stateful session cookies.
        $middleware->api(append: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // CSRF 除外: api/jobbox/*/read は auth:sanctum で保護済みのため safe
        $middleware->validateCsrfTokens(except: [
            'api/jobbox/*/read',
        ]);

        $middleware->alias([
            'superadmin'     => \App\Http\Middleware\SuperadminMiddleware::class,
            'admin'          => \App\Http\Middleware\AdminMiddleware::class,
            'leader'         => \App\Http\Middleware\LeaderMiddleware::class,
            'coordinator'    => \App\Http\Middleware\CoordinatorMiddleware::class,
            'ghost'          => \App\Http\Middleware\GhostUserMiddleware::class,
            'proof_coordinator' => \App\Http\Middleware\ProofCoordinatorMiddleware::class,
            'clerk'          => \App\Http\Middleware\ClerkMiddleware::class,
            'diary_manager'  => \App\Http\Middleware\DiaryManagerMiddleware::class,
            'owner'          => \App\Http\Middleware\OwnerMiddleware::class, // 後方互換性のため残す
            'representative'        => \App\Http\Middleware\EnsureIsRepresentative::class,
            'representative_leader' => \App\Http\Middleware\EnsureIsRepresentativeLeader::class,
            'company_type'   => \App\Http\Middleware\CheckCompanyType::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, $request) {
            if ($e->getStatusCode() !== 403 || $request->expectsJson()) {
                return null;
            }

            return \Inertia\Inertia::render('Errors/403')
                ->toResponse($request)
                ->setStatusCode(403);
        });

        // CSRF 419 デバッグ用（ゲストログイン 419 原因調査）
        $exceptions->renderable(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            \Illuminate\Support\Facades\Log::warning('CSRF 419 発生', [
                'url'          => $request->fullUrl(),
                'method'       => $request->method(),
                'session_id'   => $request->session()->getId(),
                'has_session_token' => (bool) $request->session()->token(),
                'has_form_token'    => $request->filled('_token'),
                'xsrf_header'  => $request->header('X-XSRF-TOKEN') ? '[present]' : null,
                'has_session_cookie' => $request->hasCookie('laravel_session'),
            ]);
            // 元の動作（419 ページ表示）に戻す
            return null;
        });
    })->create();
