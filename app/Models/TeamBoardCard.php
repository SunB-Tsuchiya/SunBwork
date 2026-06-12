<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeamBoardCard extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'team_board_id', 'team_board_column_id', 'title', 'description', 'sort_order', 'card_color', 'created_by',
    ];

    public function board()
    {
        return $this->belongsTo(TeamBoard::class, 'team_board_id');
    }

    public function column()
    {
        return $this->belongsTo(TeamBoardColumn::class, 'team_board_column_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments()
    {
        return $this->morphToMany(Attachment::class, 'attachable', 'attachmentables')
            ->withPivot('role', 'order')
            ->withTimestamps();
    }
}
