<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Changelog extends Model
{
    use HasFactory;

    protected $fillable = [
        'version',
        'title',
        'released_at',
        'summary',
        'body',
        'design_files',
        'claude_notes',
    ];

    protected $casts = [
        'released_at' => 'date',
        'design_files' => 'array',
    ];
}
