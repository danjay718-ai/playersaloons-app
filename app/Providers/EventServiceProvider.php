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
        \App\Modules\Identity\Events\UserSuspended::class => [
            \App\Modules\Wallet\Listeners\SuspendWalletListener::class,
        ],
        \App\Modules\Identity\Events\UserUnsuspended::class => [
            \App\Modules\Wallet\Listeners\UnsuspendWalletListener::class,
        ],

        // ── Wallet ──────────────────────────────────────────────────────────
        \App\Modules\Wallet\Events\WalletCredited::class => [
            \App\Modules\Wallet\Listeners\CreateLedgerEntryListener::class,
            \App\Modules\Wallet\Listeners\SendDepositNotificationListener::class,
            \App\Modules\Wallet\Listeners\CreateAuditLogListener::class,
        ],
        \App\Modules\Wallet\Events\WalletDebited::class => [
            \App\Modules\Wallet\Listeners\CreateLedgerEntryListener::class,
            \App\Modules\Wallet\Listeners\CreateAuditLogListener::class,
        ],
        \App\Modules\Wallet\Events\WithdrawalRequested::class => [
            \App\Modules\Wallet\Listeners\CreateAuditLogListener::class,
        ],
        \App\Modules\Wallet\Events\WithdrawalApproved::class => [
            \App\Modules\Wallet\Listeners\CreateLedgerEntryListener::class,
            \App\Modules\Wallet\Listeners\SendNotificationListener::class,
            \App\Modules\Wallet\Listeners\CreateAuditLogListener::class,
        ],
        \App\Modules\Wallet\Events\WithdrawalRejected::class => [
            \App\Modules\Wallet\Listeners\SendNotificationListener::class,
            \App\Modules\Wallet\Listeners\CreateAuditLogListener::class,
        ],

        // ── Tournament ──────────────────────────────────────────────────────
        \App\Modules\Tournament\Events\TournamentCompleted::class => [
            \App\Modules\Tournament\Listeners\AwardPrizesListener::class,
        ],
        \App\Modules\Tournament\Events\TournamentCancelled::class => [
            \App\Modules\Tournament\Listeners\IssueRefundsListener::class,
        ],

        // ── Match ───────────────────────────────────────────────────────────
        \App\Modules\Match\Events\MatchStarted::class => [
            \App\Modules\Match\Listeners\NotifyParticipantsListener::class,
        ],
        \App\Modules\Match\Events\MatchResultSubmitted::class => [
            \App\Modules\Match\Listeners\NotifyParticipantsListener::class,
        ],
        \App\Modules\Match\Events\MatchCompleted::class => [
            \App\Modules\Match\Listeners\AdvanceWinnerListener::class,
            \App\Modules\Match\Listeners\BroadcastBracketUpdateListener::class,
            \App\Modules\Match\Listeners\NotifyParticipantsListener::class,
        ],
        \App\Modules\Match\Events\MatchForfeited::class => [
            \App\Modules\Match\Listeners\AdvanceWinnerListener::class,
            \App\Modules\Match\Listeners\BroadcastBracketUpdateListener::class,
            \App\Modules\Match\Listeners\NotifyParticipantsListener::class,
        ],
        \App\Modules\Match\Events\MatchDisputed::class => [
            \App\Modules\Match\Listeners\NotifyParticipantsListener::class,
        ],
        \App\Modules\Match\Events\MatchRematchCreated::class => [
            \App\Modules\Match\Listeners\BroadcastBracketUpdateListener::class,
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
