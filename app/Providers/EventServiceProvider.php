<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Identity\Events\UserRegistered;
use App\Modules\Wallet\Listeners\CreateWalletListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * All domain event → listener mappings.
     *
     * Organized by domain per Architecture Baseline v1 Part 6.
     * All non-immediate listeners MUST implement ShouldQueue.
     *
     * @var array<class-string, list<class-string>>
     */
    protected array $listen = [
        // ── Identity ────────────────────────────────────────────────────────
        UserRegistered::class => [
            CreateWalletListener::class,
        ],
    ];

    /**
     * Register event listeners.
     */
    public function boot(): void
    {
        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }
}
