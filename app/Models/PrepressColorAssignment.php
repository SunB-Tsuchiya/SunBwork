<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrepressColorAssignment extends Model
{
    protected $fillable = ['color_key', 'user_id', 'sort_order'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
