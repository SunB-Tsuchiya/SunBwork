<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

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
            $currentTeam = null;
            $availableTeams = collect();
            try {
                // limit DB access to only what's needed and catch issues from the pivot/table schema
                $currentTeam = $user->currentTeam;
                $availableTeams = $user->teams;
            } catch (\Throwable $e) {
                // Log as a warning; this is non-fatal but noteworthy in logs
                Log::warning('Inertia share: failed to resolve user teams, falling back: ' . $e->getMessage());
                $currentTeam = null;
                $availableTeams = collect();
            }

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

            // legacy: job_requests is being migrated into Messages. Keep the legacy
            // property for a transitional period but set to 0 to avoid duplicate counts.
            $user->unread_job_requests_count = 0;
            // jobbox unread count: count job_assignment_messages where the assignee
            // has not yet read the job message. We use the nullable read_at column
            // on job_assignment_messages for this purpose. This keeps job unread
            // counts independent from regular MessageRecipient rows.
            try {
                $hasJam = Schema::hasTable('job_assignment_messages');
                $hasReadAt = Schema::hasColumn('job_assignment_messages', 'read_at');
                if ($hasJam && $hasReadAt) {
                    $user->unread_job_messages_count = \App\Models\JobAssignmentMessage::whereHas('projectJobAssignment', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    })->whereNull('read_at')->count();
                } else {
                    // Fallback: if schema doesn't have read_at, keep legacy behaviour
                    // by attempting to infer from message recipients when linkage exists.
                    $hasMessageId = Schema::hasColumn('job_assignment_messages', 'message_id');
                    if ($hasMessageId) {
                        $user->unread_job_messages_count = \App\Models\MessageRecipient::where('user_id', $user->id)
                            ->whereNull('read_at')
                            ->whereIn('message_id', function ($q) {
                                $q->select('message_id')->from('job_assignment_messages');
                            })->count();
                    } else {
                        $user->unread_job_messages_count = 0;
                    }
                }
            } catch (\Throwable $e) {
                $user->unread_job_messages_count = 0;
            }
            try {
                // authoritative unread messages count
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

        // Ensure URL generator uses configured APP_URL as root so
        // temporary signed URLs validate correctly when requests
        // arrive via different network/proxy setups (common in Docker).
        try {
            $appUrl = config('app.url');
            if (!empty($appUrl)) {
                URL::forceRootUrl($appUrl);
                // If APP_URL declares https, force scheme as well
                if (str_starts_with($appUrl, 'https://')) {
                    URL::forceScheme('https');
                }
            }
        } catch (\Throwable $__e) {
            Log::warning('AppServiceProvider: forceRootUrl failed: ' . $__e->getMessage());
        }
    }
}
