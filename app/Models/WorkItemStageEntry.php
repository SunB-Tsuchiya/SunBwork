<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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


    /**
     * Safe accessor for the related work item.
     * Returns null if the work_items table or WorkItem model is not present.
     */
    public function workItemSafe()
    {
        // If the work_items table doesn't exist, return null to avoid fatal errors
        if (!Schema::hasTable('work_items')) {
            return null;
        }

        // Try to use the WorkItem model if available, fall back to DB query
        if (class_exists(\App\Models\WorkItem::class)) {
            return $this->belongsTo(\App\Models\WorkItem::class, 'work_item_id');
        }

        // Fallback: return raw DB row as object
        $row = DB::table('work_items')->where('id', $this->work_item_id)->first();
        return $row ?: null;
    }
}
