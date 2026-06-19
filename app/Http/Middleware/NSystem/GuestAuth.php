<?php

namespace App\Http\Middleware\NSystem;

use App\Models\DemoPage;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class GuestAuth
{
    public function handle(Request $request, Closure $next, string $slug = 'n-demo'): Response
    {
        // Sanctum 認証済みスタッフはそのまま通す
        if (Auth::check()) {
            return $next($request);
        }

        if (session("nsystem_demo_auth.{$slug}") === true) {
            // DBで有効性を再確認（管理画面で無効化・期限切れになっていないか）
            $page = DemoPage::where('slug', $slug)->first();
            if ($page?->isAccessible()) {
                return $next($request);
            }
            session()->forget("nsystem_demo_auth.{$slug}");
        }

        return redirect()->route('n-guest.login', ['for' => $slug])
            ->with('intended', $request->fullUrl());
    }
}
