<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiSummaryJob extends Model
{
    protected $table = 'ai_summary_jobs';

    protected $fillable = [
        'ai_conversation_id',
        'job_uuid',
        'status',
        'message_count',
        'chars_processed',
        'ai_summary_id',
        'api_response',
        'error',
    ];

    protected $casts = [
        'api_response' => 'array',
    ];

    public function conversation()
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function summary()
    {
        return $this->belongsTo(AiSummary::class, 'ai_summary_id');
    }
}
