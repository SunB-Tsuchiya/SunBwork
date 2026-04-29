<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoordinatorSetting extends Model
{
    protected $fillable = ['user_id', 'jobbox_group_mode'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
