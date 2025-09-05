<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\ProjectSchedule;
use App\Models\ProjectMemo;
use App\Models\JobRequest;
use App\Models\Message;
use App\Policies\ProjectSchedulePolicy;
use App\Policies\ProjectMemoPolicy;
use App\Policies\JobRequestPolicy;
use App\Policies\MessagePolicy;
use App\Policies\ClientPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // Model => Policy mappings
        ProjectSchedule::class => ProjectSchedulePolicy::class,
        ProjectMemo::class => ProjectMemoPolicy::class,
        JobRequest::class => JobRequestPolicy::class,
        Message::class => MessagePolicy::class,
        \App\Models\Client::class => ClientPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Only a superadmin may create new admin users
        Gate::define('create-admin', function ($user) {
            return $user->user_role === 'superadmin';
        });

        // Convenience gate: promote to admin
        Gate::define('promote-to-admin', function ($user) {
            return $user->user_role === 'superadmin';
        });

        // Ensure users have a valid current_team on login to avoid null-related view errors
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, \App\Listeners\EnsureUserHasCurrentTeam::class);
    }
}
