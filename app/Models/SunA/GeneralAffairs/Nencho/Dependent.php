<?php

namespace App\Models\SunA\GeneralAffairs\Nencho;

use Illuminate\Database\Eloquent\Model;

/** 年末調整: 扶養親族（繰り返し・最大 20） */
class Dependent extends Model
{
    protected $table = 'suna_ga_nencho_dependents';

    protected $guarded = ['id'];

    protected $hidden = ['my_number'];

    protected function casts(): array
    {
        return [
            'birthday' => 'date:Y-m-d',
            'disability_issued_on' => 'date:Y-m-d',
            'my_number' => 'encrypted',
            'my_number_provided' => 'boolean',
            'nonresident' => 'boolean',
            'address_same' => 'boolean',
            'other_earner' => 'array',
            'change' => 'array',
        ];
    }

    /** 伏せ字（下 4 桁のみ）。番号が無ければ null */
    public static function mask(?string $number): ?string
    {
        return $number ? str_repeat('●', max(strlen($number) - 4, 0)) . substr($number, -4) : null;
    }

    public function maskedMyNumber(): ?string
    {
        return self::mask($this->my_number);
    }
}
