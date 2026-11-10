<?php

use App\Http\Controllers\SunA\GeneralAffairs\ApplicationController;
use App\Http\Controllers\SunA\GeneralAffairs\ApplicationTypeController;
use App\Http\Controllers\SunA\Common\PostalLookupController;
use App\Http\Controllers\SunA\GeneralAffairs\Admin\StaffPermissionController;
use App\Http\Controllers\SunA\GeneralAffairs\Employees\EmployeeController;
use App\Http\Controllers\SunA\GeneralAffairs\Nencho\CampaignController;
use App\Http\Controllers\SunA\GeneralAffairs\Nencho\MyNenchoController;
use App\Http\Controllers\SunA\GeneralAffairs\Nencho\NoticeController;
use App\Http\Controllers\SunA\GeneralAffairs\Nencho\NenchoController;
use App\Http\Controllers\SunA\GeneralAffairs\Nencho\SubmissionController;
use App\Http\Controllers\SunA\Sales\AnnualAnalysisController;
use App\Http\Controllers\SunA\Sales\ClientAnalysisController;
use App\Http\Controllers\SunA\Sales\ClientGroupController;
use App\Http\Controllers\SunA\Sales\CompareController;
use App\Http\Controllers\SunA\Sales\DashboardController as SalesDashboardController;
use App\Http\Controllers\SunA\Sales\DepartmentComparisonController;
use App\Http\Controllers\SunA\Sales\ImportController;
use App\Http\Controllers\SunA\Sales\RegistrationStatusController;
use App\Http\Controllers\SunA\Sales\SameMonthController;
use App\Http\Controllers\SunA\Sales\ReportPermissionController;
use App\Http\Controllers\Clerk\ClerkCalendarColorController;
use App\Http\Controllers\Clerk\ClerkCalendarController;
use App\Http\Controllers\Clerk\ClerkCalendarHolidayController;
use App\Http\Controllers\Clerk\ClerkCalendarReminderController;
use App\Http\Controllers\Clerk\ClerkEventController;
use App\Http\Controllers\Clerk\ClerkScheduleRuleController;
use App\Http\Controllers\Clerk\ClerkWeekPostController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| サンエー印刷 専用ルート（SunA）
|--------------------------------------------------------------------------
| サンエー印刷（company_type = 'general'）専用機能のルート。CLAUDE.md「SunA 配置ルール」参照。
| 総務/経理は Clerk エリア（ルート名の接頭辞 clerk. を保つ。AppLayout のロール判定・ナビ色が依存）。
| company_type:general で保護（SuperAdmin は通過）。
*/

