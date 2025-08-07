<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Team;

class DepartmentTeamsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminUserId = 1; // 管理者ユーザーをオーナーとして設定

        // 出力部チーム
        $outputTeam = Team::create([
            'name' => 'サン・ブレーン - 出力部',
            'user_id' => $adminUserId, // 管理者をオーナーに設定
            'company_id' => 1,
            'department_id' => 2,
            'team_type' => 'department',
            'personal_team' => false,
            'description' => '出力部門のチーム',
        ]);

        // オンデマンド部チーム
        $ondemandTeam = Team::create([
            'name' => 'サン・ブレーン - オンデマンド部',
            'user_id' => $adminUserId, // 管理者をオーナーに設定
            'company_id' => 1,
            'department_id' => 3,
            'team_type' => 'department',
            'personal_team' => false,
            'description' => 'オンデマンド部門のチーム',
        ]);

        echo "出力部チーム作成完了 (ID: {$outputTeam->id})\n";
        echo "オンデマンド部チーム作成完了 (ID: {$ondemandTeam->id})\n";
    }
}
