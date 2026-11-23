<?php

namespace App\Providers;

use App\Models\Agent;
use App\Models\Listing;
use App\Policies\AgentPolicy;
use App\Policies\ListingPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Agent::class => AgentPolicy::class,
        Listing::class => ListingPolicy::class,
    ];

    public function boot(): void
    {
        // Super admin bypasses every gate/permission check.
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });
    }
}
