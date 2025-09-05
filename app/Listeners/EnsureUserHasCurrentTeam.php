<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use App\Models\Team;

class EnsureUserHasCurrentTeam
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;

        try {
            if (!\Illuminate\Support\Facades\Schema::hasColumn('users', 'current_team_id')) {
                return;
            }

            if (!empty($user->current_team_id)) {
                return;
            }

            // Prefer a company-level team for the user's company
            if (!empty($user->company_id)) {
                $team = Team::where('company_id', $user->company_id)->where('team_type', 'company')->first();
                if ($team) {
                    \Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->update(['current_team_id' => $team->id, 'updated_at' => now()]);
                    return;
                }
            }

            // Fallback: if user is member of any team, pick the first one
            $memberTeam = \Illuminate\Support\Facades\DB::table('team_user')
                ->where('user_id', $user->id)
                ->orderBy('team_id')
                ->first();
            if ($memberTeam) {
                \Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->update(['current_team_id' => $memberTeam->team_id, 'updated_at' => now()]);
            }
        } catch (\Throwable $e) {
            logger()->warning('EnsureUserHasCurrentTeam failed', ['user_id' => $user->id ?? null, 'error' => $e->getMessage()]);
        }
    }
}
