<?php

namespace App\Models\SunA\GeneralAffairs\Nencho;

use Illuminate\Database\Eloquent\Model;

/** 年末調整: 保険料控除の繰り返し行 */
class InsuranceItem extends Model
{
    protected $table = 'suna_ga_nencho_insurance_items';

    /** 種別（kind）と、1 種別あたりの最大件数（国の様式） */
    public const KINDS = [
        'life_general' => '一般の生命保険料',
        'life_care' => '介護医療保険料',
        'life_pension' => '個人年金保険料',
        'earthquake' => '地震保険料',
        'social' => '社会保険料',
    ];

    public const MAX_PER_KIND = 20;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['pension_start_on' => 'date:Y-m-d'];
    }
}
