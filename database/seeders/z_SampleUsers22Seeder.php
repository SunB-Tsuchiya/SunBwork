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

            // Provide default company/department names when CSV doesn't include them
            if (empty($data['company_name'])) {
                $data['company_name'] = '株式会社サン・ブレーン';
            }
            if (empty($data['department_name'])) {
                $data['department_name'] = '情報出版';
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
                // current_team_id is set later when assigning teams
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
                // Assign user into company/department team based on CSV fields (do NOT create personal teams)
                try {
                    $userRecord = DB::table('users')->where('email', $email)->first();
                    if ($userRecord) {
                        $schema = DB::getSchemaBuilder();
                        $teamId = null;
                        // If company_id / department_id missing in CSV, try to resolve by name (find or create)
                        try {
                            // resolve company by name
                            if ($schema->hasTable('companies')) {
                                $csvCompanyName = $data['company_name'] ?? null;
                                if ($csvCompanyName) {
                                    $existingCompany = DB::table('companies')->where('name', $csvCompanyName)->first();
                                    if ($existingCompany) {
                                        $companyId = $existingCompany->id;
                                    } else {
                                        try {
                                            $companyId = DB::table('companies')->insertGetId([
                                                'name' => $csvCompanyName,
                                                'created_at' => $now,
                                                'updated_at' => $now,
                                            ]);
                                        } catch (\Throwable $_exC) {
                                            logger()->warning('z_SampleUsers22Seeder: failed to create company', ['email' => $email, 'company' => $csvCompanyName, 'error' => $_exC->getMessage()]);
                                        }
                                    }
                                }
                            }

                            // resolve department by name (attached to resolved company if possible)
                            if ($schema->hasTable('departments')) {
                                $csvDeptName = $data['department_name'] ?? null;
                                if ($csvDeptName) {
                                    $deptQuery = DB::table('departments')->where('name', $csvDeptName);
                                    if (!empty($companyId)) {
                                        $deptQuery->where('company_id', $companyId);
                                    }
                                    $existingDept = $deptQuery->first();
                                    if ($existingDept) {
                                        $departmentId = $existingDept->id;
                                    } else {
                                        try {
                                            $departmentId = DB::table('departments')->insertGetId([
                                                'company_id' => $companyId,
                                                'name' => $csvDeptName,
                                                'created_at' => $now,
                                                'updated_at' => $now,
                                            ]);
                                        } catch (\Throwable $_exD) {
                                            logger()->warning('z_SampleUsers22Seeder: failed to create department', ['email' => $email, 'department' => $csvDeptName, 'error' => $_exD->getMessage()]);
                                        }
                                    }
                                }
                            }

                            // write resolved company_id/department_id back to users table if columns exist
                            $updateData = [];
                            if (!empty($companyId) && Schema::hasColumn('users', 'company_id')) {
                                $updateData['company_id'] = $companyId;
                            }
                            if (!empty($departmentId) && Schema::hasColumn('users', 'department_id')) {
                                $updateData['department_id'] = $departmentId;
                            }
                            if (!empty($updateData)) {
                                try {
                                    $updateData['updated_at'] = $now;
                                    DB::table('users')->where('id', $userRecord->id)->update($updateData);
                                } catch (\Throwable $_exU) {
                                    logger()->warning('z_SampleUsers22Seeder: failed to update user company/department', ['email' => $email, 'error' => $_exU->getMessage()]);
                                }
                            }
                        } catch (\Throwable $_exResolve) {
                            logger()->warning('z_SampleUsers22Seeder: company/department resolution failed', ['email' => $email, 'error' => $_exResolve->getMessage()]);
                        }
                        // collect all teams we should register the user to (department first, then company)
                        $assignedTeamIds = [];

                        // read company/department ids from CSV if present
                        $companyId = isset($data['company_id']) && $data['company_id'] !== '' ? intval($data['company_id']) : null;
                        $departmentId = isset($data['department_id']) && $data['department_id'] !== '' ? intval($data['department_id']) : null;

                        if ($schema->hasTable('teams')) {
                            // If CSV specified a current_team_id, prefer that if the team exists
                            $csvTeamId = isset($data['current_team_id']) && $data['current_team_id'] !== '' ? intval($data['current_team_id']) : null;
                            if ($csvTeamId) {
                                $team = DB::table('teams')->where('id', $csvTeamId)->first();
                                if ($team) {
                                    $teamId = $team->id;
                                    $assignedTeamIds[] = $teamId;
                                }
                            }

                            // Prefer department team when department_id provided (only if not set by CSV)
                            if (!$teamId && $departmentId) {
                                $team = DB::table('teams')
                                    ->where('team_type', 'department')
                                    ->where('company_id', $companyId)
                                    ->where('department_id', $departmentId)
                                    ->first();

                                if ($team) {
                                    $teamId = $team->id;
                                    $assignedTeamIds[] = $team->id;
                                } else {
                                    // create department team so user can be assigned
                                    try {
                                        $deptName = $data['department_name'] ?? null;
                                        $teamName = $deptName ?: ("Department {$departmentId} (Company {$companyId})");
                                        $deptTeamId = DB::table('teams')->insertGetId([
                                            'user_id' => $userRecord->id,
                                            'company_id' => $companyId,
                                            'department_id' => $departmentId,
                                            'name' => $teamName,
                                            'personal_team' => false,
                                            'team_type' => 'department',
                                            'created_at' => $now,
                                            'updated_at' => $now,
                                        ]);
                                        $teamId = $deptTeamId;
                                        $assignedTeamIds[] = $deptTeamId;
                                    } catch (\Throwable $_exCreateTeam) {
                                        // failed to create department team - skip creating this team
                                        // leave teamId as-is
                                    }
                                }
                            }

                            // fallback: assign to company-level team if exists (only if still not set)
                            if (!$teamId && $companyId) {
                                $team = DB::table('teams')
                                    ->where('team_type', 'company')
                                    ->where('company_id', $companyId)
                                    ->first();
                                if ($team) {
                                    $teamId = $team->id;
                                    $assignedTeamIds[] = $team->id;
                                } else {
                                    // create company team so user can be assigned
                                    try {
                                        $companyName = $data['company_name'] ?? null;
                                        $teamName = $companyName ?: ("Company {$companyId}");
                                        $companyTeamId = DB::table('teams')->insertGetId([
                                            'user_id' => $userRecord->id,
                                            'company_id' => $companyId,
                                            'department_id' => null,
                                            'name' => $teamName,
                                            'personal_team' => false,
                                            'team_type' => 'company',
                                            'created_at' => $now,
                                            'updated_at' => $now,
                                        ]);
                                        $teamId = $companyTeamId;
                                        $assignedTeamIds[] = $companyTeamId;
                                    } catch (\Throwable $_exCreateTeam) {
                                        // failed to create company team - skip creating this team
                                        // leave teamId as-is
                                    }
                                }
                            }

                            // final fallback: if still no team assigned, try to attach to any existing company-level team
                            if (!$teamId) {
                                try {
                                    $fallback = DB::table('teams')->whereNotNull('company_id')->first();
                                    if ($fallback) {
                                        $teamId = $fallback->id;
                                        $assignedTeamIds[] = $teamId;
                                    }
                                } catch (\Throwable $_exFallback) {
                                    // ignore fallback failures
                                }
                            }
                        }

                        // If CSV lacks department_id but has department_name, try to find or create a department team by name
                        if (empty($departmentId) && !empty($data['department_name']) && $schema->hasTable('teams')) {
                            try {
                                $deptName = $data['department_name'];
                                // try to find existing department team by name
                                $deptTeam = DB::table('teams')
                                    ->where('team_type', 'department')
                                    ->where('name', $deptName)
                                    ->first();
                                if ($deptTeam) {
                                    if (!in_array($deptTeam->id, $assignedTeamIds)) {
                                        $assignedTeamIds[] = $deptTeam->id;
                                    }
                                } else {
                                    // create department team (company_id if available from CSV or null)
                                    $companyForDept = $companyId ?? null;
                                    $newDeptId = DB::table('teams')->insertGetId([
                                        'user_id' => $userRecord->id,
                                        'company_id' => $companyForDept,
                                        'department_id' => null,
                                        'name' => $deptName,
                                        'personal_team' => false,
                                        'team_type' => 'department',
                                        'created_at' => $now,
                                        'updated_at' => $now,
                                    ]);
                                    if ($newDeptId) {
                                        $assignedTeamIds[] = $newDeptId;
                                        // if we haven't set preferred team yet, prefer this department
                                        $teamId = $teamId ?: $newDeptId;
                                    }
                                }
                            } catch (\Throwable $_exDept) {
                                // failed to find/create department team by name
                            }
                        }

                        // ensure we register the user into all assigned teams (department first, then company)
                        if ($schema->hasTable('team_user') && !empty($assignedTeamIds)) {
                            $pivotRole = $data['team_role'] ?? ($data['role'] ?? 'member');
                            foreach ($assignedTeamIds as $tId) {
                                try {
                                    $exists = DB::table('team_user')->where('team_id', $tId)->where('user_id', $userRecord->id)->first();
                                    if (!$exists) {
                                        DB::table('team_user')->insert([
                                            'team_id' => $tId,
                                            'user_id' => $userRecord->id,
                                            'role' => $pivotRole,
                                            'created_at' => $now,
                                            'updated_at' => $now,
                                        ]);
                                    }
                                } catch (\Throwable $_exPivot) {
                                    // failed to insert team_user pivot for this team; continue
                                }
                            }
                        }

                        // decide current_team_id: prefer department team if available, else first assigned, else null
                        $preferredTeam = null;
                        // find department id in assigned list if departmentId was provided
                        if ($departmentId) {
                            foreach ($assignedTeamIds as $t) {
                                $teamRec = DB::table('teams')->where('id', $t)->first();
                                if ($teamRec && $teamRec->team_type === 'department') {
                                    $preferredTeam = $t;
                                    break;
                                }
                            }
                        }
                        if (!$preferredTeam && !empty($assignedTeamIds)) {
                            $preferredTeam = $assignedTeamIds[0];
                        }

                        if ($preferredTeam && Schema::hasColumn('users', 'current_team_id')) {
                            try {
                                DB::table('users')->where('id', $userRecord->id)->update(['current_team_id' => $preferredTeam, 'updated_at' => $now]);
                            } catch (\Throwable $_exUpdateUser) {
                                // failed to update user's current_team_id
                            }
                        }
                    }
                } catch (\Throwable $_exTeam) {
                    // failed to assign team for user; continue with next user
                }
            } catch (\Throwable $e) {
                $this->command->error("Failed to insert/update $email: " . $e->getMessage());
            }
        }

        fclose($handle);
    }
}
