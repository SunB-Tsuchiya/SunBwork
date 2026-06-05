<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobFieldOption extends Model
{
    protected $fillable = [
        'name',
        'group_key',
        'company_id',
        'department_id',
        'coefficient',
        'sort_order',
    ];

    protected $casts = [
        'coefficient' => 'float',
        'sort_order'  => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
