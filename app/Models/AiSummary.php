<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiSummary extends Model
{
    protected $table = 'ai_summaries';

    protected $fillable = [
        'ai_conversation_id',
        'summary',
        'summarized_until_message_id',
        'char_count',
        'version',
        'tokens_estimate',
        'model',
        'status',
        'meta',
    ];

    public function conversation()
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }
}
