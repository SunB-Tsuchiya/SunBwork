<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClerkScheduleRule extends Model
{
    use SoftDeletes;

    protected $fillable = ['company_id', 'created_by', 'title', 'description', 'color_key', 'recurrence',
        'day_of_month', 'ordinal', 'day_of_week', 'custom_dates', 'starts_on', 'ends_on', 'is_active'];

    protected function casts(): array
    {
        return ['starts_on' => 'date:Y-m-d', 'ends_on' => 'date:Y-m-d', 'custom_dates' => 'array', 'is_active' => 'boolean'];
    }

    public function occurrences()
    {
        return $this->hasMany(ClerkScheduleOccurrence::class, 'rule_id');
    }
}
