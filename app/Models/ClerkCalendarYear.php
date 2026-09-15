<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClerkCalendarYear extends Model
{
    protected $fillable = ['company_id', 'year', 'initialized_at'];
}
