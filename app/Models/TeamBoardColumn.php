<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamBoardColumn extends Model
{
    protected $fillable = ['team_board_id', 'name', 'color', 'sort_order'];

    public function board()
    {
        return $this->belongsTo(TeamBoard::class, 'team_board_id');
    }

    public function cards()
    {
        return $this->hasMany(TeamBoardCard::class)->orderBy('sort_order');
    }
}
