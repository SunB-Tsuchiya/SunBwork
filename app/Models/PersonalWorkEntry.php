<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalWorkEntry extends Model
{
    protected $fillable = [
        'user_id', 'company_id', 'department_id', 'work_date', 'work_category', 'activity_type',
        'title', 'project_name_text', 'plan_slot', 'planned_start_time',
        'planned_end_time', 'actual_quantity', 'actual_unit', 'actual_minutes', 'result_note',
        'entry_visibility', 'project_job_id', 'broad_project_id', 'project_job_assignment_id',
        'source_event_id', 'diary_id',
    ];

    protected $casts = [
        'work_date' => 'date:Y-m-d',
        'actual_quantity' => 'decimal:2',
        'actual_minutes' => 'integer',
    ];

    public function projectJob()
    {
        return $this->belongsTo(ProjectJob::class);
    }

    public function broadProject()
    {
        return $this->belongsTo(BroadProject::class);
    }

    public function sourceEvent()
    {
        return $this->belongsTo(Event::class, 'source_event_id');
    }

    public function diary()
    {
        return $this->belongsTo(Diary::class);
    }
}
