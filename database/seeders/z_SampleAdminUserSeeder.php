<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;

class z_sampleAdminUserSeeder extends Seeder
{
    public function run()
    {
        // single admin user data
        $email = 'ito@test.com';
        $plainPassword = 'password123';
        $name = '伊藤太郎';
        $roleName = '管理者';
        $roleCode = 'admin';

        $now = Carbon::now();

        // ensure users table exists
        if (!Schema::hasTable('users')) {
            if (isset($this->command) && method_exists($this->command, 'error')) {
                $this->command->error('z_sampleAdminUserSeeder: users table not found.');
            }
            return;
        }

        // prepare insert payload
        $insert = [
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($plainPassword),
            'email_verified_at' => $now,
            'remember_token' => Str::random(10),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // if users table has user_role column, set it to admin for this seeded user
        if (Schema::hasColumn('users', 'user_role')) {
            $insert['user_role'] = $roleCode;
        }

        try {
            DB::table('users')->updateOrInsert(['email' => $email], $insert);
            if (isset($this->command) && method_exists($this->command, 'info')) {
                $this->command->info("Created/updated admin user: {$email}");
            }
        } catch (\Throwable $e) {
            if (isset($this->command) && method_exists($this->command, 'error')) {
                $this->command->error('z_sampleAdminUserSeeder: failed to upsert user: ' . $e->getMessage());
            }
            return;
        }

        // fetch created user
        $user = DB::table('users')->where('email', $email)->first();
        if (!$user) {
            return;
        }

        // attempt to resolve company by name '株式会社サン・ブレーン'
        $companyId = null;
        if (Schema::hasTable('companies')) {
            try {
                $company = DB::table('companies')->where('name', '株式会社サン・ブレーン')->first();
                if ($company) {
                    $companyId = $company->id;
                } else {
                    if (isset($this->command) && method_exists($this->command, 'error')) {
                        $this->command->error("z_sampleAdminUserSeeder: company '株式会社サン・ブレーン' not found. user will be created without company_id.");
                    }
                }
            } catch (\Throwable $_ex) {
                // ignore resolution failure
            }
        }

        // attempt to resolve assignment by code or name
        $assignmentId = null;
        if (Schema::hasTable('assignments')) {
            try {
                $assign = DB::table('assignments')->where('code', $roleCode)->first();
                if (!$assign) {
                    $assign = DB::table('assignments')->where('name', $roleName)->first();
                }
                if ($assign) {
                    $assignmentId = $assign->id;
                } else {
                    if (isset($this->command) && method_exists($this->command, 'error')) {
                        $this->command->error("z_sampleAdminUserSeeder: assignment '{$roleName}' (code='{$roleCode}') not found. user will be created without assignment_id.");
                    }
                }
            } catch (\Throwable $_ex) {
                // ignore
            }
        }

        // prepare update for user (company_id, department_id=null, assignment_id)
        $update = ['updated_at' => $now];
        // ensure user_role is set to admin on update if the column exists
        if (Schema::hasColumn('users', 'user_role')) {
            $update['user_role'] = $roleCode;
        }
        if ($companyId && Schema::hasColumn('users', 'company_id')) {
            $update['company_id'] = $companyId;
        }
        // explicitly set department_id to null if the column exists
        if (Schema::hasColumn('users', 'department_id')) {
            $update['department_id'] = null;
        }
        if ($assignmentId && Schema::hasColumn('users', 'assignment_id')) {
            $update['assignment_id'] = $assignmentId;
        }

        if (!empty($update)) {
            try {
                DB::table('users')->where('id', $user->id)->update($update);
                if (isset($this->command) && method_exists($this->command, 'info')) {
                    $this->command->info("Updated user record with company/assignment info for {$email}");
                }
            } catch (\Throwable $_ex) {
                if (isset($this->command) && method_exists($this->command, 'error')) {
                    $this->command->error('z_sampleAdminUserSeeder: failed to update user company/assignment: ' . $_ex->getMessage());
                }
            }
        }

        // Attach user to the company-level team (株式会社サン・ブレーン) and set current_team_id
        if (Schema::hasTable('teams') && $companyId) {
            try {
                $companyTeam = DB::table('teams')
                    ->where('company_id', $companyId)
                    ->where('team_type', 'company')
                    ->first();

                if ($companyTeam) {
                    // ensure pivot table exists and insert if missing
                    if (Schema::hasTable('team_user')) {
                        $exists = DB::table('team_user')
                            ->where('team_id', $companyTeam->id)
                            ->where('user_id', $user->id)
                            ->first();
                        if (!$exists) {
                            try {
                                DB::table('team_user')->insert([
                                    'team_id' => $companyTeam->id,
                                    'user_id' => $user->id,
                                    'role' => $roleCode ?? 'admin',
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ]);
                            } catch (\Throwable $_exPivot) {
                                // ignore pivot insert failures
                            }
                        }
                    }

                    // set current_team_id on user if column exists
                    if (Schema::hasColumn('users', 'current_team_id')) {
                        try {
                            DB::table('users')->where('id', $user->id)->update(['current_team_id' => $companyTeam->id, 'updated_at' => $now]);
                        } catch (\Throwable $_exCur) {
                            // ignore failures
                        }
                    }
                } else {
                    if (isset($this->command) && method_exists($this->command, 'error')) {
                        $this->command->error("z_sampleAdminUserSeeder: company-level team not found for company_id={$companyId}. User not attached to any company team.");
                    }
                }
            } catch (\Throwable $_exAttach) {
                // ignore team attach failures
            }
        }
    }
}
