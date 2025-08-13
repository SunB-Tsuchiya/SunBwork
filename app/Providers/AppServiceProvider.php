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
            $user->current_team = $user->currentTeam;
            $user->available_teams = $user->teams;
            $user->company = $company;
            $user->department = $department;
            $user->assignment = $assignment;
            return $user;
        });
    }
}
