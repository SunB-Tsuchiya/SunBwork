<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamBoard extends Model
{
    protected $fillable = ['team_id', 'name'];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function columns()
    {
        return $this->hasMany(TeamBoardColumn::class)->orderBy('sort_order');
    }

    public function cards()
    {
        return $this->hasMany(TeamBoardCard::class);
    }
}
