<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Script extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'component_key',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
