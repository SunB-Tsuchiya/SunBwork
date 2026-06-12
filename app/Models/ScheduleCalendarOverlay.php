<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleCalendarOverlay extends Model
{
    protected $fillable = [
        'user_id',
        'target_user_id',
        'target_company_id',
        'target_department_id',
        'sort_order',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function targetCompany()
    {
        return $this->belongsTo(Company::class, 'target_company_id');
    }

    public function targetDepartment()
    {
        return $this->belongsTo(Department::class, 'target_department_id');
    }
}
