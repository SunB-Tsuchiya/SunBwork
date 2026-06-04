<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamMeetingMinute extends Model
{
    protected $fillable = ['team_id', 'user_id', 'title', 'content', 'held_at'];

    protected function casts(): array
    {
        return ['held_at' => 'date:Y-m-d'];
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attendees()
    {
        return $this->hasMany(TeamMeetingAttendee::class);
    }

    public function attendeeUsers()
    {
        return $this->belongsToMany(User::class, 'team_meeting_attendees')->withTimestamps();
    }

    public function comments()
    {
        return $this->hasMany(TeamMeetingComment::class)->orderBy('created_at');
    }

    public function attachments()
    {
        return $this->morphToMany(Attachment::class, 'attachable', 'attachmentables')
            ->withPivot('role', 'order')
            ->withTimestamps();
    }
}
