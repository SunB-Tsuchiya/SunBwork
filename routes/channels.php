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
// job-specific private channel for job-related notifications
Broadcast::channel('jobmessages.{userId}', function ($user, $userId) {
    // Development debug: log auth status for broadcasting auth requests.
    try {
        if (app()->environment('local') || env('APP_ENV') === 'local') {
            \Illuminate\Support\Facades\Log::debug('Broadcast auth attempt for jobmessages', [
                'route_user_id' => $userId,
                'auth_user_id' => isset($user) ? $user->id : null,
                'request_ip' => request()->ip(),
                'session_id' => session()->getId(),
            ]);
        }
    } catch (\Exception $e) {
        // ignore logging failures to avoid breaking auth flow
    }

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
