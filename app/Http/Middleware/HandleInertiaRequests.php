<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            // Share authenticated user basic info and helper role flags for frontend permission checks
            'auth' => [
                'user' => $request->user()
                    ? array_merge(
                        $request->user()->only(['id', 'name', 'email', 'user_role', 'company_id', 'department_id']),
                        [
                            'isAdmin' => $request->user()->isAdmin(),
                            'isLeader' => $request->user()->isLeader(),
                            'isCoordinator' => $request->user()->isCoordinator(),
                            'isSuperAdmin' => $request->user()->isSuperAdmin(),
                            'isUser' => $request->user()->isUser(),
                        ]
                    )
                    : null,
            ],
        ];
    }
}
