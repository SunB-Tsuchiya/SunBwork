<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomReservation extends Model
{
    protected $fillable = ['meeting_room_id', 'user_id', 'event_id', 'title', 'starts_at', 'ends_at', 'notes'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    public function meetingRoom()
    {
        return $this->belongsTo(MeetingRoom::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
