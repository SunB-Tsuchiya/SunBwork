<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgressTemplate extends Model
{
    protected $fillable = [
        'name',
        'description',
        'column_config',
        'row_config',
        'sheet_type',
        'created_by',
        'is_shared',
    ];

    protected $casts = [
        'column_config' => 'array',
        'row_config'    => 'array',
        'is_shared'     => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
