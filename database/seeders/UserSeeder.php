<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Team;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // 既存ユーザーの削除
        User::truncate();

        // Admin ユーザー
        $admin = User::create([
            'name' => '管理者 太郎',
            'email' => 'admin@suna.co.jp',
            'password' => Hash::make('password123'),
            'affiliation' => '情報出版',
            'role' => 'システム管理',
            'user_role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // Admin用のチーム作成
        $adminTeam = Team::create([
            'user_id' => $admin->id,
            'name' => $admin->name . "'s Team",
            'personal_team' => true,
        ]);

        $admin->current_team_id = $adminTeam->id;
        $admin->save();

        // Owner ユーザー
        $owner = User::create([
            'name' => 'オーナー 花子',
            'email' => 'owner@suna.co.jp',
            'password' => Hash::make('password123'),
            'affiliation' => '情報出版',
            'role' => 'コンテンツ管理',
            'user_role' => 'owner',
            'email_verified_at' => now(),
        ]);

        // Owner用のチーム作成
        $ownerTeam = Team::create([
            'user_id' => $owner->id,
            'name' => $owner->name . "'s Team",
            'personal_team' => true,
        ]);

        $owner->current_team_id = $ownerTeam->id;
        $owner->save();

        // 一般ユーザー（既存）
        $user = User::create([
            'name' => '土屋裕士',
            'email' => 'h-tsuchiya@suna.co.jp',
            'password' => Hash::make('password123'),
            'affiliation' => '情報出版',
            'role' => '進行管理',
            'user_role' => 'user',
            'email_verified_at' => now(),
        ]);

        // 一般ユーザー用のチーム作成
        $userTeam = Team::create([
            'user_id' => $user->id,
            'name' => $user->name . "'s Team",
            'personal_team' => true,
        ]);

        $user->current_team_id = $userTeam->id;
        $user->save();
    }
}
