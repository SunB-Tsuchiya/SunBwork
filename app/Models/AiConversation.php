<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiConversation extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'title', 'system_prompt'];

    public function messages()
    {
        return $this->hasMany(AiMessage::class)->orderBy('created_at');
    }
}