// 総務
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'clerk', 'company_type:general'])
    ->prefix('clerk/general-affairs')
    ->name('clerk.general_affairs.')
    ->group(function () {
        // 申請一覧（有効な申請の種類を枠で表示）
        Route::get('applications', [ApplicationController::class, 'index'])->name('applications.index');

        // 年末調整（トップ・設計ガイド）
        Route::get('nencho', [NenchoController::class, 'index'])->name('nencho.index');
        Route::get('nencho/guide', [NenchoController::class, 'guide'])->name('nencho.guide');

        // 年末調整: 受付（会社×年度）と対象者 … 権限「年末調整を管理する」が必要
        Route::middleware('suna_staff:manage_nencho')->group(function () {
            Route::get('nencho/campaigns', [CampaignController::class, 'index'])->name('nencho.campaigns.index');
            Route::get('nencho/campaigns/create', [CampaignController::class, 'create'])->name('nencho.campaigns.create');
            Route::post('nencho/campaigns', [CampaignController::class, 'store'])->name('nencho.campaigns.store');
            Route::get('nencho/campaigns/{campaign}', [CampaignController::class, 'show'])->name('nencho.campaigns.show');
            Route::get('nencho/campaigns/{campaign}/edit', [CampaignController::class, 'edit'])->name('nencho.campaigns.edit');
            Route::put('nencho/campaigns/{campaign}', [CampaignController::class, 'update'])->name('nencho.campaigns.update');
            Route::post('nencho/campaigns/{campaign}/sync', [CampaignController::class, 'sync'])->name('nencho.campaigns.sync');
            Route::post('nencho/campaigns/{campaign}/targets', [CampaignController::class, 'storeTarget'])->name('nencho.campaigns.targets.store');
            Route::patch('nencho/campaigns/{campaign}/targets/{target}', [CampaignController::class, 'toggleTarget'])->name('nencho.campaigns.targets.toggle');
            // 案内（お知らせの送信・カレンダーのリマインダー）
            Route::post('nencho/campaigns/{campaign}/notices', [NoticeController::class, 'store'])->name('nencho.campaigns.notices.store');
            Route::post('nencho/campaigns/{campaign}/calendar-reminder', [NoticeController::class, 'reminder'])->name('nencho.campaigns.reminder');
            Route::delete('nencho/campaigns/{campaign}/calendar-reminder', [NoticeController::class, 'removeReminder'])->name('nencho.campaigns.reminder.remove');
            // 提出内容の確認と差戻し
            Route::get('nencho/submissions/{submission}', [SubmissionController::class, 'show'])->name('nencho.submissions.show');
            Route::post('nencho/submissions/{submission}/return', [SubmissionController::class, 'return'])->name('nencho.submissions.return');
            Route::post('nencho/submissions/{submission}/profile-sync', [SubmissionController::class, 'profileSync'])->name('nencho.submissions.profile_sync');
            Route::post('nencho/submissions/{submission}/memo', [SubmissionController::class, 'memo'])->name('nencho.submissions.memo');
            // 代理入力（端末のない従業員の分を、総務が同じフォームで入力する）
            Route::get('nencho/proxy/campaigns/{campaign}/users/{user}', [MyNenchoController::class, 'proxyStart'])->name('nencho.proxy.start');
            Route::get('nencho/proxy/submissions/{submission}', [MyNenchoController::class, 'show'])->name('nencho.proxy.show');
            Route::put('nencho/proxy/submissions/{submission}/steps/{step}', [MyNenchoController::class, 'saveStep'])->whereNumber('step')->name('nencho.proxy.steps.save');
            Route::post('nencho/proxy/submissions/{submission}/carry-over', [MyNenchoController::class, 'carryOver'])->name('nencho.proxy.carry_over');
            Route::post('nencho/proxy/submissions/{submission}/submit', [MyNenchoController::class, 'submit'])->name('nencho.proxy.submit');
            Route::post('nencho/proxy/submissions/{submission}/heartbeat', [MyNenchoController::class, 'heartbeat'])->name('nencho.proxy.heartbeat');
            Route::post('nencho/proxy/submissions/{submission}/release', [MyNenchoController::class, 'release'])->name('nencho.proxy.release');
            Route::post('nencho/proxy/submissions/{submission}/takeover', [MyNenchoController::class, 'takeover'])->name('nencho.proxy.takeover');
        });

        // 社員情報（両社の社員。ユーザー情報 users とは別）… 権限で制御
        Route::middleware('suna_staff:view_employees')->group(function () {
            Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
            Route::get('employees/guide', [EmployeeController::class, 'guide'])->name('employees.guide');
            Route::get('employees/{user}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
        });
        Route::put('employees/{user}', [EmployeeController::class, 'update'])->middleware('suna_staff:edit_employees')->name('employees.update');
        Route::post('employees/{user}/my-number/reveal', [EmployeeController::class, 'revealMyNumber'])->middleware('suna_staff:view_my_number')->name('employees.my_number.reveal');

        // 申請の種類の管理（CRUD）
        Route::get('application-types', [ApplicationTypeController::class, 'index'])->name('application_types.index');
        Route::get('application-types/create', [ApplicationTypeController::class, 'create'])->name('application_types.create');
        Route::post('application-types', [ApplicationTypeController::class, 'store'])->name('application_types.store');
        Route::get('application-types/{applicationType}/edit', [ApplicationTypeController::class, 'edit'])->name('application_types.edit');
        Route::put('application-types/{applicationType}', [ApplicationTypeController::class, 'update'])->name('application_types.update');
        Route::patch('application-types/{applicationType}/active', [ApplicationTypeController::class, 'toggle'])->name('application_types.toggle');
        Route::delete('application-types/{applicationType}', [ApplicationTypeController::class, 'destroy'])->name('application_types.destroy');
    });

// 管理者: 社員情報権限管理（サン・ブレーンの Leader権限管理と同じ作り）とマイナンバー閲覧記録
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'admin', 'company_type:general'])
    ->prefix('admin/general-affairs')
    ->name('admin.general_affairs.')
    ->group(function () {
        Route::get('staff-permissions', [StaffPermissionController::class, 'index'])->name('staff_permissions.index');
        Route::get('staff-permissions/{staff}/edit', [StaffPermissionController::class, 'edit'])->name('staff_permissions.edit');
        Route::put('staff-permissions/{staff}', [StaffPermissionController::class, 'update'])->name('staff_permissions.update');
        Route::get('my-number-logs', [StaffPermissionController::class, 'logs'])->name('my_number_logs.index');
    });

