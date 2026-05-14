<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectItemEntry extends Model
{
    protected $fillable = [
        'project_job_id',
        'name',
        'sort_order',
    ];

    public function projectJob()
    {
        return $this->belongsTo(ProjectJob::class);
    }

    public function workflowRows()
    {
        return $this->hasMany(WorkflowRow::class, 'item_entry_id');
    }
}
