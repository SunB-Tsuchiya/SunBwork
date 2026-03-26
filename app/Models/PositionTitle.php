<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PositionTitle extends Model
{
    protected $fillable = ['name', 'applicable_role', 'sort_order'];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
