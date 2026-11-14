<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        Gate::define('admin-only', static fn (User $user): bool => $user->isAdmin() && $user->is_active);
        Gate::define('export-contacts', static fn (User $user): bool => $user->isAdmin() && $user->is_active);
        Gate::define('manage-integrations', static fn (User $user): bool => $user->isAdmin() && $user->is_active);
        Gate::define('manage-users', static fn (User $user): bool => $user->isAdmin() && $user->is_active);
        Gate::define('purge-contacts', static fn (User $user): bool => $user->isAdmin() && $user->is_active);

        RateLimiter::for('captacao', static function (Request $request): Limit {
            return Limit::perMinute(15)->by($request->ip());
        });
    }
}
