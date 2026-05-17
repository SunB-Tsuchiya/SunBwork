<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowCell extends Model
{
    protected $fillable = [
        'row_id',
        'stage_key',
        'cell_type',
        'assigned_user_id',
        'value_text',
        'value_date',
        'value_bool',
        'value_user_id',
        'value_subcontractor_id',
        'assignment_id',
        'proof_assignment_id',
        'schedule_id',
        'cell_deadline',
        'work_date',
        'work_minutes',
        'completed_at',
        'cell_note',
        'cell_note_user_id',
    ];

    protected $casts = [
        'completed_at'  => 'datetime',
        'work_date'     => 'date',
        'value_date'    => 'date',
        'value_bool'    => 'boolean',
        'cell_deadline' => 'date',
    ];

    public function row()
    {
        return $this->belongsTo(WorkflowRow::class, 'row_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function valueUser()
    {
        return $this->belongsTo(User::class, 'value_user_id');
    }

    public function valueSubcontractor()
    {
        return $this->belongsTo(\App\Models\Subcontractor::class, 'value_subcontractor_id');
    }

    public function assignment()
    {
        return $this->belongsTo(ProjectJobAssignment::class, 'assignment_id');
    }

    public function proofAssignment()
    {
        return $this->belongsTo(ProjectJobAssignment::class, 'proof_assignment_id');
    }

    public function schedule()
    {
        return $this->belongsTo(\App\Models\ProjectSchedule::class, 'schedule_id');
    }

    public function noteUser()
    {
        return $this->belongsTo(User::class, 'cell_note_user_id');
    }
}
