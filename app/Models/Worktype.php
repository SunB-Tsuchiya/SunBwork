<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Worktype extends Model
{
    protected $fillable = ['company_id', 'name', 'start_time', 'end_time', 'sort_order'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