// 売上レポート（営業）: 閲覧。許可された人（と SuperAdmin）・サンエー印刷のみ（suna_sales_report:view）。
// 独立レイアウト（AppLayout を使わない）。ルート名の接頭辞 suna.sales.report.（clerk. は使わない）。
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'suna_sales_report:view'])
    ->prefix('suna/sales/report')
    ->name('suna.sales.report.')
    ->group(function () {
        Route::get('/', [RegistrationStatusController::class, 'index'])->name('index');
        Route::get('api/registration-status', [RegistrationStatusController::class, 'data'])->name('api.registration_status');

        // 取込履歴（何が変わったか。閲覧できる人）
        Route::get('history', [ImportController::class, 'history'])->name('history.index');
        Route::get('api/history/{import}/changes', [ImportController::class, 'changes'])->whereNumber('import')->name('api.history.changes');

        // 分析（段階2）: 年次分析・同月比較・得意先分析。画面と JSON API
        Route::get('annual', [AnnualAnalysisController::class, 'index'])->name('annual');
        Route::get('api/annual/summary', [AnnualAnalysisController::class, 'summary'])->name('api.annual.summary');
        Route::get('api/annual/clients', [AnnualAnalysisController::class, 'clients'])->name('api.annual.clients');
        Route::get('same-month', [SameMonthController::class, 'index'])->name('same_month');
        Route::get('api/same-month/summary', [SameMonthController::class, 'summary'])->name('api.same_month.summary');
        Route::get('client', [ClientAnalysisController::class, 'index'])->name('client');
        Route::get('api/client/search', [ClientAnalysisController::class, 'search'])->name('api.client.search');
        Route::get('api/client/detail', [ClientAnalysisController::class, 'detail'])->name('api.client.detail');
        Route::get('department', [DepartmentComparisonController::class, 'index'])->name('department');
        Route::get('api/department/summary', [DepartmentComparisonController::class, 'summary'])->name('api.department.summary');
        Route::get('compare', [CompareController::class, 'index'])->name('compare');
        Route::get('api/compare/summary', [CompareController::class, 'summary'])->name('api.compare.summary');

        // 名寄せ設定（閲覧。変更は取込できる人のグループ）
        Route::get('client-groups', [ClientGroupController::class, 'index'])->name('client_groups');
        Route::get('api/client-groups/data', [ClientGroupController::class, 'data'])->name('api.client_groups.data');
        Route::get('api/client-groups/clients', [ClientGroupController::class, 'clients'])->name('api.client_groups.clients');
        Route::get('api/client-groups/events', [ClientGroupController::class, 'events'])->name('api.client_groups.events');
    });

