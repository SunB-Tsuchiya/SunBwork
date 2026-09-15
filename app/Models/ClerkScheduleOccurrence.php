<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClerkScheduleOccurrence extends Model
{
    protected $fillable = ['rule_id', 'nominal_date', 'clerk_event_id', 'state'];

    protected function casts(): array
    {
        return ['nominal_date' => 'date:Y-m-d'];
    }

    public function event()
    {
        return $this->belongsTo(ClerkEvent::class, 'clerk_event_id');
    }

    public function rule()
    {
        return $this->belongsTo(ClerkScheduleRule::class, 'rule_id')->withTrashed();
    }
}
