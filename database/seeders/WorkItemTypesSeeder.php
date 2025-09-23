<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkItemTypesSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = DB::table('companies')->where('code', 'SUNBRAIN')->value('id');
        $departmentId = DB::table('departments')->where('code', 'INFO')->value('id');

        $types = [
            ['name' => '作成', 'slug' => 'creation', 'description' => '原稿作成', 'company_id' => $companyId, 'department_id' => $departmentId, 'coefficient' => 1.25, 'sort_order' => 0],
            ['name' => '修正', 'slug' => 'revision', 'description' => '修正作業', 'company_id' => $companyId, 'department_id' => $departmentId, 'coefficient' => 1.0, 'sort_order' => 1],
            ['name' => '校正', 'slug' => 'proofreading', 'description' => '校正・編集作業', 'company_id' => $companyId, 'department_id' => $departmentId, 'coefficient' => 1.0, 'sort_order' => 2],
            ['name' => '赤字照合', 'slug' => 'proofreading against corrections', 'description' => '赤字を照合', 'company_id' => $companyId, 'department_id' => $departmentId, 'coefficient' => 0.75, 'sort_order' => 3],
            ['name' => '編集', 'slug' => 'editing', 'description' => '編集', 'company_id' => $companyId, 'department_id' => $departmentId, 'coefficient' => 1.0, 'sort_order' => 4],
            ['name' => '確認', 'slug' => 'confirmation', 'description' => '確認作業', 'company_id' => $companyId, 'department_id' => $departmentId, 'coefficient' => 0.75, 'sort_order' => 5],
            ['name' => 'その他', 'slug' => 'other', 'description' => 'その他の作業', 'company_id' => $companyId, 'department_id' => $departmentId, 'coefficient' => 1.0, 'sort_order' => 6],
        ];

        foreach ($types as $t) {
            DB::table('work_item_types')->updateOrInsert(['slug' => $t['slug']], $t);
        }
    }
}
