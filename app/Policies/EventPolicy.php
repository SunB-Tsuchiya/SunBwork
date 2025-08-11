<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * イベント閲覧権限
     */
    public function view(User $user, Event $event)
    {
        return $event->user_id === $user->id;
    }

    /**
     * イベント更新権限
     */
    public function update(User $user, Event $event)
    {
        return $event->user_id === $user->id;
    }

    /**
     * イベント削除権限
     */
    public function delete(User $user, Event $event)
    {
        return $event->user_id === $user->id;
    }
}
