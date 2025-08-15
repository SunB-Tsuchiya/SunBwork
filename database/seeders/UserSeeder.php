<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->insert([
            'name' => 'admin',
            'email' => 'admin@test.co.jp',
            'password' => Hash::make('password123'),
            'user_role' => 'admin',
            'company_id' => 1,
            'department_id' => 1,
            'assignment_id' => 1,
            'current_team_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
