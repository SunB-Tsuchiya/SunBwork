<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class UserTeamMigrationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $infoPubTeamId = 5; // サン・ブレーン - 情報出版部のチームID

        $users = User::all();

        foreach ($users as $user) {
            // 役割を決定
            $role = 'member';
            if ($user->user_role === 'admin') {
                $role = 'admin';
            } elseif ($user->user_role === 'owner') {
                $role = 'owner';
            }

            // team_userテーブルにメンバーシップを追加（重複チェック）
            $exists = DB::table('team_user')
                ->where('team_id', $infoPubTeamId)
                ->where('user_id', $user->id)
                ->exists();

            if (!$exists) {
                DB::table('team_user')->insert([
                    'team_id' => $infoPubTeamId,
                    'user_id' => $user->id,
                    'role' => $role,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // ユーザーのcurrent_team_idを設定
            $user->current_team_id = $infoPubTeamId;
            $user->save();

            echo "ユーザー {$user->name} を情報出版部チームに移行しました\n";
        }

        echo "全ユーザーの情報出版部への移行が完了しました\n";
    }
}
