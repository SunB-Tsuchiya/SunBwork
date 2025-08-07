<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

class CompanyDepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. 会社データを投入
        $sunbrain = DB::table('companies')->updateOrInsert(
            ['code' => 'sunbrain'],
            [
                'name' => 'サン・ブレーン',
                'code' => 'sunbrain',
                'description' => '株式会社サン・ブレーン',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // IDを取得
        $sunbrainCompany = DB::table('companies')->where('code', 'sunbrain')->first();
        $sunbrain = $sunbrainCompany->id;

        // 2. 部署データを投入
        $departments = [
            [
                'company_id' => $sunbrain,
                'name' => '情報出版',
                'code' => 'info_publishing',
                'description' => '情報出版部',
                'sort_order' => 1,
                'active' => true,
            ],
            [
                'company_id' => $sunbrain,
                'name' => '出力',
                'code' => 'output',
                'description' => '出力部',
                'sort_order' => 2,
                'active' => true,
            ],
            [
                'company_id' => $sunbrain,
                'name' => 'オンデマンド',
                'code' => 'ondemand',
                'description' => 'オンデマンド部',
                'sort_order' => 3,
                'active' => true,
            ]
        ];

        $departmentIds = [];
        foreach ($departments as $dept) {
            $dept['created_at'] = now();
            $dept['updated_at'] = now();

            DB::table('departments')->updateOrInsert(
                ['code' => $dept['code'], 'company_id' => $dept['company_id']],
                $dept
            );

            // IDを取得
            $department = DB::table('departments')->where('code', $dept['code'])->where('company_id', $dept['company_id'])->first();
            $departmentIds[] = $department->id;
        }

        // 3. 部署チームを作成（情報出版部から開始）
        $infoPubDeptId = $departmentIds[0]; // 情報出版部

        DB::table('teams')->updateOrInsert(
            ['name' => 'サン・ブレーン - 情報出版部', 'team_type' => 'department'],
            [
                'user_id' => 1, // 最初のユーザー（管理者）
                'name' => 'サン・ブレーン - 情報出版部',
                'personal_team' => false,
                'company_id' => $sunbrain,
                'department_id' => $infoPubDeptId,
                'team_type' => 'department',
                'description' => '情報出版部のチーム',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $infoPubTeam = DB::table('teams')->where('name', 'サン・ブレーン - 情報出版部')->where('team_type', 'department')->first();
        $infoPubTeamId = $infoPubTeam->id;

        // 4. 既存ユーザーを情報出版部チームに移行
        $existingUsers = User::all();

        foreach ($existingUsers as $user) {
            // 既存の個人チームを削除or非アクティブ化
            Team::where('user_id', $user->id)
                ->where('personal_team', true)
                ->update(['personal_team' => false, 'team_type' => 'legacy']);

            // ユーザーを情報出版部チームのメンバーとして追加
            $role = 'member';
            if ($user->user_role === 'admin') {
                $role = 'admin';
            } elseif ($user->user_role === 'owner') {
                $role = 'owner';
            }

            // team_userテーブルに挿入（重複チェック）
            $existingMembership = DB::table('team_user')
                ->where('team_id', $infoPubTeamId)
                ->where('user_id', $user->id)
                ->first();

            if (!$existingMembership) {
                DB::table('team_user')->insert([
                    'team_id' => $infoPubTeamId,
                    'user_id' => $user->id,
                    'role' => $role,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // ユーザーの現在チームを設定
            $user->current_team_id = $infoPubTeamId;
            $user->save();
        }

        $this->command->info('会社・部署データの初期化が完了しました。');
        $this->command->info("- サン・ブレーン（ID: {$sunbrain}）");
        $this->command->info("- 情報出版部チーム（ID: {$infoPubTeamId}）");
        $this->command->info("- 既存ユーザー {$existingUsers->count()} 名を情報出版部に移行");
    }
}
