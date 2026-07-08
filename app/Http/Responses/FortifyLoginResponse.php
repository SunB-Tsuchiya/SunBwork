<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class FortifyLoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        // session()->regenerate() runs during login. Force a browser reload so
        // the SPA receives the fresh CSRF token before the next write request.
        return Inertia::location(redirect()->intended(Fortify::redirects('login')));
    }
}
