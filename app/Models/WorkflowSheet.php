<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowSheet extends Model
{
    protected $fillable = [
        'project_job_id',
        'template_id',
        'name',
        'stage_config',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'stage_config' => 'array',
    ];

    public function projectJob()
    {
        return $this->belongsTo(ProjectJob::class);
    }

    public function template()
    {
        return $this->belongsTo(WorkflowTemplate::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rows()
    {
        return $this->hasMany(WorkflowRow::class, 'sheet_id')->orderBy('sort_order');
    }
}
