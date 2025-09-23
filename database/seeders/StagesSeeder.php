<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StagesSeeder extends Seeder
{
    public function run(): void
    {
        $stages = [
            ['name' => '初校', 'order_index' => 1, 'description' => '初校', 'sort_order' => 0, 'coefficient' => 1.0],
            ['name' => '再校', 'order_index' => 2, 'description' => '再校', 'sort_order' => 1, 'coefficient' => 1.0],
            ['name' => '三校', 'order_index' => 3, 'description' => '三校', 'sort_order' => 2, 'coefficient' => 1.0],
            ['name' => '四校', 'order_index' => 4, 'description' => '四校', 'sort_order' => 3, 'coefficient' => 1.0],
            ['name' => '五校', 'order_index' => 5, 'description' => '五校', 'sort_order' => 4, 'coefficient' => 1.0],
            ['name' => '確認校', 'order_index' => 6, 'description' => '確認校（最終チェック）', 'sort_order' => 5, 'coefficient' => 1.0],
            ['name' => '校了', 'order_index' => 7, 'description' => '校了（完了）', 'sort_order' => 6, 'coefficient' => 1.0],
        ];

        $companyId = DB::table('companies')->where('code', 'SUNBRAIN')->value('id');
        $departmentId = DB::table('departments')->where('code', 'INFO')->value('id');

        foreach ($stages as $st) {
            $insert = $st;
            $insert['company_id'] = $companyId;
            $insert['department_id'] = $departmentId;
            DB::table('stages')->updateOrInsert(['name' => $st['name']], $insert);
        }
    }
}
