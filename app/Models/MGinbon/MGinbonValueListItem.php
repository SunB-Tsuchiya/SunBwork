<?php

namespace App\Models\MGinbon;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MGinbonValueListItem extends MGinbonModel
{
    protected $table = 'mginbon_value_list_items';

    protected $fillable = ['mginbon_value_list_id', 'value', 'sort_order', 'is_active', 'linked_user_id', 'linked_subcontractor_id', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function valueList(): BelongsTo
    {
        return $this->belongsTo(MGinbonValueList::class, 'mginbon_value_list_id');
    }
}
