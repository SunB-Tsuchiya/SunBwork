<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiConversation extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'title', 'system_prompt', 'summary_id'];

    public function messages()
    {
        return $this->hasMany(AiMessage::class)->orderBy('created_at');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
