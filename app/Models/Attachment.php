<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    protected $fillable = [
        'diary_id',
        'event_id',
        'path',
        'original_name',
        'mime_type',
        'status',
        'size',
        'user_id',
        'message_id',
    ];

    public function message()
    {
        return $this->belongsTo(\App\Models\Message::class, 'message_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
