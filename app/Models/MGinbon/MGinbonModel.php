<?php

namespace App\Models\MGinbon;

use Illuminate\Database\Eloquent\Model;

abstract class MGinbonModel extends Model
{
    protected $connection = 'mginbon';
}
