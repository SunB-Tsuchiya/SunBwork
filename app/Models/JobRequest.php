<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_job_id',
        'project_job_assignment_id',
        'from_user_id',
        'to_user_id',
        'status',
        'message',
        'accepted_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function assignment()
    {
        return $this->belongsTo(ProjectJobAssignment::class, 'project_job_assignment_id');
    }

    public function projectJob()
    {
        return $this->belongsTo(ProjectJob::class, 'project_job_id');
    }
}
