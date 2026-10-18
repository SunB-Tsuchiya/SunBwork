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
