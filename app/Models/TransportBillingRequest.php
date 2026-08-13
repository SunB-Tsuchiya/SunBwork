<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportBillingRequest extends Model
{
    protected $fillable = [
        'user_id',
        'period_start',
        'period_end',
        'total_amount',
    ];

    protected $casts = [
        'period_start' => 'date:Y-m-d',
        'period_end'   => 'date:Y-m-d',
        'total_amount' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(TransportExpense::class, 'billing_request_id');
    }
}
