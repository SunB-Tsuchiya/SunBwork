<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgressTemplate extends Model
{
    protected $fillable = [
        'name',
        'description',
        'column_config',
        'created_by',
        'is_shared',
    ];

    protected $casts = [
        'column_config' => 'array',
        'is_shared'     => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
