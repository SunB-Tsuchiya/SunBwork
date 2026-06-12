<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\MeetingRoom;
use Illuminate\Database\Seeder;

class MeetingRoomsSeeder extends Seeder
{
    public function run(): void
    {
        // サン・ブレーン（またはローカル最初の会社）の会議室を初期投入
        $company = Company::where('name', 'like', '%サン・ブレーン%')
            ->orWhere('name', 'like', '%sun brain%')
            ->first() ?? Company::first();

        if (!$company) {
            $this->command->warn('Company not found. Skipping MeetingRoomsSeeder.');
            return;
        }

        $rooms = [
            ['name' => '田端会議室',     'sort_order' => 1, 'color' => '#3b82f6'],
            ['name' => '田端多目的ルーム', 'sort_order' => 2, 'color' => '#10b981'],
            ['name' => '田端応接室',      'sort_order' => 3, 'color' => '#8b5cf6'],
        ];

        foreach ($rooms as $room) {
            MeetingRoom::firstOrCreate(
                ['company_id' => $company->id, 'name' => $room['name']],
                array_merge($room, ['company_id' => $company->id, 'active' => true])
            );
        }

        $this->command->info("Meeting rooms seeded for company: {$company->name}");
    }
}
