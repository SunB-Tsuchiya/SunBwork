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

            CompanySeeder::class,
            DepartmentSeeder::class,
            AssignmentSeeder::class,
            UserSeeder::class,
            AiPresetsSeeder::class,
        ]);
    }
}
