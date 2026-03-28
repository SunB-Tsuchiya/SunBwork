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

        $middleware->alias([
            'superadmin'     => \App\Http\Middleware\SuperadminMiddleware::class,
            'admin'          => \App\Http\Middleware\AdminMiddleware::class,
            'leader'         => \App\Http\Middleware\LeaderMiddleware::class,
            'coordinator'    => \App\Http\Middleware\CoordinatorMiddleware::class,
            'owner'          => \App\Http\Middleware\OwnerMiddleware::class, // 後方互換性のため残す
            'representative'        => \App\Http\Middleware\EnsureIsRepresentative::class,
            'representative_leader' => \App\Http\Middleware\EnsureIsRepresentativeLeader::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
