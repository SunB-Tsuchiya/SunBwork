<?php

namespace App\Models\SunA\GeneralAffairs\Nencho;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * サンエー印刷 総務: 社員プロフィール（住所録の土台）
 */
class EmployeeProfile extends Model
{
    protected $table = 'suna_ga_employee_profiles';

    protected $fillable = [
        'company_id', 'user_id', 'name', 'name_kana', 'birthday', 'postal_code', 'prefecture', 'city', 'street',
        'household_head_name', 'household_head_relation', 'household_head_relation_other', 'employee_number',
        'nearest_station', 'my_number',
    ];

    /** マイナンバーは画面（Inertia）へ渡る配列・JSON に含めない */
    protected $hidden = ['my_number'];

    protected function casts(): array
    {
        return ['birthday' => 'date:Y-m-d', 'household_head_relation' => 'integer', 'my_number' => 'encrypted'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /** 伏せ字（下 4 桁のみ）。番号が無ければ null */
    public function maskedMyNumber(): ?string
    {
        $number = $this->my_number;

        return $number ? str_repeat('●', max(strlen($number) - 4, 0)) . substr($number, -4) : null;
    }
}
