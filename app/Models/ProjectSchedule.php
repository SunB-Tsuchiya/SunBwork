<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_job_id',
        'parent_id',
        'name',
        'description',
        'start_date',
        'end_date',
        'progress',
        'status',
        'order',
        'metadata',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'metadata' => 'array',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function projectJob()
    {
        return $this->belongsTo(ProjectJob::class);
    }

    public function assignments()
    {
        return $this->hasMany(ProjectScheduleAssignment::class);
    }

    public function comments()
    {
        return $this->hasMany(ProjectScheduleComment::class);
    }
}
