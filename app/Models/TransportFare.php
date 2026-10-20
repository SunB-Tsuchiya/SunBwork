<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 運賃マスタ（総務・経理が管理する、経路と運賃の台帳。会社ごと） */
class TransportFare extends Model
{
    public const STATUS_VERIFIED   = 'verified';
    public const STATUS_UNVERIFIED = 'unverified';
    public const STATUS_WANTED     = 'wanted';

    public const STATUS_LABELS = [
        self::STATUS_VERIFIED   => '検査済み',
        self::STATUS_UNVERIFIED => '未検査',
        self::STATUS_WANTED     => '登録したい',
    ];

    protected $fillable = [
        'company_id', 'route_key', 'station_a', 'station_b', 'fare_ic', 'fare_ticket', 'status',
        'reported_fare', 'reported_fare_type', 'note', 'use_count', 'last_used_at', 'checked_by', 'checked_at', 'source',
    ];

    protected $casts = [
        'fare_ic' => 'integer', 'fare_ticket' => 'integer', 'reported_fare' => 'integer', 'use_count' => 'integer',
        'last_used_at' => 'datetime', 'checked_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    /** 駅名の比較用の正規化: 全角半角・空白・末尾の「駅」・ヶ/ヵ/ケ のゆれをそろえる */
    public static function normalizeStation(?string $name): string
    {
        $s = mb_convert_kana((string) $name, 'KVas', 'UTF-8');          // 半角カナ→全角、全角英数→半角
        $s = mb_strtolower(preg_replace('/\s+/u', '', $s));
        $s = preg_replace('/駅$/u', '', $s);
        return strtr($s, ['ヶ' => 'ケ', 'ヵ' => 'ケ']);
    }

    /** 経路の比較用キー（出発・到着の方向は問わない） */
    public static function routeKey(?string $a, ?string $b): string
    {
        $pair = [self::normalizeStation($a), self::normalizeStation($b)];
        sort($pair, SORT_STRING);
        return implode('|', $pair);
    }

    /** 運賃マスタの検査から日数がたって、要再確認か */
    public function needsRecheck(): bool
    {
        return $this->status === self::STATUS_VERIFIED && $this->checked_at
            && $this->checked_at->lt(now()->subDays((int) config('transport.recheck_after_days', 365)));
    }

    /** 運賃（片道）。IC / 切符 */
    public function fareFor(string $fareType): ?int
    {
        return $fareType === 'ticket' ? $this->fare_ticket : $this->fare_ic;
    }
}
