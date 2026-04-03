<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgressRow extends Model
{
    protected $fillable = [
        'sheet_id',
        'label',
        'order',
    ];

    public function sheet()
    {
        return $this->belongsTo(ProgressSheet::class, 'sheet_id');
    }

    public function cells()
    {
        return $this->hasMany(ProgressCell::class, 'row_id');
    }
}
