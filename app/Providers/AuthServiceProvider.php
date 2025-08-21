<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\\Models\\Model' => 'App\\Policies\\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Only a superadmin may create new admin users
        Gate::define('create-admin', function ($user) {
            return isset($user->is_superadmin) && $user->is_superadmin === true;
        });

        // Convenience gate: promote to admin
        Gate::define('promote-to-admin', function ($user) {
            return isset($user->is_superadmin) && $user->is_superadmin === true;
        });
    }
}
