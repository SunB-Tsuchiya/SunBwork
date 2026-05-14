<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowRow extends Model
{
    protected $fillable = [
        'sheet_id',
        'parent_id',
        'label',
        'sort_order',
        'item_entry_id',
    ];

    public function sheet()
    {
        return $this->belongsTo(WorkflowSheet::class, 'sheet_id');
    }

    public function itemEntry()
    {
        return $this->belongsTo(ProjectItemEntry::class, 'item_entry_id');
    }

    public function cells()
    {
        return $this->hasMany(WorkflowCell::class, 'row_id');
    }

    public function parent()
    {
        return $this->belongsTo(WorkflowRow::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(WorkflowRow::class, 'parent_id')->orderBy('sort_order');
    }
}
