<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

use App\Models\Client;
// クライアント一覧API（id, nameのみ返す）
Route::get('/clients', function () {
    return Client::select('id', 'name')->get();
});

// チャットルームAPI
use App\Http\Controllers\ChatRoomController;
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/chat-rooms', [ChatRoomController::class, 'index']);
    Route::post('/chat-rooms', [ChatRoomController::class, 'store']);
    Route::get('/chat-rooms/{id}', [ChatRoomController::class, 'show']);
    Route::put('/chat-rooms/{id}', [ChatRoomController::class, 'update']);
    Route::delete('/chat-rooms/{id}', [ChatRoomController::class, 'destroy']);
});
