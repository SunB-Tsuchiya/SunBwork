<?php

namespace Database\Seeders;

use App\Models\LabelIsshikiDestination;
use Illuminate\Database\Seeder;

class LabelIsshikiSeeder extends Seeder
{
    private const DESTINATIONS = [
        '本館受付',
        '研究開発',
        '恵比寿９F五十嵐様',
        'ロジ',
        '別館６Fコールセンター',
        '本部２F茂呂様',
        '本部４F教務候補生用',
        '西日暮里教務',
        '湘南台教務',
        '総務本部',
        '本部４階　教務室　松村様',
        '教務室　武田様',
        '本部４階　教務室　教務候補生用',
        '関東教務',
        '関東教務池袋',
    ];

    public function run(): void
    {
        foreach (self::DESTINATIONS as $i => $name) {
            LabelIsshikiDestination::updateOrCreate(
                ['name' => $name],
                ['sort_order' => $i + 1, 'is_active' => true]
            );
        }
    }
}
