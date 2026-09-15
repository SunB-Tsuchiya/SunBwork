<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ClerkEvent extends Model
{
    protected $fillable = [
        'company_id', 'user_id', 'title', 'description', 'starts_at', 'ends_at', 'all_day',
        'color_key', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'all_day' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeOverlappingRange(Builder $query, mixed $start, mixed $exclusiveEnd): Builder
    {
        return $query
            ->where('starts_at', '<', $exclusiveEnd)
            ->where(function (Builder $query) use ($start) {
                $query->where(function (Builder $query) use ($start) {
                    $query->whereNull('ends_at')->where('starts_at', '>=', $start);
                })->orWhere('ends_at', '>=', $start);
            });
    }

    public function scheduleOccurrence()
    {
        return $this->hasOne(ClerkScheduleOccurrence::class, 'clerk_event_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
