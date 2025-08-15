<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('departments')->insert([
            [
                'id' => 1,
                'company_id' => 1,
                'name' => '情報出版',
                'code' => 'INFO',
                'description' => '情報出版部門',
                'sort_order' => 0,
                'active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'company_id' => 1,
                'name' => '製版',
                'code' => 'SEIHAN',
                'description' => '製版部門',
                'sort_order' => 1,
                'active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'company_id' => 1,
                'name' => 'オンデマンド',
                'code' => 'ONDEMAND',
                'description' => 'オンデマンド部門',
                'sort_order' => 2,
                'active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
