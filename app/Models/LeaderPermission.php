<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaderPermission extends Model
{
    protected $fillable = [
        'user_id',
        'client_management',
        'diary_management',
        'workload_analysis',
        'workload_setting',
        'work_record_management',
        'dispatch_management',
        'user_management',
    ];

    protected $casts = [
        'client_management'      => 'boolean',
        'diary_management'       => 'boolean',
        'workload_analysis'      => 'boolean',
        'workload_setting'       => 'boolean',
        'work_record_management' => 'boolean',
        'dispatch_management'    => 'boolean',
        'user_management'        => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** 全権限オンのデフォルト値を返す */
    public static function allEnabled(): array
    {
        return [
            'client_management'      => true,
            'diary_management'       => true,
            'workload_analysis'      => true,
            'workload_setting'       => true,
            'work_record_management' => true,
            'dispatch_management'    => true,
            'user_management'        => true,
        ];
    }
}
