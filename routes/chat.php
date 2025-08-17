<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Chat\ChatController;

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    // チャット画面
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    // チャット履歴取得
    Route::get('/chat/messages/{userId}', [ChatController::class, 'messages'])->name('chat.messages');
    // チャット送信
    Route::post('/chat/messages', [ChatController::class, 'send'])->name('chat.send');
});
