<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowCell extends Model
{
    protected $fillable = [
        'row_id',
        'stage_key',
        'assigned_user_id',
        'assignment_id',
        'work_date',
        'work_minutes',
        'completed_at',
        'cell_note',
        'cell_note_user_id',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'work_date'    => 'date',
    ];

    public function row()
    {
        return $this->belongsTo(WorkflowRow::class, 'row_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function assignment()
    {
        return $this->belongsTo(ProjectJobAssignment::class, 'assignment_id');
    }

    public function noteUser()
    {
        return $this->belongsTo(User::class, 'cell_note_user_id');
    }
}
