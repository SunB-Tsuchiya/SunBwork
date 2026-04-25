<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgressRow extends Model
{
    protected $fillable = [
        'sheet_id',
        'label',
        'order',
        'parent_id',
        'deadline',
    ];

    protected $casts = [
        'deadline' => 'date:Y-m-d',
    ];

    public function sheet()
    {
        return $this->belongsTo(ProgressSheet::class, 'sheet_id');
    }

    public function cells()
    {
        return $this->hasMany(ProgressCell::class, 'row_id');
    }

    public function children()
    {
        return $this->hasMany(ProgressRow::class, 'parent_id')->orderBy('order');
    }

    public function parent()
    {
        return $this->belongsTo(ProgressRow::class, 'parent_id');
    }
}
