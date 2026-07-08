<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperatorReservationRequest extends Model
{
    protected $fillable = [
        'conflicting_reservation_id',
        'operator_user_id',
        'requested_by_user_id',
        'job_name',
        'memo',
        'starts_at',
        'ends_at',
        'status',
        'responded_by_user_id',
        'responded_at',
        'response_message',
    ];

    protected $casts = [
        'starts_at'     => 'datetime',
        'ends_at'       => 'datetime',
        'responded_at'  => 'datetime',
    ];

    public function conflictingReservation(): BelongsTo
    {
        return $this->belongsTo(OperatorReservation::class, 'conflicting_reservation_id');
    }

    public function operatorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_user_id');
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function respondedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by_user_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(OperatorReservationNotification::class, 'operator_reservation_request_id');
    }
}
