<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'work_item_type_id',
        'size_id',
        'pages',
        'quantity',
        'estimated_minutes',
        'status',
        'specs',
        'created_by',
        'team_id',
    ];

    protected $casts = [
        'specs' => 'array',
    ];

    public function type()
    {
        return $this->belongsTo(WorkItemType::class, 'work_item_type_id');
    }

    public function size()
    {
        return $this->belongsTo(Size::class);
    }

    public function stages()
    {
        return $this->hasMany(WorkItemStageEntry::class);
    }
}
