<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamEvent extends Model
{
    protected $fillable = [
        'team_id', 'user_id', 'title', 'description', 'starts_at', 'ends_at', 'all_day',
    ];

    protected function casts(): array
    {
        return [
            'all_day'   => 'boolean',
            'starts_at' => 'datetime',
            'ends_at'   => 'datetime',
        ];
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
