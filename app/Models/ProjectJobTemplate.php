<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectJobTemplate extends Model
{
    protected $fillable = [
        'name',
        'description',
        'fixed_fields',
        'team_members',
        'is_shared',
        'created_by',
    ];

    protected $casts = [
        'fixed_fields' => 'array',
        'team_members' => 'array',
        'is_shared'    => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
