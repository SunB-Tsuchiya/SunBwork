<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClerkScheduleRule extends Model
{
    use SoftDeletes;

    protected $fillable = ['company_id', 'created_by', 'title', 'description', 'color_key', 'recurrence',
        'day_of_month', 'ordinal', 'day_of_week', 'custom_dates', 'starts_on', 'ends_on', 'is_active', 'calendar_kind'];

    protected function casts(): array
    {
        return ['starts_on' => 'date:Y-m-d', 'ends_on' => 'date:Y-m-d', 'custom_dates' => 'array', 'is_active' => 'boolean'];
    }

    public function occurrences()
    {
        return $this->hasMany(ClerkScheduleOccurrence::class, 'rule_id');
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
