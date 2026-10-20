<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 交通費の状態変更の履歴 */
class TransportExpenseEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['transport_expense_id', 'user_id', 'action', 'from_status', 'to_status', 'comment'];

    protected $casts = ['created_at' => 'datetime'];

    public const ACTION_LABELS = [
        'submit'    => '申請',
        'withdraw'  => '取り下げ',
        'approve'   => '受理',
        'return'    => '差戻し',
        'bill'      => '請求済みにした',
        'unapprove' => '受理を取り消し',
        'unbill'    => '請求済みを取り消し',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(TransportExpense::class, 'transport_expense_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
