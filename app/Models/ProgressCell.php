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
        'assignment_id',
    ];

    protected $casts = [
        'value_date' => 'date',
        'value_bool' => 'boolean',
    ];

    public function row()
    {
        return $this->belongsTo(ProgressRow::class, 'row_id');
    }

    public function valueUser()
    {
        return $this->belongsTo(User::class, 'value_user_id');
    }

    public function assignment()
    {
        return $this->belongsTo(ProjectJobAssignment::class, 'assignment_id');
    }
}
