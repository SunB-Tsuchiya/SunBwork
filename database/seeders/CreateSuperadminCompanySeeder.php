<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;

class CreateSuperadminCompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Company::firstOrCreate([
            'name' => 'Superadmin Company',
        ], [
            'name' => 'Superadmin Company',
            'code' => 'SUPERADMIN',
            'description' => 'Auto-created company for superadmin user',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
