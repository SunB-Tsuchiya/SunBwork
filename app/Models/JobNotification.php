<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobNotification extends Model
{
    protected $fillable = [
        'type',
        'sender_id',
        'recipient_id',
        'project_job_id',
        'assignment_id',
        'message',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function projectJob()
    {
        return $this->belongsTo(ProjectJob::class);
    }

    public function assignment()
    {
        return $this->belongsTo(ProjectJobAssignment::class);
    }
}
