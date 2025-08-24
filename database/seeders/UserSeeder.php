<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\Company;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // company は code をキーに検索して id を取得する（id は環境により変わるため固定せず検索）
        $company = Company::where('code', 'SUNBRAIN')->first();
        $companyId = $company ? $company->id : null;

        DB::table('users')->insert([
            'name' => 'admin',
            'email' => 'admin@test.co.jp',
            'password' => Hash::make('password123'),
            'user_role' => 'admin',
            'company_id' => $companyId,
            'department_id' => 1,
            'assignment_id' => 1,
            'current_team_id' => $companyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
