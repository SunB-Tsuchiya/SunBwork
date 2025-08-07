<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// User Dashboard (default authenticated users)
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        $user = Auth::user();

        // 最初のログイン時は最高権限のダッシュボードにリダイレクト
        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        } elseif ($user->isOwner()) {
            return redirect()->route('owner.dashboard');
        }

        // Regular user dashboard
        return Inertia::render('Dashboard', [
            'user' => $user,
        ]);
    })->name('dashboard');

    // User Dashboard (一般ユーザー機能 - Admin, Ownerもアクセス可能)
    Route::get('/user/dashboard', function () {
        return Inertia::render('Dashboard', [
            'user' => Auth::user(),
        ]);
    })->name('user.dashboard');

    // チーム切り替え
    Route::put('/current-team', [App\Http\Controllers\CurrentTeamController::class, 'update'])->name('current-team.update');
});

// Owner Routes (OwnerとAdminがアクセス可能)
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'owner'])
    ->prefix('owner')
    ->name('owner.')
    ->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\Owner\DashboardController::class, 'index'])->name('dashboard');
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
    });
