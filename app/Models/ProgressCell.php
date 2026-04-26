<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgressCell extends Model
{
    protected $fillable = [
        'row_id',
        'col_key',
        'value_text',
        'value_date',
        'value_bool',
        'value_user_id',
        'value_subcontractor_id',
        'assignment_id',
        'proof_assignment_id',
        'cell_type',
        'schedule_id',
        'cell_deadline',
        'cell_note',
        'cell_note_user_id',
        'completed_at',
    ];

    protected $casts = [
        'value_date'   => 'date',
        'value_bool'   => 'boolean',
        'cell_deadline' => 'date',
        'completed_at'  => 'datetime',
    ];

    public function row()
    {
        return $this->belongsTo(ProgressRow::class, 'row_id');
    }

    public function valueUser()
    {
        return $this->belongsTo(User::class, 'value_user_id');
    }

    public function valueSubcontractor()
    {
        return $this->belongsTo(Subcontractor::class, 'value_subcontractor_id');
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
