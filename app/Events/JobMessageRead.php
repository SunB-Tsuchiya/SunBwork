<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class JobMessageRead implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public $message_id;
    public $user_id;
    // Optional additional users to notify (e.g., the original sender)
    public $notify_user_ids = [];
    public $read_at;

    /**
     * @param int $message_id
     * @param int $user_id The user who triggered the read (usually the assignee)
     * @param array|null $notifyUserIds Additional user ids to also notify (e.g., original sender)
     */
    public function __construct($message_id, $user_id, $notifyUserIds = null)
    {
        $this->message_id = $message_id;
        $this->user_id = $user_id;
        if (is_array($notifyUserIds)) {
            $this->notify_user_ids = array_values(array_unique($notifyUserIds));
        }
    }

    public function broadcastOn()
    {
        $channels = [];
        // Always notify the acting user (so their UI can update)
        if ($this->user_id) {
            $channels[] = new PrivateChannel('jobmessages.' . $this->user_id);
        }
        // Also notify any additional users provided (e.g., the original sender)
        foreach ($this->notify_user_ids as $uid) {
            if ($uid && $uid !== $this->user_id) {
                $channels[] = new PrivateChannel('jobmessages.' . $uid);
            }
        }

        // If no channels were added for some reason, default to acting user channel
        if (empty($channels) && $this->user_id) {
            return new PrivateChannel('jobmessages.' . $this->user_id);
        }

        return $channels;
    }

    public function broadcastWith()
    {
        return ['message_id' => $this->message_id, 'read_at' => $this->read_at ?? null];
    }
}
