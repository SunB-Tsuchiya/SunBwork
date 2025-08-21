<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_room_id',
        'user_id',
        'body',
        'type', // text, image, system, etc.
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function room()
    {
        return $this->belongsTo(ChatRoom::class, 'chat_room_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    

    public function getBodyAttribute($value)
    {
        try {
            return decrypt($value);
        } catch (\Exception $e) {
            return $value; // 復号失敗時はそのまま返す
        }
    }
    
    public function reads()
    {
        return $this->hasMany(ChatMessageRead::class);
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
