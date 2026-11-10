<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClerkCalendarColor extends Model
{
    protected $fillable = ['company_id', 'color_key', 'label', 'sort_order', 'calendar_kind'];

    public function company()
    {
        return $this->belongsTo(Company::class);
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
