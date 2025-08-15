<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;

class TeamSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('teams')->insert([
            [
                'user_id' => 1,
                'name' => 'サンプル会社チーム',
                'personal_team' => false,
                'company_id' => 1,
                'department_id' => null,
                'team_type' => 'company',
                'description' => '会社全体のチーム',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 1,
                'name' => 'サンプル部署チーム',
                'personal_team' => false,
                'company_id' => 1,
                'department_id' => 1,
                'team_type' => 'department',
                'description' => '部署ごとのチーム',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 1,
                'name' => 'サンプル個人チーム',
                'personal_team' => true,
                'company_id' => 1,
                'department_id' => 1,
                'team_type' => 'personal',
                'description' => '個人用チーム',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
