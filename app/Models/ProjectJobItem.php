<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectJobItem extends Model
{
    protected $fillable = [
        'progress_sheet_id',
        'name',
        'type',
        'row_id',
        'col_key',
        'parent_label',
        'calendar_linked',
        'order',
    ];

    protected $casts = [
        'calendar_linked' => 'boolean',
    ];

    public function sheet()
    {
        return $this->belongsTo(ProgressSheet::class, 'progress_sheet_id');
    }

    public function row()
    {
        return $this->belongsTo(ProgressRow::class, 'row_id');
    }

    public function schedules()
    {
        return $this->hasMany(ProjectSchedule::class);
    }
}
