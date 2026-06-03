<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamMeetingComment extends Model
{
    protected $fillable = ['team_meeting_minute_id', 'user_id', 'user_name', 'comment'];

    public function minute()
    {
        return $this->belongsTo(TeamMeetingMinute::class, 'team_meeting_minute_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
