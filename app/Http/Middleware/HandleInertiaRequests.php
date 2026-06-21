<?php

namespace App\Http\Middleware;

use App\Models\AdminPermission;
use App\Models\AnnouncementRecipient;
use App\Models\JobNotification;
use App\Models\LeaderPermission;
use App\Models\ProofTeamMember;
use App\Models\ScheduleNotification;
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

        // 会社タイプ取得（SuperAdmin はコンテキスト切り替えに応じた会社を参照）
        $contextCompany = null;
        if ($request->user()?->isSuperAdmin()) {
            $ctxId = session('superadmin_context.company_id');
            $contextCompany = $ctxId ? \App\Models\Company::find($ctxId) : null;
        } else {
            $contextCompany = $request->user()?->company;
        }

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

        $unreadScheduleNotifications = 0;
        if ($request->user()) {
            $unreadScheduleNotifications = ScheduleNotification::where('user_id', $request->user()->id)
                ->whereNull('read_at')
                ->count();
        }

        // 部署フィールド設定（会社内全部署分、AssignmentForm で利用）
        $departmentFieldConfigs = [];
        $jobFieldOptions        = [];
        if ($request->user() && $contextCompany?->id) {
            $companyId = $contextCompany->id;
            $deptIds   = \App\Models\Department::where('company_id', $companyId)->pluck('id');
            $departmentFieldConfigs = \App\Models\DepartmentFieldConfig::whereIn('department_id', $deptIds)
                ->get(['id', 'department_id', 'slot', 'label', 'enabled', 'allowed_item_ids', 'source', 'source_group'])
                ->toArray();
            $jobFieldOptions = \App\Models\JobFieldOption::where('company_id', $companyId)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'group_key', 'company_id', 'coefficient'])
                ->toArray();
        }

        return [
            ...parent::share($request),
            // CSRF トークンをすべての Inertia レスポンスで共有する。
            // セッション移行（login() / regenerate()）後も soft navigation で
            // meta[name="csrf-token"] が更新されるよう AppLayout が参照している。
            'csrf_token' => csrf_token(),
            'flash' => $flashMessage ? ['message' => $flashMessage, 'type' => $flashType] : null,
            'departmentFieldConfigs' => $departmentFieldConfigs,
            'jobFieldOptions'        => $jobFieldOptions,
            'clientDeleteError' => session('clientDeleteError'),
            'subcontractorDeleteError' => session('subcontractorDeleteError'),
            'unreadAnnouncements' => $unreadAnnouncements,
            'unreadJobNotifications' => $unreadJobNotifications,
            'unreadScheduleNotifications' => $unreadScheduleNotifications,
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
                            // department.module === 'prepress' で判定（名前ベースから移行）
                            'isPrepressDepartment'   => $request->user()->isSuperAdmin() || $request->user()->isAdmin()
                                ? true
                                : (\App\Models\Department::find($request->user()->department_id)?->module === 'prepress'),
                            'departmentModule'       => \App\Models\Department::find($request->user()->department_id)?->module,
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
                // 日報マネージャーフラグ（diary_team_leaders に登録されている場合 true）
                'isDiaryManager' => $request->user()
                    ? \Illuminate\Support\Facades\DB::table('diary_team_leaders')
                        ->where('user_id', $request->user()->id)
                        ->exists()
                    : false,
                // 部署別ジョブフロー機能フラグ
                // 各コンポーネントは auth.featureFlags.xxx を参照するだけ（判定ロジック不要）
                'featureFlags' => (function () use ($request, $contextCompany) {
                    $user = $request->user();
                    if (! $user) {
                        return [];
                    }
                    $module     = \App\Models\Department::find($user->department_id)?->module;
                    $isSunbrain = $contextCompany?->company_type === 'sunbrain';
                    $isAdminUp  = in_array($user->user_role, ['superadmin', 'admin']);
                    return [
                        'proofRequest'  => $isSunbrain && ($isAdminUp || $module === 'publishing'),
                        'prepressBoard' => $isSunbrain && ($isAdminUp || $module === 'prepress'),
                        // general タイプ会社の Clerk/Admin、または SuperAdmin は全社に通知送信可
                        'crossCompanyAnnouncement' => in_array($user->user_role, ['clerk', 'admin', 'superadmin'])
                            && ($contextCompany?->company_type === 'general' || $user->user_role === 'superadmin'),
                    ];
                })(),
                // 会社タイプ（'sunbrain' | 'general' | 'global'）
                // SuperAdmin はコンテキスト切り替えに応じた値。未切り替え時は 'global'
                'companyType' => $contextCompany?->company_type ?? 'global',
                // SuperAdmin のコンテキスト会社 ID（null = グローバル管理モード）
                'superAdminContextId' => $request->user()?->isSuperAdmin()
                    ? session('superadmin_context.company_id')
                    : null,
                // SuperAdmin のコンテキスト切り替え用会社一覧
                'switchableCompanies' => $request->user()?->isSuperAdmin()
                    ? \App\Models\Company::where('code', '!=', 'SUPERADMIN')
                        ->active()
                        ->ordered()
                        ->get(['id', 'name', 'company_type'])
                        ->toArray()
                    : null,
            ],
        ];
    }
}
