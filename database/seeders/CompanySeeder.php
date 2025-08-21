<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Company;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        // id を固定しないで常に company を取得/作成する
        Company::firstOrCreate(
            ['code' => 'SUNBRAIN'],
            ['name' => '株式会社サン・ブレーン', 'description' => 'サン・ブレーン本社', 'active' => 1]
        );
    }
}
