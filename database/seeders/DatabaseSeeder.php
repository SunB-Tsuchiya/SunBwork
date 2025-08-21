<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // ensure superadmin and its team are created before other dependent seeders
            CreateSuperadminSeeder::class,
            CreateSuperadminTeamSeeder::class,
            // core data
            CompanySeeder::class,
            DepartmentSeeder::class,
            AssignmentSeeder::class,
            // teams depend on companies/departments
            \Database\Seeders\TeamSeeder::class,
            // users depend on companies/departments/assignments/teams
            UserSeeder::class,
            AiPresetsSeeder::class,
        ]);
    }
}
