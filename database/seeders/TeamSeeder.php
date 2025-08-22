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
            $department = Department::first();
            $user = User::first();
        } catch (\Throwable $e) {
            // DB 接続が無い環境や名前解決エラーなどではシードをスキップし、分かりやすく出力する
            if (isset($this->command) && method_exists($this->command, 'info')) {
                $this->command->info('TeamSeeder: database not available, skipping seeding. Error: ' . $e->getMessage());
            }
            return;
        }

        if (!$company || !$user) {
            // 必要な参照データが無ければ何もしない（手動で環境を整えてから再実行してください）
            return;
        }
        $companyName = $company->name ?? '会社';

        // 1) 会社全体チームを作成/更新
        $companyTeamAttrs = [
            'user_id' => $user->id,
            'name' => $companyName . ' 全社チーム',
            'personal_team' => false,
            'company_id' => $company->id,
            'department_id' => null,
            'team_type' => 'company',
            'description' => '会社全体のチーム',
            'updated_at' => now(),
            'created_at' => now(),
        ];

        Team::updateOrCreate(
            [
                'company_id' => $company->id,
                'team_type' => 'company',
            ],
            $companyTeamAttrs
        );

        // 2) 会社に属する全ての部署ごとにチームを作成/更新
        $departments = Department::where('company_id', $company->id)->get();

        foreach ($departments as $dept) {
            $deptName = $dept->name ?? ($companyName . ' 部署');

            $deptTeamAttrs = [
                'user_id' => $user->id,
                'name' => $deptName . ' チーム',
                'personal_team' => false,
                'company_id' => $company->id,
                'department_id' => $dept->id,
                'team_type' => 'department',
                'description' => '部署ごとのチーム',
                'updated_at' => now(),
                'created_at' => now(),
            ];

            Team::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'department_id' => $dept->id,
                    'team_type' => 'department',
                ],
                $deptTeamAttrs
            );
        }
    }
}
