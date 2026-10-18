<?php

namespace App\Models\SunA\GeneralAffairs\Nencho;

use Illuminate\Database\Eloquent\Model;

/** 年末調整の操作履歴（提出・取り下げ・差戻し） */
class SubmissionEvent extends Model
{
    protected $table = 'suna_ga_nencho_events';

    public $timestamps = false;

    public const ACTIONS = ['submitted' => '提出', 'withdrawn' => '取り下げ', 'returned' => '差戻し', 'proxy_started' => '代理入力を開始', 'profile_synced' => '社員情報との差異に対応'];

    protected $fillable = ['submission_id', 'action', 'actor_user_id', 'actor_name', 'comment', 'meta', 'created_at'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }
}
