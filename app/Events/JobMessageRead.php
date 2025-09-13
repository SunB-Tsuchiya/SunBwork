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

    public function __construct($message_id, $user_id)
    {
        $this->message_id = $message_id;
        $this->user_id = $user_id;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('jobmessages.' . $this->user_id);
    }

    public function broadcastWith()
    {
        return ['message_id' => $this->message_id];
    }
}