// 売上レポート（営業）: 取込。取込を許可された人（と SuperAdmin）のみ（suna_sales_report:import）。
// 流れ: アップロード → 解析・検証 → 差分プレビュー → 確定。取込ファイルは保存しない
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'suna_sales_report:import'])
    ->prefix('suna/sales/report')
    ->name('suna.sales.report.')
    ->group(function () {
        Route::get('import', [ImportController::class, 'create'])->name('import.create');
        Route::post('api/import/preview', [ImportController::class, 'preview'])->name('api.import.preview');
        Route::post('api/import/repreview', [ImportController::class, 'repreview'])->name('api.import.repreview');
        Route::post('api/import/confirm', [ImportController::class, 'confirm'])->name('api.import.confirm');
        Route::post('api/import/discard', [ImportController::class, 'discard'])->name('api.import.discard');
        Route::post('api/history/{import}/undo', [ImportController::class, 'undo'])->whereNumber('import')->name('api.history.undo');

        // 名寄せ設定の変更（グループの作成・名前変更・メンバーの追加/解除・解散）
        Route::post('api/client-groups', [ClientGroupController::class, 'store'])->name('api.client_groups.store');
        Route::patch('api/client-groups/{group}', [ClientGroupController::class, 'update'])->whereNumber('group')->name('api.client_groups.update');
        Route::post('api/client-groups/{group}/members', [ClientGroupController::class, 'addMembers'])->whereNumber('group')->name('api.client_groups.members.add');
        Route::post('api/client-groups/{group}/members/remove', [ClientGroupController::class, 'removeMember'])->whereNumber('group')->name('api.client_groups.members.remove');
        Route::delete('api/client-groups/{group}', [ClientGroupController::class, 'destroy'])->whereNumber('group')->name('api.client_groups.destroy');
    });

// 売上レポート（営業）: 管理者の「利用メンバー設定」（サンエー印刷用。ポートフォリオの利用メンバー設定と同じ形。
// SuperAdmin の「売上分析 利用許可設定」（サン・ブレーン用）とは別）。suna_sales_report:manage = Admin/SuperAdmin かつサンエー印刷
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'admin', 'suna_sales_report:manage'])
    ->prefix('admin/suna-sales')
    ->name('admin.suna.sales.')
    ->group(function () {
        Route::get('permissions', [ReportPermissionController::class, 'index'])->name('permissions.index');
        Route::put('permissions', [ReportPermissionController::class, 'update'])->name('permissions.update');
    });

// 従業員: 自分の年末調整の入力。サンエー印刷・サン・ブレーンのどちらの従業員でも、受付の対象者なら使える
// （company_type では制限しない。自分の提出物だけ操作でき、他人の ID は 404）。ルート名の接頭辞 user. を保つ。
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])
    ->prefix('user/nencho')
    ->name('user.general_affairs.nencho.')
    ->group(function () {
        Route::get('/', [MyNenchoController::class, 'index'])->name('index');
        Route::post('campaigns/{campaign}/start', [MyNenchoController::class, 'start'])->name('start');
        Route::get('submissions/{submission}', [MyNenchoController::class, 'show'])->name('show');
        Route::put('submissions/{submission}/steps/{step}', [MyNenchoController::class, 'saveStep'])->whereNumber('step')->name('steps.save');
        Route::post('submissions/{submission}/carry-over', [MyNenchoController::class, 'carryOver'])->name('carry_over');
        Route::post('submissions/{submission}/submit', [MyNenchoController::class, 'submit'])->name('submit');
        Route::post('submissions/{submission}/withdraw', [MyNenchoController::class, 'withdraw'])->name('withdraw');
        Route::post('submissions/{submission}/heartbeat', [MyNenchoController::class, 'heartbeat'])->name('heartbeat');
        Route::post('submissions/{submission}/release', [MyNenchoController::class, 'release'])->name('release');
        Route::post('submissions/{submission}/takeover', [MyNenchoController::class, 'takeover'])->name('takeover');
    });

// 郵便番号から住所を検索（SunA 共通。社員情報・年末調整・受付の住所入力で使う。ログインしていれば誰でも可）
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'throttle:60,1'])
    ->get('suna/postal-lookup', [PostalLookupController::class, 'lookup'])
    ->name('suna.postal_lookup');

