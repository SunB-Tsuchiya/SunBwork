<?php

namespace App\Models\SunA\GeneralAffairs\Nencho;

use Illuminate\Database\Eloquent\Model;

/** 年末調整: 配偶者（提出物に 1 件） */
class Spouse extends Model
{
    protected $table = 'suna_ga_nencho_spouses';

    protected $guarded = ['id'];

    protected $hidden = ['my_number'];

    protected function casts(): array
    {
        return [
            'birthday' => 'date:Y-m-d',
            'disability_issued_on' => 'date:Y-m-d',
            'my_number' => 'encrypted',
            'my_number_provided' => 'boolean',
            'address_same' => 'boolean',
            'nonresident' => 'boolean',
            'elderly' => 'boolean',
            'change' => 'array',
        ];
    }

    public function maskedMyNumber(): ?string
    {
        return Dependent::mask($this->my_number);
    }
}
