<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperatorReservationNotification extends Model
{
    protected $fillable = [
        'operator_reservation_request_id',
        'user_id',
        'type',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(OperatorReservationRequest::class, 'operator_reservation_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }
}
