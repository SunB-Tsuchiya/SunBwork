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
    
    // bodyを暗号化して保存、復号して取得
    public function setBodyAttribute($value)
    {
        $this->attributes['body'] = encrypt($value);
    }

    public function getBodyAttribute($value)
    {
        try {
            return decrypt($value);
        } catch (\Exception $e) {
            return $value; // 復号失敗時はそのまま返す
        }
    }
}
