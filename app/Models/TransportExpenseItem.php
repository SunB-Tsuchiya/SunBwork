<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportExpenseItem extends Model
{
    protected $fillable = [
        'transport_expense_id',
        'sort_order',
        'occurrence_date',
        'destination',
        'purpose',
        'purpose_text',
        'station_from',
        'station_to',
        'via_stations',
        'fare_type',
        'amount',
        'base_amount',
        'auto_double',
        'checked_at',
        'checked_by',
    ];

    protected $casts = [
        'occurrence_date' => 'date:Y-m-d',
        'amount' => 'integer',
        'base_amount' => 'integer',
        'auto_double' => 'boolean',
        'via_stations' => 'array',
        'checked_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public const PURPOSE_LABELS = [
        'round_trip'  => '打合せ（往復）',
        'outbound'    => '打合せ（往路）',
        'return'      => '打合せ（帰路）',
        'direct_home' => '打合せ（直帰）',
        'other'       => 'その他',
    ];

    /** 「片道の金額を×2（往復）」が使える用件（その他の用件でも、往復のことがあるため） */
    public const DOUBLE_PURPOSES = ['round_trip', 'other'];

    /** 経由地の上限・同行者の上限（1 行あたり） */
    public const MAX_VIA = 5;
    public const MAX_COMPANIONS = 5;

    /** 同行者（選んだ社員） */
    public function companions(): HasMany
    {
        return $this->hasMany(TransportExpenseItemCompanion::class, 'transport_expense_item_id');
    }

    /** 区間の駅を順に並べたもの（出発 → 経由地 → 到着）。空の駅は除く */
    public function routePoints(): array
    {
        return array_values(array_filter(
            array_merge([$this->station_from], $this->via_stations ?? [], [$this->station_to]),
            fn ($s) => trim((string) $s) !== ''
        ));
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(TransportExpense::class, 'transport_expense_id');
    }

    public function getPurposeLabelAttribute(): string
    {
        if ($this->purpose === 'other') {
            return $this->purpose_text ?: 'その他';
        }
        return self::PURPOSE_LABELS[$this->purpose] ?? $this->purpose;
    }
}
