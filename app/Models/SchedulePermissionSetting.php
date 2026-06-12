<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchedulePermissionSetting extends Model
{
    protected $fillable = ['company_id', 'can_add_to_others_min_role'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
