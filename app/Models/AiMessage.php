<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiMessage extends Model
{
    use HasFactory;

    protected $fillable = ['ai_conversation_id', 'user_id', 'role', 'content', 'meta', 'char_count'];

    protected $casts = [
        'meta' => 'array'
    ];

    public function conversation()
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    /**
     * Automatically populate char_count when creating a message.
     */
    protected static function booted()
    {
        static::creating(function ($message) {
            try {
                if (isset($message->content) && $message->content !== null) {
                    $message->char_count = mb_strlen($message->content);
                } else {
                    $message->char_count = 0;
                }
            } catch (\Throwable $e) {
                // Fallback: ensure char_count is at least 0 on errors
                $message->char_count = 0;
            }
        });
    }
}
