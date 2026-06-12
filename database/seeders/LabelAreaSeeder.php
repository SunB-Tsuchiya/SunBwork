<?php

namespace Database\Seeders;

use App\Models\LabelAreaMaster;
use Illuminate\Database\Seeder;

class LabelAreaSeeder extends Seeder
{
    private const AREAS = [
        '本部',
        '東海本部',
        '本部部署分',
        '関東',
        '関東スタッフ',
        '関西',
        '九州',
        '本部職員',
        '東海本部職員',
    ];

    public function run(): void
    {
        foreach (self::AREAS as $i => $name) {
            LabelAreaMaster::updateOrCreate(
                ['name' => $name],
                ['sort_order' => $i + 1, 'is_active' => true]
            );
        }
    }
}
