<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    /**
     * Global before hook: superadmin can do everything
     */
    public function before(User $user, $ability)
    {
        if (isset($user->user_role) && $user->user_role === 'superadmin') {
            return true;
        }
        return null;
    }

    /**
     * Determine whether the user can view the message.
     * Sender or any recipient may view.
     */
    public function view(User $user, Message $message)
    {
        if ($user->id === $message->from_user_id) {
            return true;
        }

        // Check if user is a recipient
        return $message->recipients()->where('user_id', $user->id)->exists();
    }
}
