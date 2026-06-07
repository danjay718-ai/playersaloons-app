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

        // Register modular policies
        Gate::policy(\App\Modules\Tournament\Models\Tournament::class, \App\Modules\Tournament\Policies\TournamentPolicy::class);
        Gate::policy(\App\Modules\Match\Models\GameMatch::class, \App\Modules\Match\Policies\MatchPolicy::class);
        Gate::policy(\App\Modules\Wallet\Models\Wallet::class, \App\Modules\Wallet\Policies\WalletPolicy::class);
        Gate::policy(\App\Modules\Wallet\Models\Withdrawal::class, \App\Modules\Wallet\Policies\WithdrawalPolicy::class);
        Gate::policy(\App\Modules\Identity\Models\KycSubmission::class, \App\Modules\Identity\Policies\KycPolicy::class);
        Gate::policy(\App\Modules\Team\Models\Team::class, \App\Modules\Team\Policies\TeamPolicy::class);
        Gate::policy(\App\Modules\Identity\Models\User::class, \App\Modules\Identity\Policies\UserPolicy::class);
        Gate::policy(\App\Modules\Match\Models\MatchDispute::class, \App\Modules\Match\Policies\DisputePolicy::class);
    }
}
