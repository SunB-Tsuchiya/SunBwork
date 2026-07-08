<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Fortify;

class FortifyLogoutResponse implements LogoutResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        // Logout invalidates the session and rotates the token. A full reload
        // prevents stale <meta name="csrf-token"> from being reused.
        return Inertia::location(redirect(Fortify::redirects('logout', '/')));
    }
}
