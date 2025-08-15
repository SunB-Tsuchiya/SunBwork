<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssignmentSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('assignments')->insert([
            // 情報出版
            [ 'department_id' => 1, 'name' => '進行管理', 'code' => 'shinko', 'description' => null, 'sort_order' => 0, 'active' => 1, 'created_at' => now(), 'updated_at' => now(), ],
            [ 'department_id' => 1, 'name' => 'オペレーター', 'code' => 'operator', 'description' => null, 'sort_order' => 1, 'active' => 1, 'created_at' => now(), 'updated_at' => now(), ],
            [ 'department_id' => 1, 'name' => '校正', 'code' => 'kousei', 'description' => null, 'sort_order' => 2, 'active' => 1, 'created_at' => now(), 'updated_at' => now(), ],
            [ 'department_id' => 1, 'name' => '営業', 'code' => 'eigyo', 'description' => null, 'sort_order' => 3, 'active' => 1, 'created_at' => now(), 'updated_at' => now(), ],
            [ 'department_id' => 1, 'name' => 'そのほか', 'code' => 'other', 'description' => null, 'sort_order' => 4, 'active' => 1, 'created_at' => now(), 'updated_at' => now(), ],
            // 製版
            [ 'department_id' => 2, 'name' => '進行管理', 'code' => 'shinko', 'description' => null, 'sort_order' => 0, 'active' => 1, 'created_at' => now(), 'updated_at' => now(), ],
            [ 'department_id' => 2, 'name' => 'オペレーター', 'code' => 'operator', 'description' => null, 'sort_order' => 1, 'active' => 1, 'created_at' => now(), 'updated_at' => now(), ],
            [ 'department_id' => 2, 'name' => 'そのほか', 'code' => 'other', 'description' => null, 'sort_order' => 2, 'active' => 1, 'created_at' => now(), 'updated_at' => now(), ],
            // オンデマンド
            [ 'department_id' => 3, 'name' => '進行管理', 'code' => 'shinko', 'description' => null, 'sort_order' => 0, 'active' => 1, 'created_at' => now(), 'updated_at' => now(), ],
            [ 'department_id' => 3, 'name' => 'オペレーター', 'code' => 'operator', 'description' => null, 'sort_order' => 1, 'active' => 1, 'created_at' => now(), 'updated_at' => now(), ],
            [ 'department_id' => 3, 'name' => 'そのほか', 'code' => 'other', 'description' => null, 'sort_order' => 2, 'active' => 1, 'created_at' => now(), 'updated_at' => now(), ],
        ]);
    }
}
