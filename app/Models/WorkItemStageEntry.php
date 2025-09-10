<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkItemStageEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_item_id',
        'stage_id',
        'performed_by',
        'started_at',
        'completed_at',
        'note',
        'sequence',
    ];

    protected $dates = ['started_at', 'completed_at'];

    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }

    public function workItem()
    {
        return $this->belongsTo(WorkItem::class);
    }
}
