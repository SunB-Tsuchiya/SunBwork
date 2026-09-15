<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClerkCalendarHoliday extends Model
{
    protected $fillable = ['company_id', 'date', 'name'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d'];
    }
}
