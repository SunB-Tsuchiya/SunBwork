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
            // ensure superadmin company, superadmin and its team are created before other dependent seeders
            CreateSuperadminCompanySeeder::class,
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
            // この下はサンプル用のファイルです。必要ないときは消します。
            z_SampleAdminUserSeeder::class,
            z_SampleUsers22Seeder::class,
            z_SampleDiariesSeeder::class,
            z_ClientSeeder::class,
        ]);
    }
}
