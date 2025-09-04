<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CreateSuperadminTeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = env('SUPERADMIN_EMAIL');
        if (! $email) {
            return;
        }

        $super = DB::table('users')->where('email', $email)->first();
        if (! $super) {
            // superadmin user not present; CreateSuperadminSeeder should run first or separately
            return;
        }

        // if a superadmin team already exists, do nothing
        $exists = DB::table('teams')->where('name', 'Superadmin Team')->first();
        if ($exists) {
            // ensure user's current_team_id points to it
            DB::table('users')->where('id', $super->id)->update(['current_team_id' => $exists->id]);
            return;
        }

        $teamId = DB::table('teams')->insertGetId([
            'user_id' => $super->id,
            'name' => 'Superadmin Team',
            'personal_team' => false,
            'company_id' => null,
            'department_id' => null,
            'team_type' => 'admin',
            'description' => '管理者専用チーム（superadmin）',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // set as current_team for superadmin
        DB::table('users')->where('id', $super->id)->update(['current_team_id' => $teamId]);

        // attach membership in team_user pivot if it exists in this app
        if (DB::getSchemaBuilder()->hasTable('team_user')) {
            DB::table('team_user')->insert([
                'team_id' => $teamId,
                'user_id' => $super->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
