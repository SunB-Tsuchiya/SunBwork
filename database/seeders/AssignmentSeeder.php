<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Company;
use App\Models\Department;
use App\Models\Assignment;

class AssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstWhere('code', 'SUNBRAIN');
        if (!$company) {
            $this->command->info('Company SUNBRAIN not found; skipping AssignmentSeeder');
            return;
        }

        $mapping = [
            'INFO' => [
                ['name' => '進行管理', 'code' => 'shinko', 'sort_order' => 0],
                ['name' => 'オペレーター', 'code' => 'operator', 'sort_order' => 1],
                ['name' => '校正', 'code' => 'kousei', 'sort_order' => 2],
                ['name' => '営業', 'code' => 'eigyo', 'sort_order' => 3],
                ['name' => 'そのほか', 'code' => 'other', 'sort_order' => 4],
            ],
            'SEIHAN' => [
                ['name' => '進行管理', 'code' => 'shinko', 'sort_order' => 0],
                ['name' => 'オペレーター', 'code' => 'operator', 'sort_order' => 1],
                ['name' => 'そのほか', 'code' => 'other', 'sort_order' => 2],
            ],
            'ONDEMAND' => [
                ['name' => '進行管理', 'code' => 'shinko', 'sort_order' => 0],
                ['name' => 'オペレーター', 'code' => 'operator', 'sort_order' => 1],
                ['name' => 'そのほか', 'code' => 'other', 'sort_order' => 2],
            ],
        ];

        foreach ($mapping as $deptCode => $assigns) {
            $dept = Department::where('company_id', $company->id)->where('code', $deptCode)->first();
            if (!$dept) continue;
            foreach ($assigns as $a) {
                Assignment::updateOrCreate(
                    ['department_id' => $dept->id, 'code' => $a['code']],
                    ['name' => $a['name'], 'description' => null, 'sort_order' => $a['sort_order'], 'active' => 1]
                );
            }
        }
    }
}
