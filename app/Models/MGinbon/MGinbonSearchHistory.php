<?php

namespace App\Models\MGinbon;

class MGinbonSearchHistory extends MGinbonModel
{
    protected $table = 'mginbon_search_histories';

    protected $fillable = [
        'mginbon_project_id', 'user_id', 'criteria_version', 'criteria',
        'criteria_hash', 'summary', 'result_count', 'display_mode', 'executed_at',
    ];

    protected function casts(): array
    {
        return ['criteria' => 'array', 'executed_at' => 'datetime'];
    }
}
