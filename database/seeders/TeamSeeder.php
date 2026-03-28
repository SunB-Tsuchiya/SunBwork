<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Team;
use App\Models\Company;
use App\Models\Department;
use App\Models\User;

class TeamSeeder extends Seeder
{
    public function run(): void
    {
        // 環境に既に存在する Company / Department / User を参照してシード
        try {
            $company = Company::where('code', 'SUNBRAIN')->first() ?? Company::first();
            $user = User::first();
        } catch (\Throwable $e) {
            if (isset($this->command) && method_exists($this->command, 'info')) {
                $this->command->info('TeamSeeder: database not available, skipping seeding. Error: ' . $e->getMessage());
            }
            return;
        }

        if (!$company || !$user) {
            return;
        }

        $companyName = $company->name ?? '会社';
        $companyName = preg_replace('/\s*全社チーム$|\s*チーム$/u', '', $companyName);

        // 部署ごとにチームを作成/更新（会社全体チームは作らない）
        $departments = Department::where('company_id', $company->id)->get();

        foreach ($departments as $dept) {
            $deptName = $dept->name ?? ($companyName . ' 部署');
            $deptName = preg_replace('/\s*チーム$/u', '', $deptName);

            Team::updateOrCreate(
                [
                    'company_id'    => $company->id,
                    'department_id' => $dept->id,
                    'team_type'     => 'department',
                ],
                [
                    'user_id'      => $user->id,
                    'name'         => $deptName,
                    'personal_team'=> false,
                    'company_id'   => $company->id,
                    'department_id'=> $dept->id,
                    'team_type'    => 'department',
                    'description'  => '部署ごとのチーム',
                    'updated_at'   => now(),
                    'created_at'   => now(),
                ]
            );
        }
    }
}
