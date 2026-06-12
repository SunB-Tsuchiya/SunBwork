<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleAttendee extends Model
{
    protected $fillable = ['event_id', 'user_id', 'status', 'added_by'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
