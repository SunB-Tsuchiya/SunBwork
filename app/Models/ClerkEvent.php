<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ClerkEvent extends Model
{
    protected $fillable = [
        'company_id', 'user_id', 'title', 'description', 'starts_at', 'ends_at', 'all_day',
        'color_key', 'completed_at', 'calendar_kind',
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

    /**
     * 会社＋カレンダーの種類で絞り込む（calendar_kind: ''=従来のカレンダー、'soumu'/'keiri'/'sales'=サンエー印刷）。
     * 種類を指定しない既存の呼び出し（forCompany 等）は、そのまま動く。
     */
    public function scopeInCalendar(\Illuminate\Database\Eloquent\Builder $query, int $companyId, string $kind = ''): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where($this->getTable() . '.company_id', $companyId)->where($this->getTable() . '.calendar_kind', $kind);
    }
}
