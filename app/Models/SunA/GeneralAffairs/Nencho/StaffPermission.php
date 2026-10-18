<?php

namespace App\Models\SunA\GeneralAffairs\Nencho;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * サンエー印刷 総務: 社員情報・年末調整の権限（担当者ごと）
 */
class StaffPermission extends Model
{
    protected $table = 'suna_ga_staff_permissions';

    public const ABILITIES = [
        'can_view_employees' => '社員情報（一般項目）を見る',
        'can_edit_employees' => '社員情報を編集する',
        'can_view_my_number' => 'マイナンバーを見る',
        'can_manage_nencho' => '年末調整を管理する（受付・回収・確認・代理入力）',
    ];

    protected $fillable = ['user_id', 'can_view_employees', 'can_edit_employees', 'can_view_my_number', 'can_manage_nencho', 'updated_by'];

    protected function casts(): array
    {
        return array_fill_keys(array_keys(self::ABILITIES), 'boolean');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
