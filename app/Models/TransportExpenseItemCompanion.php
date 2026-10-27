<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 交通費の明細の同行者（申請の突き合わせ用。同行者本人の申請とは別） */
class TransportExpenseItemCompanion extends Model
{
    protected $fillable = ['transport_expense_item_id', 'user_id'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(TransportExpenseItem::class, 'transport_expense_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
