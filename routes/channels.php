<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
Broadcast::channel('chat.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
Broadcast::channel('jobrequests.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('messages.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
Broadcast::channel('chatroom.{roomId}', function ($user, $roomId) {
    // ルーム参加者ならtrue
    try {
        $room = \App\Models\ChatRoom::find($roomId);
    } catch (\Throwable $e) {
        // テーブルが存在しない等のDBエラーはここで吸収して false を返す
        return false;
    }
    return $room?->users?->contains($user->id) ?? false;
});
