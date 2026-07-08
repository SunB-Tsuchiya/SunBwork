<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): SymfonyResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Inertia::location() forces a full page reload on the client side.
        // This is necessary because session()->regenerate() changes the CSRF token,
        // and Inertia's soft navigation does not update <meta name="csrf-token">,
        // causing all subsequent POST requests to fail with 419.
        return Inertia::location(redirect()->intended(route('dashboard', absolute: false)));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): SymfonyResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Force a full reload so the next page receives a fresh session cookie
        // and <meta name="csrf-token"> after the logout token rotation.
        return Inertia::location(redirect()->route('login'));
    }
}
