<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserEmploymentSetting extends Model
{
    protected $fillable = [
        'user_id',
        'diary_required',
        'job_registration',
        'ranking_included',
        'workload_tracked',
    ];

    protected function casts(): array
    {
        return [
            'diary_required'    => 'boolean',
            'job_registration'  => 'boolean',
            'ranking_included'  => 'boolean',
            'workload_tracked'  => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
