<?php

namespace App\Providers;

use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Policies\KycPolicy;
use App\Modules\Identity\Policies\UserPolicy;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchDispute;
use App\Modules\Match\Policies\DisputePolicy;
use App\Modules\Match\Policies\MatchPolicy;
use App\Modules\Team\Models\Team;
use App\Modules\Team\Policies\TeamPolicy;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Policies\TournamentPolicy;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\Withdrawal;
use App\Modules\Wallet\Policies\WalletPolicy;
use App\Modules\Wallet\Policies\WithdrawalPolicy;
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
        Gate::policy(Tournament::class, TournamentPolicy::class);
        Gate::policy(GameMatch::class, MatchPolicy::class);
        Gate::policy(Wallet::class, WalletPolicy::class);
        Gate::policy(Withdrawal::class, WithdrawalPolicy::class);
        Gate::policy(KycSubmission::class, KycPolicy::class);
        Gate::policy(Team::class, TeamPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(MatchDispute::class, DisputePolicy::class);
    }
}
