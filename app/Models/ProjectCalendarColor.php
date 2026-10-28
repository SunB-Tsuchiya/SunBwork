<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 案件のカレンダーの色設定（色ごとの名前・並び順） */
class ProjectCalendarColor extends Model
{
    protected $fillable = ['project_job_id', 'color_key', 'label', 'sort_order', 'updated_by'];

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
