<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ChatRoom extends Model
{
    use HasFactory;
    // table name may vary between environments/migrations ('chat_rooms' vs 'chats')
    // choose existing table at runtime so app doesn't 500 when one variant is missing
    protected $fillable = ['name', 'type'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        // prefer 'chat_rooms' if present, fallback to 'chats' if that exists
        if (Schema::hasTable('chat_rooms')) {
            $this->setTable('chat_rooms');
        } elseif (Schema::hasTable('chats')) {
            $this->setTable('chats');
        }
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'chat_room_user')->withTimestamps();
    }

    public function messages()
    {
        // determine foreign key used in chat_messages: prefer chat_room_id, fallback to chat_id
        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn('chat_messages', 'chat_room_id')) {
                return $this->hasMany(ChatMessage::class, 'chat_room_id');
            }
        } catch (\Throwable $e) {
            // ignore and fallback
        }
        return $this->hasMany(ChatMessage::class, 'chat_id');
    }
}
