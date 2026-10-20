<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 交通費の期限（当月末日）の解除の記録 */
class TransportDeadlineExtension extends Model
{
    public const STATUS_REQUESTED = 'requested';   // 申請者からの依頼中
    public const STATUS_GRANTED   = 'granted';     // 許可（期限を解除）
    public const STATUS_REJECTED  = 'rejected';    // 却下

    protected $fillable = ['company_id', 'user_id', 'billing_month', 'status', 'granted_by', 'reason', 'requested_at', 'decided_at', 'decision_comment'];

    protected $casts = ['requested_at' => 'datetime', 'decided_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
