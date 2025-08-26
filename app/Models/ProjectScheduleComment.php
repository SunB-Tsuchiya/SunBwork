<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectScheduleComment extends Model
{
    use HasFactory;

    protected $fillable = ['project_schedule_id', 'user_id', 'body', 'metadata'];

    protected $casts = ['metadata' => 'array'];

    public function schedule()
    {
        return $this->belongsTo(ProjectSchedule::class, 'project_schedule_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
