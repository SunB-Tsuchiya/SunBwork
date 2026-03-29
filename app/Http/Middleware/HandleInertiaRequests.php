<?php

namespace App\Http\Middleware;

use App\Models\AdminPermission;
use App\Models\LeaderPermission;
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
        $flashMessage = session('success') ?? session('error') ?? null;
        $flashType    = session('success') ? 'success' : (session('error') ? 'error' : 'success');

        return [
            ...parent::share($request),
            'flash' => $flashMessage ? ['message' => $flashMessage, 'type' => $flashType] : null,
            // Share authenticated user basic info and helper role flags for frontend permission checks
            'auth' => [
                'user' => $request->user()
                    ? array_merge(
                        $request->user()->only(['id', 'name', 'email', 'user_role', 'company_id', 'department_id']),
                        [
                            'isAdmin'          => $request->user()->isAdmin(),
                            'isLeader'         => $request->user()->isLeader(),
                            'isCoordinator'    => $request->user()->isCoordinator(),
                            'isSuperAdmin'     => $request->user()->isSuperAdmin(),
                            'isUser'           => $request->user()->isUser(),
                            'isRepresentative'       => $request->user()->isAdmin() && $request->user()->isRepresentative(),
                            'isRepresentativeLeader' => $request->user()->isLeader() && $request->user()->isRepresentativeLeader(),
                            'isDepartmentLeader'     => $request->user()->isLeader() && $request->user()->isDepartmentLeader(),
                        ]
                    )
                    : null,
                // Admin 権限（admin ロール時のみ取得、それ以外は null）
                'adminPermissions' => $request->user()?->isAdmin()
                    ? AdminPermission::where('user_id', $request->user()->id)->first()
                    : null,
                // Leader 権限（leader ロール時のみ取得、それ以外は null）
                'leaderPermissions' => $request->user()?->isLeader()
                    ? LeaderPermission::where('user_id', $request->user()->id)->first()
                    : null,
            ],
        ];
    }
}
