<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabelRoute extends Model
{
    protected $fillable = [
        'code', 'course', 'area', 'day1', 'day1_start', 'day2', 'day2_start', 'sort_order',
    ];

    protected $casts = [
        'course'     => 'integer',
        'sort_order' => 'integer',
    ];

    public function stops(): HasMany
    {
        return $this->hasMany(LabelRouteStop::class, 'route_id')->orderBy('stop_order');
    }
}
