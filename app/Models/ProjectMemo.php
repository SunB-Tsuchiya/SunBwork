<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectMemo extends Model
{
    use HasFactory;

    protected $table = 'project_memos';

    protected $fillable = [
        'project_id',
        'user_id',
        'date',
        'body',
        'metadata',
    ];

    protected $casts = [
        'date' => 'date',
        'metadata' => 'array',
    ];
}
