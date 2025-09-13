<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class JobMessageCreated implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public $message;
    public $recipientIds;

    /**
     * Create a new event instance.
     */
    public function __construct($message, array $recipientIds = [])
    {
        $this->message = $message;
        $this->recipientIds = $recipientIds;
    }

    /**
     * Get the channels the event should broadcast on.
     * Broadcast separately to each recipient's private jobmessages channel.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        $channels = [];
        foreach ($this->recipientIds as $id) {
            $channels[] = new PrivateChannel('jobmessages.' . $id);
        }
        return $channels;
    }

    public function broadcastWith()
    {
        return [
            'message_id' => $this->message->id ?? null,
            'subject' => $this->message->subject ?? null,
            'from_user_name' => optional($this->message->fromUser)->name ?? null,
            'is_job' => true,
        ];
    }
}
