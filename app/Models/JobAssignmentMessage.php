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
    ];

    protected $casts = [
        'attachments' => 'array',
        'read_at' => 'datetime',
    ];

    public function projectJobAssignment()
    {
        return $this->belongsTo(ProjectJobAssignment::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
