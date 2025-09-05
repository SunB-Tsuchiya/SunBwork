<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Actions\Jetstream\CreateTeam;

class TestTeamDeletePivot extends Command
{
    protected $signature = 'test:team-delete-pivot';
    protected $description = 'Create a team with members, delete it, and show team_user pivot before/after';

    public function handle()
    {
        $this->info('Creating test team with members');
        $users = User::take(3)->get();
        if ($users->isEmpty()) {
            $this->error('No users found to attach. Create users first.');
            return 1;
        }

        $creator = $users->first();
        $memberIds = $users->pluck('id')->toArray();

        $action = new CreateTeam();
        $team = $action->create($creator, ['name' => 'delete-test-team', 'leader_id' => $creator->id]);

        // attach extra members
        DB::table('team_user')->insertOrIgnore(array_map(function ($uid) use ($team) {
            return ['team_id' => $team->id, 'user_id' => $uid, 'role' => 'user', 'created_at' => now(), 'updated_at' => now()];
        }, $memberIds));

        $this->info('team_user rows before delete:');
        $rows = DB::table('team_user')->where('team_id', $team->id)->get();
        foreach ($rows as $r) {
            $this->line(json_encode($r));
        }

        $this->info('Deleting team id=' . $team->id);
        $team->delete();

        $after = DB::table('team_user')->where('team_id', $team->id)->get();
        $this->info('team_user rows after delete: ' . $after->count());

        return 0;
    }
}
