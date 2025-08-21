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
    $departmentName = $department->name ?? null;

        $teams = [
            [
                'user_id' => $user->id,
                'name' => $companyName . ' 全社チーム',
                'personal_team' => false,
                'company_id' => $company->id,
                'department_id' => null,
                'team_type' => 'company',
                'description' => '会社全体のチーム',
            ],
            [
                'user_id' => $user->id,
                'name' => $departmentName ? ($departmentName . ' チーム') : ($companyName . ' 部署チーム'),
                'personal_team' => false,
                'company_id' => $company->id,
                'department_id' => $department ? $department->id : null,
                'team_type' => 'department',
                'description' => '部署ごとのチーム',
            ],
            // 個人チームは自動で作成しない
        ];

        foreach ($teams as $t) {
            Team::updateOrCreate(
                [
                    'name' => $t['name'],
                    'company_id' => $t['company_id'],
                    'team_type' => $t['team_type'],
                ],
                array_merge($t, [
                    'updated_at' => now(),
                    'created_at' => now(),
                ])
            );
        }
    }
}
