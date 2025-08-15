<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('companies')->insert([
            'name' => '株式会社サン・ブレーン',
            'code' => 'SUNBRAIN',
            'description' => 'サン・ブレーン本社',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
