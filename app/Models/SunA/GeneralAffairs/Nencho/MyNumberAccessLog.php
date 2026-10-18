<?php

namespace App\Models\SunA\GeneralAffairs\Nencho;

use Illuminate\Database\Eloquent\Model;

/**
 * サンエー印刷 総務: マイナンバーの閲覧・登録・変更の記録
 */
class MyNumberAccessLog extends Model
{
    protected $table = 'suna_ga_my_number_access_logs';

    public $timestamps = false;

    public const ACTIONS = ['view' => '全文を表示', 'update' => '登録・変更', 'clear' => '削除'];

    protected $fillable = ['viewer_user_id', 'viewer_name', 'employee_profile_id', 'employee_name', 'action', 'ip_address', 'accessed_at'];

    protected function casts(): array
    {
        return ['accessed_at' => 'datetime'];
    }
}
