<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    protected $fillable = [
        'user_id',
        'worktype_id',
        'calendar_view',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function worktype()
    {
        return $this->belongsTo(Worktype::class);
    }
}
