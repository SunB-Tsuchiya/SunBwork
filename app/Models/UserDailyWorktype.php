<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDailyWorktype extends Model
{
    protected $fillable = ['user_id', 'date', 'worktype_id'];

    protected $casts = ['date' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function worktype()
    {
        return $this->belongsTo(Worktype::class);
    }
}
