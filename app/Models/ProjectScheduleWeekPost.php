<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectScheduleWeekPost extends Model
{
    protected $fillable = ['project_job_id', 'user_id', 'year', 'week', 'parent_id', 'body'];

    public function projectJob()
    {
        return $this->belongsTo(ProjectJob::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(ProjectScheduleWeekPost::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(ProjectScheduleWeekPost::class, 'parent_id')->orderBy('created_at');
    }
}
