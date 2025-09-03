<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnitMember extends Model
{
    protected $table = 'unit_members';
    protected $fillable = ['unit_id', 'user_id'];
}
