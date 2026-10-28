<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** チームルームのカレンダーの色設定（色ごとの名前・並び順） */
class TeamCalendarColor extends Model
{
    protected $fillable = ['team_id', 'color_key', 'label', 'sort_order', 'updated_by'];

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
