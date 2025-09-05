<?php

namespace App\Actions\Jetstream;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Laravel\Jetstream\Contracts\CreatesTeams;
use Laravel\Jetstream\Events\AddingTeam;
use Laravel\Jetstream\Jetstream;

class CreateTeam implements CreatesTeams
{
    /**
     * Validate and create a new team for the given user.
     *
     * @param  array<string, string>  $input
     */
    public function create(User $user, array $input): Team
    {
        Gate::forUser($user)->authorize('create', Jetstream::newTeamModel());

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
        ])->validateWithBag('createTeam');

        AddingTeam::dispatch($user);

        // create team and persist optional leader_id (default to creating user)
        $team = $user->ownedTeams()->create([
            'name' => $input['name'],
            'personal_team' => false,
            'leader_id' => $input['leader_id'] ?? $user->id,
        ]);

        // ensure the creating user is attached as a member with a role (avoid duplicate key)
        // use syncWithoutDetaching to set the pivot 'role' to 'owner' by default
        if (method_exists($team, 'users')) {
            $team->users()->syncWithoutDetaching([
                $user->id => ['role' => 'owner'],
            ]);
        }

        // If an explicit leader_id was provided (e.g. admin selected another user), ensure
        // pivot roles are consistent. Preserve SuperAdmin users as owners for all teams,
        // and preserve company admins as owners for teams created within their company.
        $leaderId = $input['leader_id'] ?? $user->id;
        try {
            // collect preserved owners. By default we preserve superadmins and company admins
            // so they remain owners for relevant teams. This behavior can be disabled by
            // setting TEAMS_PRESERVE_ADMINS=false in the environment to restrict owners to
            // only the creating user and the configured leader.
            $preserveOwnerIds = [];
            if (env('TEAMS_PRESERVE_ADMINS', true)) {
                $superAdmins = \Illuminate\Support\Facades\DB::table('users')
                    ->where('user_role', 'superadmin')
                    ->pluck('id')
                    ->toArray();
                $preserveOwnerIds = array_merge($preserveOwnerIds, $superAdmins);

                if (!empty($team->company_id)) {
                    $companyAdmins = \Illuminate\Support\Facades\DB::table('users')
                        ->where('user_role', 'admin')
                        ->where('company_id', $team->company_id)
                        ->pluck('id')
                        ->toArray();
                    $preserveOwnerIds = array_merge($preserveOwnerIds, $companyAdmins);
                }
            }

            // always include creator
            $preserveOwnerIds[] = $user->id;

            if ($leaderId !== 'superadmin' && !empty($leaderId)) {
                $leaderInt = intval($leaderId);
                $preserveOwnerIds[] = $leaderInt;

                if (method_exists($team, 'users')) {
                    $team->users()->syncWithoutDetaching([
                        $leaderInt => ['role' => 'owner'],
                    ]);
                } else {
                    \Illuminate\Support\Facades\DB::table('team_user')->updateOrInsert(
                        ['team_id' => $team->id, 'user_id' => $leaderInt],
                        ['role' => 'owner', 'updated_at' => now(), 'created_at' => now()]
                    );
                }
            }

            // ensure preserved owners (superadmin, company admins, creator, leader) are owners
            $preserveOwnerIds = array_values(array_unique(array_filter($preserveOwnerIds, function ($v) {
                return !empty($v);
            })));
            foreach ($preserveOwnerIds as $pid) {
                if (method_exists($team, 'users')) {
                    $team->users()->syncWithoutDetaching([$pid => ['role' => 'owner']]);
                } else {
                    \Illuminate\Support\Facades\DB::table('team_user')->updateOrInsert(
                        ['team_id' => $team->id, 'user_id' => $pid],
                        ['role' => 'owner', 'updated_at' => now(), 'created_at' => now()]
                    );
                }
            }

            // demote any other owners who are NOT in preserve list
            if (!empty($preserveOwnerIds)) {
                \Illuminate\Support\Facades\DB::table('team_user')
                    ->where('team_id', $team->id)
                    ->where('role', 'owner')
                    ->whereNotIn('user_id', $preserveOwnerIds)
                    ->update(['role' => 'user', 'updated_at' => now()]);
            }
        } catch (\Throwable $_ex) {
            logger()->warning('CreateTeam: failed to normalize pivot roles for new team', ['team_id' => $team->id, 'leader_id' => $leaderId, 'error' => $_ex->getMessage()]);
        }

        $user->switchTeam($team);

        return $team;
    }
}
