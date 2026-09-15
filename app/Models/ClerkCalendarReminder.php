<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ClerkCalendarReminder extends Model
{
    protected $fillable = ['company_id', 'created_by', 'content', 'color_key', 'starts_on', 'ends_on', 'is_active'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeVisibleOn(Builder $query, string $date): Builder
    {
        return $query->where('is_active', true)
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date);
    }

    public static function visibleForCompany(int $companyId, ?string $date = null)
    {
        $date ??= now('Asia/Tokyo')->toDateString();

        return static::forCompany($companyId)
            ->visibleOn($date)
            ->orderBy('starts_on')
            ->orderBy('id')
            ->get(['id', 'content', 'color_key']);
    }
}
