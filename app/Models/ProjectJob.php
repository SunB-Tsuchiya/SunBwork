<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'jobcode',
        'title',
        'user_id',
        'client_id',
        'detail',
        'schedule',
        'completed',
    ];

    protected $casts = [
        'schedule' => 'array',
        'completed' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    // ProjectTeamMembers relation
    public function teamMembers()
    {
        // eager-load the user relation for display convenience
        return $this->hasMany(ProjectTeamMember::class, 'project_job_id');
    }

    // Schedules for this project job
    public function schedules()
    {
        return $this->hasMany(\App\Models\ProjectSchedule::class, 'project_job_id');
    }

    public function projectJobAssignments()
    {
        return $this->hasMany(\App\Models\ProjectJobAssignment::class, 'project_job_id');
    }
}
