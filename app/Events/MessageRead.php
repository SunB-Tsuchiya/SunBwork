<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use App\Models\Message;

class MessageRead implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public $messageId;
    public $userId;

    public function __construct(Message $message, int $userId)
    {
        $this->messageId = $message->id;
        $this->userId = $userId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('messages.' . $this->userId);
    }

    public function broadcastWith()
    {
        return [
            'message_id' => $this->messageId,
            'user_id' => $this->userId,
        ];
    }
}
