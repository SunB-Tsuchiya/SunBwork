<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Chat\ChatController;

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    // チャット画面
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.users.index');
    // ルーム内メッセージ履歴取得
    Route::get('/chat/rooms/{roomId}/messages', [ChatController::class, 'roomMessages'])->name('chat.rooms.messages');
    // ルーム内メッセージ送信（web.php側にもあるが、ZiggyやAPI用途で明示）
    Route::post('/chat/rooms/{id}/messages', [ChatController::class, 'sendRoomMessage'])->name('chat.rooms.messages.send');
    // 添付ファイルの安全な配信（?path=chat/xxx を受け取る）
    Route::get('/chat/attachments', [ChatController::class, 'streamAttachment'])->name('chat.attachments.stream');
});
