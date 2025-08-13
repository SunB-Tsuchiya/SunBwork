<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

// デバッグ用ルート
require __DIR__ . '/debug.php';

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// User Dashboard (default authenticated users)
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    // カレンダー画面
    Route::get('/calendar', [App\Http\Controllers\CalendarController::class, 'index'])->name('calendar.index');
    // カレンダーからのイベント時間変更用
    Route::put('/events/{id}/calendar', [App\Http\Controllers\EventController::class, 'update_from_calendar'])->name('events.update_from_calendar');

    // 予定（イベント）API
    Route::get('/events', [App\Http\Controllers\EventController::class, 'index'])->name('events.index');
    Route::post('/events', [App\Http\Controllers\EventController::class, 'store'])->name('events.store');
    Route::put('/events/{event}', [App\Http\Controllers\EventController::class, 'update'])->name('events.update');
    Route::delete('/events/{event}', [App\Http\Controllers\EventController::class, 'destroy'])->name('events.destroy');

    Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

    // User Dashboard (一般ユーザー機能 - Admin, Leaderもアクセス可能)
    Route::get('/user/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('user.dashboard');

    // チーム切り替え
    Route::put('/current-team', [App\Http\Controllers\CurrentTeamController::class, 'update'])->name('current-team.update');

    // 日報機能（作成、保存、表示、編集、更新、削除）
    Route::resource('diaries', App\Http\Controllers\DiaryController::class)
        ->only(['create', 'store', 'show', 'edit', 'update', 'destroy', 'index']);

    // イベント機能（作成、保存、表示、編集、更新）
    Route::resource('events', App\Http\Controllers\EventController::class)->only([
        'create', 'store', 'show', 'edit', 'update'
    ]);
});

// Leader Routes (LeaderとAdminがアクセス可能)
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'leader'])
    ->prefix('leader')
    ->name('leader.')
    ->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\Leader\DashboardController::class, 'index'])->name('dashboard');
    });

// Admin Routes (Adminのみアクセス可能)
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');

        // CSV一括登録（リソースルートより前に配置）
        Route::get('users/csv/upload', [App\Http\Controllers\Admin\UserController::class, 'csvUpload'])->name('users.csv.upload');
        Route::post('users/csv/preview', [App\Http\Controllers\Admin\UserController::class, 'csvPreview'])->name('users.csv.preview');
        Route::post('users/csv/store', [App\Http\Controllers\Admin\UserController::class, 'csvStore'])->name('users.csv.store');

        // ユーザー管理
        Route::resource('users', App\Http\Controllers\Admin\UserController::class);

        // 会社管理
        Route::resource('companies', App\Http\Controllers\Admin\CompanyController::class);
    });

// デバッグ・テスト用ページのルート例
// 今後も任意のVueページをテスト表示したい場合は、下記のようにInertia::renderでページ名を指定してください。
// 例: /debug/create → resources/js/Pages/Diaries/CreateDebug.vue
// 例: /debug/other  → resources/js/Pages/OtherDebug.vue


Route::get('/debug/create', function() {
    return Inertia::render('Diaries/CreateDebug');
})->name('debug.create');