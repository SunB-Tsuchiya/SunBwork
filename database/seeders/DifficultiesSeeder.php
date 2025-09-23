<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DifficultiesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['sort_order' => 0, 'name' => '軽い', 'coefficient' => 0.75],
            ['sort_order' => 1, 'name' => '普通', 'coefficient' => 1.0],
            ['sort_order' => 2, 'name' => '重い', 'coefficient' => 1.25],
            ['sort_order' => 3, 'name' => '重大', 'coefficient' => 1.5],
        ];

        $companyId = DB::table('companies')->where('code', 'SUNBRAIN')->value('id');
        $departmentId = DB::table('departments')->where('code', 'INFO')->value('id');

        foreach ($rows as $r) {
            $insert = [
                'name' => $r['name'],
                'sort_order' => $r['sort_order'],
                'coefficient' => $r['coefficient'],
                'company_id' => $companyId,
                'department_id' => $departmentId,
            ];

            DB::table('difficulties')->updateOrInsert(
                ['name' => $r['name'], 'company_id' => $companyId, 'department_id' => $departmentId],
                $insert
            );
        }
    }
}
