<?php

namespace App\Models\MGinbon;

use Illuminate\Database\Eloquent\Relations\HasMany;

class MGinbonValueList extends MGinbonModel
{
    protected $table = 'mginbon_value_lists';

    protected $fillable = ['mginbon_project_id', 'code', 'name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(MGinbonValueListItem::class, 'mginbon_value_list_id')->orderBy('sort_order')->orderBy('id');
    }
}
