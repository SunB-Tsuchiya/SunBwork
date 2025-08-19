<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
Broadcast::channel('chat.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
Broadcast::channel('chatroom.{roomId}', function ($user, $roomId) {
    // ルーム参加者ならtrue
    return \App\Models\ChatRoom::find($roomId)?->users->contains($user->id);
});