<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = ['company_id', 'department_id', 'name', 'description', 'leader_id'];
}
