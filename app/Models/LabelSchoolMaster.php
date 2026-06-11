<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabelSchoolMaster extends Model
{
    protected $fillable = [
        'code',
        'display_name',
        'area',
        'route',
        'stop_order',
        'default_qty',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'stop_order'  => 'integer',
        'default_qty' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
