<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiPreset extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'type', 'data', 'description', 'icon'
    ];

    protected $casts = [
        'data' => 'array',
    ];
}
