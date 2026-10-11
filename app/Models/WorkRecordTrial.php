<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkRecordTrial extends Model
{
    protected $fillable = [
        'user_id', 'company_id', 'department_id', 'work_date', 'recorded_time',
        'project_job_id', 'project_job_assignment_id', 'project_name_text', 'work_category', 'activity_type',
        'title', 'progress_state', 'approximate_minutes', 'note', 'series_id',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date:Y-m-d',
            'approximate_minutes' => 'integer',
        ];
    }

    public function projectJob()
    {
        return $this->belongsTo(ProjectJob::class);
    }

    public function projectJobAssignment()
    {
        return $this->belongsTo(ProjectJobAssignment::class);
    }
}
