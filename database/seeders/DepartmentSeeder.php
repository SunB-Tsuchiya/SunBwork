<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Company;
use App\Models\Department;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstWhere('code', 'SUNBRAIN');
        if (!$company) {
            $this->command->info('Company SUNBRAIN not found; skipping DepartmentSeeder');
            return;
        }

        $rows = [
            ['company_id' => $company->id, 'name' => '情報出版', 'code' => 'INFO', 'description' => '情報出版部門', 'sort_order' => 0, 'active' => 1],
            ['company_id' => $company->id, 'name' => '製版', 'code' => 'SEIHAN', 'description' => '製版部門', 'sort_order' => 1, 'active' => 1],
            ['company_id' => $company->id, 'name' => 'オンデマンド', 'code' => 'ONDEMAND', 'description' => 'オンデマンド部門', 'sort_order' => 2, 'active' => 1],
        ];

        foreach ($rows as $r) {
            Department::updateOrCreate(
                ['company_id' => $r['company_id'], 'code' => $r['code']],
                ['name' => $r['name'], 'description' => $r['description'], 'sort_order' => $r['sort_order'], 'active' => $r['active']]
            );
        }
    }
}
