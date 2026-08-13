<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportExpense extends Model
{
    protected $fillable = [
        'user_id',
        'department_id',
        'department_code',
        'billing_date',
        'billing_month',
        'total_amount',
        'status',
        'billing_request_id',
    ];

    protected $casts = [
        'billing_date' => 'date:Y-m-d',
        'total_amount' => 'integer',
        'department_code' => 'integer',
    ];

    public const DEPARTMENT_CODES = [
        0  => '共通',
        10 => '情報出版',
        20 => '制作',
        30 => '製版',
        50 => 'オンデマンド',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function billingRequest(): BelongsTo
    {
        return $this->belongsTo(TransportBillingRequest::class, 'billing_request_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransportExpenseItem::class)->orderBy('sort_order');
    }

    public function recalculateTotal(): void
    {
        $this->total_amount = $this->items()->sum('amount');
        $this->save();
    }
}
