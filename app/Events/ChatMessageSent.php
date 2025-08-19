<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\ChatMessage;

class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(ChatMessage $message)
    {
        $this->message = $message;
    }

    public function broadcastOn()
    {
        // 送信者・受信者両方にプライベートチャンネル
        return [
            new PrivateChannel('chat.' . $this->message->from_user_id),
            new PrivateChannel('chat.' . $this->message->to_user_id),
            new PrivateChannel('chatroom.' . $this->message->chat_room_id),
        ];
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->message->id,
            'chat_room_id' => $this->message->chat_room_id,
            'user_id' => $this->message->user_id,
            'user_name' => $this->message->user ? $this->message->user->name : '',
            'body' => $this->message->body,
            'type' => $this->message->type,
            'created_at' => $this->message->created_at->setTimezone('Asia/Tokyo')->format('Y-m-d H:i:s'),
        ];
    }
}
