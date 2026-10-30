<?php

namespace App\Http\Middleware;

use App\Models\AdminPermission;
use App\Models\AnnouncementRecipient;
use App\Models\JobNotification;
use App\Models\LeaderPermission;
use App\Models\ProofTeamMember;
use App\Models\ScheduleNotification;
use App\Services\PersonalWorkSchedule;
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
        // ASSET_URL を設定している環境（さくら本番）では親実装が固定値になり、デプロイ後も開きっぱなしのタブが
        // 古い JS のまま動いてしまう。ビルドごとに変わる manifest のハッシュを使い、次の画面遷移で自動リロードさせる。
        $manifest = public_path('build/manifest.json');

        return file_exists($manifest) ? hash_file('xxh128', $manifest) : parent::version($request);
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
            if ($ctxId) {
                // 明示的にコンテキスト会社を選択している場合はその会社を使う
                $contextCompany = \App\Models\Company::find($ctxId);
            } else {
                // コンテキスト未選択時は SuperAdmin 自身の所属会社をフォールバックとして使う
                // （SuperAdmin が sunbrain 会社に所属している場合は Prepress 等の機能が有効になる）
                $contextCompany = $request->user()->company;
            }
        } else {
            $contextCompany = $request->user()?->company;
        }

        $unreadAnnouncements = 0;
        if ($request->user()) {
            $unreadAnnouncements = AnnouncementRecipient::where('user_id', $request->user()->id)
                ->forSentAnnouncements()
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
                // 一般会社（サンエー印刷等）の Clerk エリア種別 'soumu' | 'keiri'（それ以外は null）
                // Clerk は所属部署名に「経理」を含むか、Admin 以上は ?kind= で選んだ方をセッションに保持（既定 総務）
                'clerkKind' => (function () use ($request, $contextCompany) {
                    $u = $request->user();
                    if (! $u || $contextCompany?->company_type !== 'general') {
                        return null;
                    }
                    if ($u->isClerk()) {
                        return str_contains((string) \App\Models\Department::find($u->department_id)?->name, '経理') ? 'keiri' : 'soumu';
                    }
                    if ($u->isSuperAdmin() || $u->isAdmin()) {
                        $kind = $request->query('kind');
                        if (in_array($kind, ['soumu', 'keiri'], true)) {
                            session(['clerk_kind' => $kind]);
                        }

                        return session('clerk_kind', 'soumu');
                    }

                    return null;
                })(),
                // 年末調整: 自分が対象者になっている受付中の受付があるか（ユーザー用タブの「年末調整」表示用。会社タイプ問わず）
                'nenchoAvailable' => (function () use ($request) {
                    $u = $request->user();
                    if (! $u) {
                        return false;
                    }
                    $today = now('Asia/Tokyo')->toDateString();

                    return \App\Models\SunA\GeneralAffairs\Nencho\Target::where('user_id', $u->id)
                        ->where('is_excluded', false)
                        ->whereHas('campaign', fn ($q) => $q->where('status', 'open')->whereDate('opens_on', '<=', $today)->whereDate('due_on', '>=', $today))
                        ->exists()
                        // 差戻しされた提出物がある人にも、受付期間外でもタブを出す（修正できるようにする）
                        || \App\Models\SunA\GeneralAffairs\Nencho\Submission::where('user_id', $u->id)->where('status', 'returned')->exists();
                })(),
                // 申請機能（交通費など）を使える申請の種類（申請者管理のスイッチ）。ユーザー用タブの「申請」の表示に使う
                'applicationAccess' => $request->user() ? \App\Models\UserApplicationAccess::enabledKeys($request->user()) : [],
                // 総務の権限（社員情報・年末調整）。サンエー印刷（general）の clerk/admin と SuperAdmin のみ。それ以外は null（追加クエリなし）
                'staffAccess' => (function () use ($request, $contextCompany) {
                    $u = $request->user();
                    if (! $u) {
                        return null;
                    }
                    if (! $u->isSuperAdmin() && ! ($contextCompany?->company_type === 'general' && ($u->isAdmin() || $u->isClerk()))) {
                        return null;
                    }

                    return \App\Support\SunA\GeneralAffairs\StaffAccess::for($u)->toArray();
                })(),
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
                // 売上分析機能へのアクセス権（SuperAdmin常時可、Admin/Clerkは個人許可がある場合のみ）
                'canAccessSalesAnalysis' => (function () use ($request) {
                    $user = $request->user();
                    if (! $user) {
                        return false;
                    }
                    if ($user->isSuperAdmin()) {
                        return true;
                    }
                    if ($user->isAdmin() || $user->isClerk()) {
                        return \App\Models\SalesAnalysisPermission::where('user_id', $user->id)
                            ->where('enabled', true)
                            ->exists();
                    }

                    return false;
                })(),
                'canAccessWorkRecordTrial' => $request->user()?->isSuperAdmin()
                    && app(PersonalWorkSchedule::class)->available($request->user()),
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
                // SuperAdmin はコンテキスト切り替えに応じた値。未切り替え時は所属会社タイプ（自社未設定時のみ 'global'）
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
