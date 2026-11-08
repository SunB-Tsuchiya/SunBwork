<?php

use App\Http\Controllers\Portfolio\CollectionController;
use App\Http\Controllers\Portfolio\DataController;
use App\Http\Controllers\Portfolio\FileController;
use App\Http\Controllers\Portfolio\ItemController;
use App\Http\Controllers\Portfolio\PdfUploadController;
use App\Http\Controllers\Portfolio\PortfolioController;
use App\Http\Controllers\Portfolio\ProfileController;
use App\Http\Controllers\Portfolio\VideoUploadController;
use App\Http\Controllers\Portfolio\ViewController;
use App\Http\Controllers\Portfolio\PortfolioPermissionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| デザイナー用ポートフォリオ（SB専用）
|--------------------------------------------------------------------------
| 設計: z_instructions/PORTFOLIO_PLAN1.md
|
| ⚠️ AppLayout はルート名の接頭辞（superadmin. / admin. / …）でロール別ナビを決める。
|    `portfolio.manage` のような接頭辞なしの名前だと、全員が「ユーザー」のナビになってしまう。
|    そのため売上分析（$registerSalesAnalysisRoutes）と同じく、各ロールのルートグループの中で
|    このクロージャを呼び、`{ロール}.portfolio.*` の名前で登録する。
|
| deny_general でサンエー印刷を除外し、portfolio ミドルウェアで個人許可を確認する。
| SunA ではなく SB 側の機能なので routes/suna.php には置かない。
|
| @param bool $adminArea admin エリアか（true: タブ付きの管理画面と利用メンバー設定。false: 自分の編集画面）
*/
// ----------------------------------------------------------------------
// JSON API・ファイル配信（画面なし＝ロール接頭辞は不要。require 時に1回だけ登録する）
// ----------------------------------------------------------------------
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'deny_general', 'portfolio'])
    ->prefix('portfolio/api')
    ->name('portfolio.api.')
    ->group(function () {
        Route::get('data', [DataController::class, 'show'])->name('data');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile');

        Route::post('collections', [CollectionController::class, 'store'])->name('collections.store');
        Route::post('collections/reorder', [CollectionController::class, 'reorder'])->name('collections.reorder');
        Route::put('collections/{collection}', [CollectionController::class, 'update'])->name('collections.update');
        Route::delete('collections/{collection}', [CollectionController::class, 'destroy'])->name('collections.destroy');

        Route::post('items', [ItemController::class, 'store'])->name('items.store');
        Route::post('items/reorder', [ItemController::class, 'reorder'])->name('items.reorder');

        // PDF 取り込み（init → chunk × N → complete → process × N）
        Route::post('items/pdf/init', [PdfUploadController::class, 'init'])->name('pdf.init');
        Route::post('items/{item}/chunk', [PdfUploadController::class, 'chunk'])->name('items.chunk'); // PDF・動画共通
        Route::post('items/{item}/complete', [PdfUploadController::class, 'complete'])->name('pdf.complete');
        Route::post('items/{item}/process', [PdfUploadController::class, 'process'])->name('pdf.process');

        // 動画取り込み（init → chunk × N → video-complete。圧縮はブラウザ側で済ませてから送る）
        Route::post('items/video/init', [VideoUploadController::class, 'init'])->name('video.init');
        Route::post('items/{item}/video-complete', [VideoUploadController::class, 'complete'])->name('video.complete');
        Route::put('items/{item}', [ItemController::class, 'update'])->name('items.update');
        Route::delete('items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');

        // 認証付きファイル配信（本人のみ）
        Route::get('files/{item}/pages/{page}', [FileController::class, 'page'])->name('pages');
        Route::get('files/{item}/{kind}', [FileController::class, 'show'])->name('files');
    });

// ----------------------------------------------------------------------
// お客様に見せる閲覧ページ（AppLayout を使わない独立レイアウト＝ロール接頭辞は不要）。別タブで開く
// ----------------------------------------------------------------------
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'deny_general', 'portfolio'])
    ->get('portfolio/view', [ViewController::class, 'show'])
    ->name('portfolio.view');

// ----------------------------------------------------------------------
// AppLayout を使う画面（ロール別接頭辞で登録。web.php の各ロールのグループ内で呼ぶ）
// ----------------------------------------------------------------------
return function (bool $adminArea = false) {
    Route::middleware(['deny_general', 'portfolio'])
        ->prefix('portfolio')
        ->name('portfolio.')
        ->group(function () use ($adminArea) {
            if ($adminArea) {
                // admin エリア（Admin / SuperAdmin）: タブ付きの管理画面
                //   メンバーのポートフォリオ（登録者のカード一覧）→ 1 人のページ（閲覧専用）／ 利用メンバー設定（SuperAdmin のみ）
                Route::get('manage', [PortfolioController::class, 'overview'])->name('manage');
                Route::get('members/{member}', [PortfolioController::class, 'member'])->name('members.show');

                // 利用メンバー設定（コントローラー側でも SuperAdmin に限定）
                Route::get('permissions', [PortfolioPermissionController::class, 'index'])->name('permissions.index');
                // ユーザーごとのスイッチを、まとめて保存する（申請者管理と同じ形）
                Route::put('permissions', [PortfolioPermissionController::class, 'update'])->name('permissions.update');
            } else {
                // user エリア: 自分のポートフォリオの編集画面
                Route::get('manage', [PortfolioController::class, 'manage'])->name('manage');
            }
        });
};
