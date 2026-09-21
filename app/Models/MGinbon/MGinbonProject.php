<?php

namespace App\Models\MGinbon;

class MGinbonProject extends MGinbonModel
{
    protected $table = 'mginbon_projects';

    protected $fillable = [
        'project_job_id', 'year', 'name', 'starts_on', 'ends_on', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
        ];
    }
}
