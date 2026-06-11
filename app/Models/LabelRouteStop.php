<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabelRouteStop extends Model
{
    protected $fillable = [
        'route_id', 'stop_order', 'school_code', 'school_name', 'arrival_time', 'notes', 'color_category',
    ];

    protected $casts = [
        'stop_order' => 'integer',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(LabelRoute::class, 'route_id');
    }

    public function schoolMaster(): BelongsTo
    {
        return $this->belongsTo(LabelSchoolMaster::class, 'school_code', 'code');
    }
}
