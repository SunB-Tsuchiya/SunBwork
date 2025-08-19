<?php
// ---------------------------------------------------------------------------
// SPA authentication note
// ---------------------------------------------------------------------------
// This application serves a first-party SPA (Inertia / Vite). For SPA-originated
// XHRs we rely on Laravel session cookies (laravel_session) + XSRF-TOKEN for
// authentication. The default `api` middleware group is stateless and does not
// start the session; therefore SPA-facing endpoints must either be routed
// through the `web` middleware (so StartSession runs) or explicitly include
// the necessary session middleware.
//
// Decision taken here: keep SPA-facing endpoints (those the browser calls
// directly with first-party cookies) on the `web` middleware so they can use
// session-based `auth:sanctum` authentication. Non-SPA API endpoints that are
// truly token/bearer-based may remain stateless under the API middleware.
//
// See .github/PULL_REQUEST_TEMPLATE.md for PR description and verification
// steps.
// ---------------------------------------------------------------------------
// チャットメッセージ既読登録
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['web', 'auth:sanctum']);

// Note: SPA-first endpoints use session-auth via the `web` middleware so that
// browser first-party cookies (laravel_session/XSRF-TOKEN) authenticate SPA XHRs.

use App\Models\Client;
// クライアント一覧API（id, nameのみ返す）
Route::get('/clients', function () {
    return Client::select('id', 'name')->get();
});

Route::middleware(['web', 'auth:sanctum'])->group(function () {
    Route::get('/chat-rooms', [\App\Http\Controllers\Chat\ChatController::class, 'indexRooms']);
    Route::post('/chat-rooms', [\App\Http\Controllers\Chat\ChatController::class, 'storeRoom']);
    Route::get('/chat-rooms/{id}', [\App\Http\Controllers\Chat\ChatController::class, 'showRoom']);
    Route::put('/chat-rooms/{id}', [\App\Http\Controllers\Chat\ChatController::class, 'updateRoom']);
    Route::delete('/chat-rooms/{id}', [\App\Http\Controllers\Chat\ChatController::class, 'destroyRoom']);
});

Route::middleware(['web', 'auth:sanctum'])->post('/chat/messages/{message}/read', [\App\Http\Controllers\Chat\ChatController::class, 'markAsRead']);

// End of API route definitions
