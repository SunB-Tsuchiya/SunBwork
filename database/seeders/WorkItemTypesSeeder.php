<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkItemTypesSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = \DB::table('companies')->where('code', 'SUNBRAIN')->value('id');
        $departmentId = \DB::table('departments')->where('code', 'INFO')->value('id');

        $types = [
            ['name' => '作成', 'slug' => 'creation', 'description' => '原稿作成', 'company_id' => $companyId, 'department_id' => $departmentId],
            ['name' => '修正', 'slug' => 'revision', 'description' => '修正作業', 'company_id' => $companyId, 'department_id' => $departmentId],
            ['name' => '校正', 'slug' => 'proofreading', 'description' => '校正・編集作業', 'company_id' => $companyId, 'department_id' => $departmentId],
            ['name' => '確認', 'slug' => 'confirmation', 'description' => '確認作業', 'company_id' => $companyId, 'department_id' => $departmentId],
        ];

        foreach ($types as $t) {
            DB::table('work_item_types')->updateOrInsert(['slug' => $t['slug']], $t);
        }
    }
}
