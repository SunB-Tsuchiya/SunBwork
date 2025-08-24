<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Company;

class CreateSuperadminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = env('SUPERADMIN_EMAIL');
        $password = env('SUPERADMIN_PASSWORD');

        if (!$email || !$password) {
            // nothing to do without env values
            return;
        }

        if (User::where('email', $email)->exists()) {
            return;
        }

        // try to find or create a superadmin company
        $company = Company::firstWhere('name', 'Superadmin Company');
        $companyId = $company ? $company->id : null;

        User::create([
            'name' => 'Super Admin',
            'email' => $email,
            'password' => Hash::make($password),
            'user_role' => 'superadmin',
            'company_id' => $companyId,
        ]);
    }
}
