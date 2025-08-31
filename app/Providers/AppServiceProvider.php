<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Inertia\Inertia::share('user', function () {
            $user = \Illuminate\Support\Facades\Auth::user();
            if (!$user) return null;
            $company = $user->company_id ? \App\Models\Company::find($user->company_id) : null;
            $department = $user->department_id ? \App\Models\Department::find($user->department_id) : null;
            $assignment = $user->assignment_id ? \App\Models\Assignment::find($user->assignment_id) : null;

            // populate current_team and available_teams safely
            $currentTeam = $user->currentTeam;
            $availableTeams = $user->teams;

            // If this is the specially provisioned superadmin account, allow login even without company/team
            if ($user->user_role === 'superadmin') {
                // if no current team exists, provide a minimal placeholder so frontend components don't break
                if (! $currentTeam) {
                    $currentTeam = (object) [
                        'id' => null,
                        'name' => 'Super Admin',
                        'team_type' => 'personal',
                        'company_name' => null,
                        'department_name' => null,
                    ];
                }
                // ensure availableTeams has expected structure without calling model methods
                if (! $availableTeams) {
                    $availableTeams = (object) ['personal' => [], 'department' => []];
                } else {
                    // if it's an Eloquent Collection or array, group by team_type
                    $personal = [];
                    $departmentTeams = [];
                    if (is_array($availableTeams) || $availableTeams instanceof \Illuminate\Support\Collection) {
                        foreach ($availableTeams as $t) {
                            $type = null;
                            if (is_object($t) && isset($t->team_type)) $type = $t->team_type;
                            elseif (is_array($t) && isset($t['team_type'])) $type = $t['team_type'];

                            if ($type === 'department') {
                                $departmentTeams[] = $t;
                            } else {
                                $personal[] = $t;
                            }
                        }
                    }
                    $availableTeams = (object) ['personal' => $personal, 'department' => $departmentTeams];
                }
            }

            $user->current_team = $currentTeam;
            $user->available_teams = $availableTeams;
            $user->company = $company;
            $user->department = $department;
            $user->assignment = $assignment;

            // unread job requests count (status 'sent' means not yet accepted/read)
            try {
                $user->unread_job_requests_count = \App\Models\JobRequest::where('to_user_id', $user->id)->where('status', 'sent')->count();
            } catch (\Throwable $e) {
                $user->unread_job_requests_count = 0;
            }
            try {
                $user->unread_messages_count = \App\Models\MessageRecipient::where('user_id', $user->id)->whereNull('read_at')->count();
            } catch (\Throwable $e) {
                $user->unread_messages_count = 0;
            }

            return $user;
        });

        // 全ページ共通でcsrf_tokenをpropsに追加
        \Inertia\Inertia::share('csrf_token', function () {
            return csrf_token();
        });
    }
}
