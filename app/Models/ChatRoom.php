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
        return $this->hasMany(ChatMessage::class);
    }
}
