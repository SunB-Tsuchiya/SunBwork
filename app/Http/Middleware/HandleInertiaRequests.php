<?php

namespace App\Http\Middleware;

use App\Models\AdminPermission;
use App\Models\AnnouncementRecipient;
use App\Models\JobNotification;
use App\Models\LeaderPermission;
use App\Models\ProofTeamMember;
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

        $unreadAnnouncements = 0;
        if ($request->user()) {
            $unreadAnnouncements = AnnouncementRecipient::where('user_id', $request->user()->id)
                ->whereNull('read_at')
                ->count();
        }

        $unreadJobNotifications = 0;
        if ($request->user()) {
            $unreadJobNotifications = JobNotification::where('recipient_id', $request->user()->id)
                ->whereNull('read_at')
                ->count();
        }

        return [
            ...parent::share($request),
            'flash' => $flashMessage ? ['message' => $flashMessage, 'type' => $flashType] : null,
            'clientDeleteError' => session('clientDeleteError'),
            'subcontractorDeleteError' => session('subcontractorDeleteError'),
            'unreadAnnouncements' => $unreadAnnouncements,
            'unreadJobNotifications' => $unreadJobNotifications,
            // Share authenticated user basic info and helper role flags for frontend permission checks
            'auth' => [
                'user' => $request->user()
                    ? array_merge(
                        $request->user()->only(['id', 'name', 'email', 'user_role', 'company_id', 'department_id', 'is_ghost'])
                            + ['worktype_end_time' => $request->user()->worktype?->end_time
                            ? substr($request->user()->worktype->end_time, 0, 5)
                            : null],
                        [
                            'isAdmin'          => $request->user()->isAdmin(),
                            'isLeader'         => $request->user()->isLeader(),
                            'isCoordinator'    => $request->user()->isCoordinator() || $request->user()->isClerk(),
                            'isClerk'          => $request->user()->isClerk(),
                            'isSuperAdmin'     => $request->user()->isSuperAdmin(),
                            'isUser'           => $request->user()->isUser(),
                            'isRepresentative'       => $request->user()->isAdmin() && $request->user()->isRepresentative(),
                            'isRepresentativeLeader' => $request->user()->isLeader() && $request->user()->isRepresentativeLeader(),
                            'isDepartmentLeader'     => $request->user()->isLeader() && $request->user()->isDepartmentLeader(),
                            'isPrepressDepartment'   => $request->user()->isSuperAdmin() || $request->user()->isAdmin()
                                ? true
                                : (\App\Models\Department::find($request->user()->department_id)?->name === '製版'),
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
                // スクリプトツールへのアクセス権
                'canAccessScripts' => (function () use ($request) {
                    $user = $request->user();
                    if (! $user) {
                        return false;
                    }
                    if (in_array($user->user_role, ['superadmin', 'admin'])) {
                        return true;
                    }
                    if ($user->user_role === 'leader') {
                        return (bool) (LeaderPermission::where('user_id', $user->id)->value('script_access') ?? false);
                    }

                    return false;
                })(),
                // 校正チームメンバーフラグ
                'isProofMember' => $request->user()
                    ? ProofTeamMember::where('user_id', $request->user()->id)->exists()
                    : false,
            ],
        ];
    }
}
