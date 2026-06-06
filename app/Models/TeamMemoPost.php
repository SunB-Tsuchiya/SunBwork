<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamMemoPost extends Model
{
    protected $fillable = ['team_id', 'user_id', 'body', 'parent_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function replies()
    {
        return $this->hasMany(TeamMemoPost::class, 'parent_id');
    }
}