// 営業のカレンダー（総務・経理とは別の、営業だけの予定）。クラークのカレンダーと同じコントローラーを、ルート名 suna.sales.* で使う。
// どのカレンダーかは ClerkCalendarKind が「ルート名が suna.sales.*」から営業と判断する。休日は会社共通。
// 利用できる人は、売上レポートの閲覧を許可された人（と SuperAdmin）。サンエー印刷のみ。
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'suna_sales_report:view'])
    ->prefix('suna/sales')
    ->name('suna.sales.')
    ->group(function () {
        Route::get('dashboard', [SalesDashboardController::class, 'index'])->name('dashboard');
        Route::get('calendar', [ClerkCalendarController::class, 'index'])->name('calendar');
        Route::get('calendar/settings/schedule-rules', [ClerkScheduleRuleController::class, 'index'])->name('calendar.schedule_rules.index');
        Route::get('calendar/settings/schedule-rules/create', [ClerkScheduleRuleController::class, 'create'])->name('calendar.schedule_rules.create');
        Route::post('calendar/settings/schedule-rules/preview', [ClerkScheduleRuleController::class, 'preview'])->name('calendar.schedule_rules.preview');
        Route::post('calendar/settings/schedule-rules', [ClerkScheduleRuleController::class, 'store'])->name('calendar.schedule_rules.store');
        Route::get('calendar/settings/schedule-rules/{scheduleRule}/edit', [ClerkScheduleRuleController::class, 'edit'])->name('calendar.schedule_rules.edit');
        Route::put('calendar/settings/schedule-rules/{scheduleRule}', [ClerkScheduleRuleController::class, 'update'])->name('calendar.schedule_rules.update');
        Route::patch('calendar/settings/schedule-rules/{scheduleRule}/active', [ClerkScheduleRuleController::class, 'toggle'])->name('calendar.schedule_rules.toggle');
        Route::delete('calendar/settings/schedule-rules/{scheduleRule}', [ClerkScheduleRuleController::class, 'destroy'])->name('calendar.schedule_rules.destroy');
        Route::get('calendar/settings', [ClerkCalendarHolidayController::class, 'settings'])->name('calendar.settings');
        Route::get('calendar/settings/holidays', [ClerkCalendarHolidayController::class, 'index'])->name('calendar.holidays.index');
        Route::post('calendar/settings/holidays', [ClerkCalendarHolidayController::class, 'store'])->name('calendar.holidays.store');
        Route::put('calendar/settings/holidays/{holiday}', [ClerkCalendarHolidayController::class, 'update'])->name('calendar.holidays.update');
        Route::delete('calendar/settings/holidays/{holiday}', [ClerkCalendarHolidayController::class, 'destroy'])->name('calendar.holidays.destroy');
        Route::get('calendar/holidays', [ClerkCalendarHolidayController::class, 'data'])->name('calendar.holidays.data');
        Route::get('calendar/events', [ClerkEventController::class, 'index'])->name('calendar.events.index');
        Route::post('calendar/events', [ClerkEventController::class, 'store'])->name('calendar.events.store');
        Route::get('calendar/events/csv-export', [ClerkEventController::class, 'csvExport'])->name('calendar.events.csv_export');
        Route::post('calendar/events/csv-import', [ClerkEventController::class, 'csvImport'])->name('calendar.events.csv_import');
        Route::put('calendar/events/{event}', [ClerkEventController::class, 'update'])->name('calendar.events.update');
        Route::delete('calendar/events/{event}', [ClerkEventController::class, 'destroy'])->name('calendar.events.destroy');
        Route::patch('calendar/events/{event}/complete', [ClerkEventController::class, 'complete'])->name('calendar.events.complete');
        Route::get('calendar/week-posts', [ClerkWeekPostController::class, 'index'])->name('calendar.week_posts.index');
        Route::post('calendar/week-posts', [ClerkWeekPostController::class, 'store'])->name('calendar.week_posts.store');
        Route::delete('calendar/week-posts/{post}', [ClerkWeekPostController::class, 'destroy'])->name('calendar.week_posts.destroy');
        Route::get('calendar/colors', [ClerkCalendarColorController::class, 'index'])->name('calendar.colors.index');
        Route::patch('calendar/colors/{colorKey}', [ClerkCalendarColorController::class, 'update'])->name('calendar.colors.update');
        Route::post('calendar/colors/reorder', [ClerkCalendarColorController::class, 'reorder'])->name('calendar.colors.reorder');
    });
