<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_room_id',
        'user_id',
        // support both body (new) and message (legacy)
        'body',
        'message',
        'type', // text, image, system, etc.
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function room()
    {
        // determine which foreign key exists on chat_messages: prefer chat_room_id, fallback to chat_id
        try {
            if (Schema::hasColumn('chat_messages', 'chat_room_id')) {
                return $this->belongsTo(ChatRoom::class, 'chat_room_id');
            }
        } catch (\Throwable $e) {
            // ignore and fallback
        }
        return $this->belongsTo(ChatRoom::class, 'chat_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function getBodyAttribute($value)
    {
        // If DB uses 'body' column, $value will be provided. Otherwise, read from 'message'
        $raw = $value;
        try {
            if (empty($raw) && Schema::hasColumn('chat_messages', 'message')) {
                $raw = $this->attributes['message'] ?? $raw;
            }
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            return decrypt($raw);
        } catch (\Exception $e) {
            return $raw; // 復号失敗時はそのまま返す
        }
    }

    public function reads()
    {
        return $this->hasMany(ChatMessageRead::class);
    }

    // Ensure when setting body it maps to the existing DB column (message) if needed
    public function setBodyAttribute($value)
    {
        try {
            if (Schema::hasColumn('chat_messages', 'body')) {
                $this->attributes['body'] = $value;
                return;
            }
        } catch (\Throwable $e) {
            // ignore
        }
        // fallback to legacy 'message' column
        $this->attributes['message'] = $value;
    }

    // When mass-assigning message (legacy), ensure body getter still works
    public function setMessageAttribute($value)
    {
        // Mirror into message attribute only; getter will read message when body absent
        $this->attributes['message'] = $value;
    }

    // Compatibility accessors for older code that referenced from_user_id / to_user_id
    public function getFromUserIdAttribute()
    {
        return $this->attributes['from_user_id'] ?? $this->attributes['user_id'] ?? null;
    }

    public function getToUserIdAttribute()
    {
        return $this->attributes['to_user_id'] ?? $this->attributes['user_id'] ?? null;
    }
}
