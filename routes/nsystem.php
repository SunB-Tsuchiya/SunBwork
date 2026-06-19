<?php

use App\Http\Controllers\NSystem\GuestAuthController;
use App\Http\Controllers\NSystem\NdemoController;
use App\Http\Middleware\NSystem\GuestAuth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| NSystem デモ用ルート
|--------------------------------------------------------------------------
| このファイルごと削除すれば NSystem を切り離せる。
| web.php の require __DIR__ . '/nsystem.php'; も合わせて削除すること。
*/

// ゲスト認証（認証不要 — 既存スタッフが誤ってログイン画面に来ないよう /guest/ プレフィックスで分離）
Route::prefix('guest')->name('n-guest.')->group(function () {
    Route::get('/login',  [GuestAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [GuestAuthController::class, 'login'])->name('login.post');
    Route::post('/logout',[GuestAuthController::class, 'logout'])->name('logout');
});

// デモページ本体（ゲスト or スタッフ認証必須）
Route::prefix('n-demo')->name('n-demo.')->middleware([GuestAuth::class . ':n-demo'])->group(function () {
    Route::get('/',            [NdemoController::class, 'index'])->name('index');
    Route::get('/school/{id}', [NdemoController::class, 'school'])->name('school');
    Route::get('/search',      [NdemoController::class, 'search'])->name('search');
    Route::get('/search/results', [NdemoController::class, 'searchResults'])->name('search.results');
});
