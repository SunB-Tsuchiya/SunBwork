<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'fare_type',
        'amount',
    ];

    protected $casts = [
        'occurrence_date' => 'date:Y-m-d',
        'amount' => 'integer',
        'sort_order' => 'integer',
    ];

    public const PURPOSE_LABELS = [
        'round_trip'  => '打合せ（往復）',
        'outbound'    => '打合せ（往路）',
        'return'      => '打合せ（帰路）',
        'direct_home' => '打合せ（直帰）',
        'other'       => 'その他',
    ];

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
