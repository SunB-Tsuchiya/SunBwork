<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 指定されたユーザーを作成
        $user = User::create([
            'name' => '土屋裕士',
            'email' => 'h-tsuchiya@suna.co.jp',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'affiliation' => '情報出版',
            'role' => '進行管理',
            'email_verified_at' => now(),
        ]);

        // ユーザーのパーソナルチームを作成
        $user->ownedTeams()->save(\App\Models\Team::forceCreate([
            'user_id' => $user->id,
            'name' => explode(' ', $user->name, 2)[0] . "'s Team",
            'personal_team' => true,
        ]));

        // User::factory(10)->withPersonalTeam()->create();

        User::factory()->withPersonalTeam()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
