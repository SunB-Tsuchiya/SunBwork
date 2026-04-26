<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgressSheet extends Model
{
    protected $fillable = [
        'project_job_id',
        'template_id',
        'name',
        'column_config',
        'created_by',
        'sort_order',
        'share_token',
    ];

    protected $casts = [
        'column_config' => 'array',
    ];

    public function projectJob()
    {
        return $this->belongsTo(ProjectJob::class);
    }

    public function template()
    {
        return $this->belongsTo(ProgressTemplate::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rows()
    {
        return $this->hasMany(ProgressRow::class, 'sheet_id')->orderBy('order');
    }

    public function linkSettings()
    {
        return $this->hasMany(ProjectJobItem::class, 'progress_sheet_id')->orderBy('order');
    }
}
