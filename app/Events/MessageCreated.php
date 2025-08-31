<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use App\Models\Message;

class MessageCreated implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(Message $message)
    {
        $this->message = $message->load('fromUser');
    }

    public function broadcastOn()
    {
        // broadcast to each recipient's private channel on client side
        $channels = [];
        foreach ($this->message->recipients as $r) {
            $channels[] = new PrivateChannel('messages.' . $r->user_id);
        }
        return $channels;
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->message->id,
            'subject' => $this->message->subject,
            'body' => $this->message->body,
            'from_user_id' => $this->message->from_user_id,
            'from_user_name' => $this->message->fromUser?->name,
            'sent_at' => $this->message->sent_at?->toDateTimeString(),
        ];
    }
}
