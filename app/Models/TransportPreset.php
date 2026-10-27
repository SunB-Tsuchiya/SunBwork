<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 交通費の「よく使う行き先」（ユーザーごとの登録） */
class TransportPreset extends Model
{
    protected $fillable = [
        'user_id', 'destination', 'purpose', 'purpose_text', 'station_from', 'station_to', 'via_stations',
        'fare_type', 'base_amount', 'auto_double',
    ];

    protected $casts = [
        'base_amount' => 'integer',
        'auto_double' => 'boolean',
        'via_stations' => 'array',
    ];

    /** 1 人が登録できる上限 */
    public const MAX_PER_USER = 300;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 合計金額（×2 のときは片道×2） */
    public function getAmountAttribute(): int
    {
        return $this->base_amount * ($this->auto_double && in_array($this->purpose, TransportExpenseItem::DOUBLE_PURPOSES, true) ? 2 : 1);
    }
}
