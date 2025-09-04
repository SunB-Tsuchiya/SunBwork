<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;

class z_SampleUsers22Seeder extends Seeder
{
    public function run()
    {
        $path = base_path('sample_users22.csv');
        if (!file_exists($path)) {
            $this->command->info("sample_users22.csv not found at $path, skipping");
            return;
        }

        if (($handle = fopen($path, 'r')) === false) {
            $this->command->error("Unable to open $path");
            return;
        }

        $header = fgetcsv($handle);
        if (!is_array($header)) {
            $this->command->error("Invalid CSV header in $path");
            fclose($handle);
            return;
        }

        $now = Carbon::now();

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);
            if (!is_array($data)) {
                continue;
            }

            $email = trim($data['email'] ?? '');
            if ($email === '') {
                continue;
            }

            $password = trim($data['password'] ?? '');
            if ($password === '') {
                $password = Str::random(12);
            }

            $insert = [
                'name' => $data['name'] ?? null,
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => $now,
                'remember_token' => Str::random(10),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Only set these columns if they exist in the users table to avoid errors
            if (Schema::hasColumn('users', 'user_role')) {
                $insert['user_role'] = $data['user_role'] ?? null;
            }
            if (Schema::hasColumn('users', 'role')) {
                $insert['role'] = $data['role'] ?? null;
            }

            try {
                DB::table('users')->updateOrInsert([
                    'email' => $email
                ], $insert);
                $this->command->info("Upserted user: $email");
            } catch (\Throwable $e) {
                $this->command->error("Failed to insert/update $email: " . $e->getMessage());
            }
        }

        fclose($handle);
    }
}
