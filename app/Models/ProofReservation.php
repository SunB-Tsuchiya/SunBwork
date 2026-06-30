<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProofReservation extends Model
{
    protected $fillable = [
        'project_job_id',
        'requester_id',
        'title',
        'requested_at_mode',
        'requested_at',
        'requested_at_text',
        'deadline_mode',
        'deadline_at',
        'deadline_text',
        'note',
        'status',
        'calendar_registered_at',
    ];

    protected $casts = [
        'calendar_registered_at' => 'datetime',
    ];

    protected function requestedAt(): Attribute
    {
        return $this->makeUtcDateTimeAttribute('requested_at');
    }

    protected function deadlineAt(): Attribute
    {
        return $this->makeUtcDateTimeAttribute('deadline_at');
    }

    private function makeUtcDateTimeAttribute(string $column)
    {
        return Attribute::make(
            get: function ($value, $attributes) use ($column) {
                $raw = $attributes[$column] ?? null;

                return $raw
                    ? Carbon::createFromFormat('Y-m-d H:i:s', $raw, 'UTC')->setTimezone('Asia/Tokyo')
                    : null;
            },
            set: function ($value) use ($column) {
                if (! $value) {
                    return [$column => null];
                }

                $carbon = $value instanceof Carbon
                    ? $value
                    : Carbon::parse($value, 'Asia/Tokyo');

                return [$column => $carbon->utc()->format('Y-m-d H:i:s')];
            },
        );
    }

    public function projectJob(): BelongsTo
    {
        return $this->belongsTo(ProjectJob::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function canRegisterToCalendar(): bool
    {
        return $this->status !== 'deleted'
            && $this->requested_at_mode === 'datetime'
            && $this->deadline_mode === 'datetime'
            && $this->requested_at
            && $this->deadline_at
            && $this->requested_at->lt($this->deadline_at);
    }
}
