<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Company;
use App\Models\User;
use App\Actions\Jetstream\CreateTeam;

class TestTeamOwners extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:team-owners';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create test users/companies and teams then show team_user rows to validate owner logic';

    public function handle()
    {
        $this->info('Starting test:team-owners');

        if (! Schema::hasTable('companies')) {
            $this->error('companies table not found. Aborting.');
            return 1;
        }

        $companyColumns = Schema::getColumnListing('companies');
        $this->info('companies columns: ' . implode(', ', $companyColumns));

        // Build company attributes and provide defaults for common required fields
        $companyAttrs = ['name' => 'TinkerCo'];
        if (in_array('code', $companyColumns)) {
            $companyAttrs['code'] = 'TINK';
        }
        if (in_array('slug', $companyColumns) && ! isset($companyAttrs['slug'])) {
            $companyAttrs['slug'] = Str::slug($companyAttrs['name']);
        }

        try {
            $company = Company::firstOrCreate(['name' => $companyAttrs['name']], $companyAttrs);
        } catch (\Throwable $e) {
            $this->error('Failed creating company: ' . $e->getMessage());
            return 1;
        }

        $this->info('Using company id: ' . $company->id);

        // Create users
        $users = [];
        $users['super'] = User::updateOrCreate(['email' => 'super@local.test'], [
            'name' => 'Super',
            'password' => Hash::make('password'),
            'user_role' => 'superadmin',
            'company_id' => $company->id,
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ]);

        $users['admin'] = User::updateOrCreate(['email' => 'admin@local.test'], [
            'name' => 'Admin',
            'password' => Hash::make('password'),
            'user_role' => 'admin',
            'company_id' => $company->id,
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ]);

        $users['creator'] = User::updateOrCreate(['email' => 'creator@local.test'], [
            'name' => 'Creator',
            'password' => Hash::make('password'),
            'user_role' => 'user',
            'company_id' => $company->id,
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ]);

        $users['other'] = User::updateOrCreate(['email' => 'other@local.test'], [
            'name' => 'Other',
            'password' => Hash::make('password'),
            'user_role' => 'user',
            'company_id' => $company->id,
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ]);

        $this->info('Created/updated users: ' . implode(', ', array_map(function ($u) {
            return $u->email;
        }, $users)));

        // Create a team via CreateTeam action and print team_user rows
        $action = new CreateTeam();

        try {
            $team = $action->create($users['creator'], ['name' => 'tinker-team', 'leader_id' => $users['other']->id]);
        } catch (\Throwable $e) {
            $this->error('CreateTeam failed: ' . $e->getMessage());
            return 1;
        }

        $this->info('Team created id=' . $team->id . ' name=' . $team->name);

        $rows = DB::table('team_user')
            ->where('team_id', $team->id)
            ->join('users', 'users.id', '=', 'team_user.user_id')
            ->select('users.id as user_id', 'users.email', 'team_user.role')
            ->orderBy('users.id')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('No team_user rows found for team ' . $team->id);
        } else {
            $this->info('team_user rows:');
            foreach ($rows as $r) {
                $this->line("user_id={$r->user_id} email={$r->email} role={$r->role}");
            }
        }

        $this->info('Test complete.');
        return 0;
    }
}
