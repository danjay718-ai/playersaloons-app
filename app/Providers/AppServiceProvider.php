<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
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
        Gate::define('viewPulse', function ($user = null) {
            if ($user && is_object($user) && method_exists($user, 'hasAnyRole')) {
                return (bool) call_user_func([$user, 'hasAnyRole'], ['SUPER_ADMIN', 'ADMIN']);
            }

            return false;
        });
    }
}
