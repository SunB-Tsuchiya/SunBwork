<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminPermission extends Model
{
    protected $fillable = [
        'user_id',
        'company_management',
        'user_management',
        'team_management',
        'diary_management',
        'client_management',
        'workload_analysis',
        'worktype_setting',
        'work_record_management',
    ];

    protected $casts = [
        'company_management'     => 'boolean',
        'user_management'        => 'boolean',
        'team_management'        => 'boolean',
        'diary_management'       => 'boolean',
        'client_management'      => 'boolean',
        'workload_analysis'      => 'boolean',
        'worktype_setting'       => 'boolean',
        'work_record_management' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** 全権限オンのデフォルト値を返す */
    public static function allEnabled(): array
    {
        return [
            'company_management'     => true,
            'user_management'        => true,
            'team_management'        => true,
            'diary_management'       => true,
            'client_management'      => true,
            'workload_analysis'      => true,
            'worktype_setting'       => true,
            'work_record_management' => true,
        ];
    }
}
