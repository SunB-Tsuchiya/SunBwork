<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobAssignmentMessage extends Model
{
    use HasFactory;

    protected $table = 'job_assignment_messages';

    protected $fillable = [
        'project_job_assignment_id',
        'sender_id',
        'subject',
        'body',
        'message_id',
        'attachments',
        'read_at',
        'accepted',
        'scheduled',
        'scheduled_at',
        'completed',
    ];

    protected $casts = [
        'attachments' => 'array',
        'read_at' => 'datetime',
        'accepted' => 'boolean',
        'scheduled' => 'boolean',
        'scheduled_at' => 'datetime',
        'completed' => 'boolean',
    ];

    public function projectJobAssignment()
    {
        return $this->belongsTo(ProjectJobAssignment::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function message()
    {
        return $this->belongsTo(\App\Models\Message::class, 'message_id');
    }
}
