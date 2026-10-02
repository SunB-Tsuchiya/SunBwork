<?php

namespace App\Models\MGinbon;

class MGinbonSavedSearch extends MGinbonModel
{
    protected $table = 'mginbon_saved_searches';

    protected $fillable = [
        'mginbon_project_id', 'owner_user_id', 'name', 'description', 'scope',
        'criteria_version', 'criteria', 'display_mode', 'sort_key',
    ];

    protected function casts(): array
    {
        return ['criteria' => 'array'];
    }
}
