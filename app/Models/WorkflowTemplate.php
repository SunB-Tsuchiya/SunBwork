<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowTemplate extends Model
{
    protected $fillable = [
        'name',
        'stage_config',
        'created_by',
    ];

    protected $casts = [
        'stage_config' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sheets()
    {
        return $this->hasMany(WorkflowSheet::class, 'template_id');
    }
}
