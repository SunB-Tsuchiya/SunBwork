<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClerkCalendarColor extends Model
{
    protected $fillable = ['company_id', 'color_key', 'label', 'sort_order'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
