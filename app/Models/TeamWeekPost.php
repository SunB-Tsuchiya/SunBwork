<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamWeekPost extends Model
{
    protected $fillable = ['team_id', 'year', 'week', 'user_id', 'body', 'parent_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(TeamWeekPost::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(TeamWeekPost::class, 'parent_id');
    }
}
