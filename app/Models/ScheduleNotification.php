<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleNotification extends Model
{
    protected $fillable = ['event_id', 'user_id', 'type', 'scheduled_at', 'notified_at', 'read_at'];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'notified_at'  => 'datetime',
        'read_at'      => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }
}
