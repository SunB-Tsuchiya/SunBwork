<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoordinatorProjectJobFavorite extends Model
{
    protected $fillable = ['user_id', 'project_job_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function projectJob()
    {
        return $this->belongsTo(ProjectJob::class);
    }
}
