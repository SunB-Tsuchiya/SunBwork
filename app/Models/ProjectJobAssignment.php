<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectJobAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_job_id',
        'user_id',
        'title',
        'detail',
        'difficulty',
    'desired_at',
    'desired_start_date',
    'desired_end_date',
    'desired_time',
    'assigned',
    'accepted',
    ];

    protected $casts = [
        'desired_at' => 'datetime',
        // serialize dates as plain Y-m-d to avoid ISO timestamp with T and timezone
        'desired_start_date' => 'date:Y-m-d',
        'desired_end_date' => 'date:Y-m-d',
        'desired_time' => 'string',
    'assigned' => 'boolean',
    'accepted' => 'boolean',
    ];

    protected $dates = [
        'desired_start_date',
        'desired_end_date',
        'desired_at',
    ];

    public function projectJob()
    {
        return $this->belongsTo(ProjectJob::class, 'project_job_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
