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
        'estimated_hours',
        'title',
        'detail',
        'difficulty',
        'desired_at',
        'desired_start_date',
        'desired_end_date',
        'desired_time',
        'assigned',
        'accepted',
        // lookup fields migrated from WorkItem
        'size_id',
        'work_item_type_id',
        'stage_id',
        'status_id',
        'company_id',
        'department_id',
    ];

    protected $casts = [
        'desired_at' => 'datetime',
        // serialize dates as plain Y-m-d to avoid ISO timestamp with T and timezone
        'desired_start_date' => 'date:Y-m-d',
        'desired_end_date' => 'date:Y-m-d',
        'desired_time' => 'string',
        // estimated_hours stores fractional hours, e.g. 1.5 == 1 hour 30 minutes
        'estimated_hours' => 'float',
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

    // Relations to lookup models (optional)
    public function workItemType()
    {
        return $this->belongsTo(WorkItemType::class, 'work_item_type_id');
    }

    public function size()
    {
        return $this->belongsTo(Size::class, 'size_id');
    }

    public function stage()
    {
        return $this->belongsTo(Stage::class, 'stage_id');
    }

    public function statusModel()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
