<?php

namespace App\Models\SunA\GeneralAffairs\Nencho;

use Illuminate\Database\Eloquent\Model;

/** 年末調整の案内（お知らせ）の送信履歴 */
class Notice extends Model
{
    protected $table = 'suna_ga_nencho_notices';

    public $timestamps = false;

    public const KINDS = ['start' => '受付開始のお知らせ', 'reminder' => '未提出の方への催促'];

    protected $fillable = ['campaign_id', 'kind', 'announcement_id', 'title', 'recipient_count', 'sent_by', 'sent_by_name', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
