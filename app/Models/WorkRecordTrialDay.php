<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkRecordTrialDay extends Model
{
    protected $fillable = ['user_id', 'work_date', 'reviewed_at'];

    protected function casts(): array
    {
        return [
            'work_date' => 'date:Y-m-d',
            'reviewed_at' => 'datetime',
        ];
    }
}
