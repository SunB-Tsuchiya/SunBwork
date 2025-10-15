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
        // Prefer environment helpers, but be resilient when config is cached
        // (env() can return null when config is cached). Try multiple sources
        // and as a last resort parse the .env file directly.
        $email = env('SUPERADMIN_EMAIL') ?: getenv('SUPERADMIN_EMAIL') ?: ($_ENV['SUPERADMIN_EMAIL'] ?? null);
        $password = env('SUPERADMIN_PASSWORD') ?: getenv('SUPERADMIN_PASSWORD') ?: ($_ENV['SUPERADMIN_PASSWORD'] ?? null);

        if (!$email || !$password) {
            // Fallback: attempt to read .env directly from project root
            try {
                $envPath = base_path('.env');
                if (file_exists($envPath) && is_readable($envPath)) {
                    $contents = file_get_contents($envPath);
                    if (!$email && preg_match('/^SUPERADMIN_EMAIL=(.*)$/m', $contents, $m)) {
                        $email = trim($m[1], " \t\r\n\"");
                    }
                    if (!$password && preg_match('/^SUPERADMIN_PASSWORD=(.*)$/m', $contents, $m2)) {
                        $password = trim($m2[1], " \t\r\n\"");
                    }
                }
            } catch (\Throwable $_ex) {
                // swallow errors here; we'll check below and return if still missing
            }
        }

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
