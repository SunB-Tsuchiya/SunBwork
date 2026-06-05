<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartmentFieldConfig extends Model
{
    protected $fillable = [
        'department_id',
        'slot',
        'label',
        'enabled',
        'allowed_item_ids',
        'sort_order',
        'source',
        'source_group',
    ];

    protected $casts = [
        'enabled'          => 'boolean',
        'allowed_item_ids' => 'array',
        'sort_order'       => 'integer',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
