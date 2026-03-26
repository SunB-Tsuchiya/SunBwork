<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PositionTitlesSeeder extends Seeder
{
    public function run(): void
    {
        $titles = [
            // Admin 用
            ['name' => '社長',     'applicable_role' => 'admin',  'sort_order' => 1],
            ['name' => '取締役',   'applicable_role' => 'admin',  'sort_order' => 2],
            ['name' => '役員',     'applicable_role' => 'admin',  'sort_order' => 3],
            // Leader 用
            ['name' => '部長',     'applicable_role' => 'leader', 'sort_order' => 4],
            ['name' => '次長',     'applicable_role' => 'leader', 'sort_order' => 5],
            ['name' => '課長',     'applicable_role' => 'leader', 'sort_order' => 6],
            ['name' => '課長代理', 'applicable_role' => 'leader', 'sort_order' => 7],
            ['name' => '係長',     'applicable_role' => 'leader', 'sort_order' => 8],
        ];

        foreach ($titles as $data) {
            DB::table('position_titles')->updateOrInsert(
                ['name' => $data['name'], 'applicable_role' => $data['applicable_role']],
                array_merge($data, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
