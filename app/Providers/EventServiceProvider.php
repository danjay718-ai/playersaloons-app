<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Identity\Events\UserRegistered;
use App\Modules\Identity\Events\UserSuspended;
use App\Modules\Identity\Events\UserUnsuspended;
use App\Modules\Match\Events\MatchCompleted;
use App\Modules\Match\Events\MatchDisputed;
use App\Modules\Match\Events\MatchForfeited;
use App\Modules\Match\Events\MatchRematchCreated;
use App\Modules\Match\Events\MatchResultSubmitted;
use App\Modules\Match\Events\MatchStarted;
use App\Modules\Match\Listeners\AdvanceWinnerListener;
use App\Modules\Match\Listeners\BroadcastBracketUpdateListener;
use App\Modules\Match\Listeners\NotifyParticipantsListener;
use App\Modules\Tournament\Events\TournamentCancelled;
use App\Modules\Tournament\Events\TournamentCompleted;
use App\Modules\Tournament\Listeners\AwardPrizesListener;
use App\Modules\Tournament\Listeners\IssueRefundsListener;
use App\Modules\Wallet\Events\WalletCredited;
use App\Modules\Wallet\Events\WalletDebited;
use App\Modules\Wallet\Events\WithdrawalApproved;
use App\Modules\Wallet\Events\WithdrawalRejected;
use App\Modules\Wallet\Events\WithdrawalRequested;
use App\Modules\Wallet\Listeners\CreateAuditLogListener;
use App\Modules\Wallet\Listeners\CreateLedgerEntryListener;
use App\Modules\Wallet\Listeners\CreateWalletListener;
use App\Modules\Wallet\Listeners\SendDepositNotificationListener;
use App\Modules\Wallet\Listeners\SendNotificationListener;
use App\Modules\Wallet\Listeners\SuspendWalletListener;
use App\Modules\Wallet\Listeners\UnsuspendWalletListener;
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
        UserSuspended::class => [
            SuspendWalletListener::class,
        ],
        UserUnsuspended::class => [
            UnsuspendWalletListener::class,
        ],

        // ── Wallet ──────────────────────────────────────────────────────────
        WalletCredited::class => [
            CreateLedgerEntryListener::class,
            SendDepositNotificationListener::class,
            CreateAuditLogListener::class,
        ],
        WalletDebited::class => [
            CreateLedgerEntryListener::class,
            CreateAuditLogListener::class,
        ],
        WithdrawalRequested::class => [
            CreateAuditLogListener::class,
        ],
        WithdrawalApproved::class => [
            CreateLedgerEntryListener::class,
            SendNotificationListener::class,
            CreateAuditLogListener::class,
        ],
        WithdrawalRejected::class => [
            SendNotificationListener::class,
            CreateAuditLogListener::class,
        ],

        // ── Tournament ──────────────────────────────────────────────────────
        TournamentCompleted::class => [
            AwardPrizesListener::class,
        ],
        TournamentCancelled::class => [
            IssueRefundsListener::class,
        ],

        // ── Match ───────────────────────────────────────────────────────────
        MatchStarted::class => [
            NotifyParticipantsListener::class,
        ],
        MatchResultSubmitted::class => [
            NotifyParticipantsListener::class,
        ],
        MatchCompleted::class => [
            AdvanceWinnerListener::class,
            BroadcastBracketUpdateListener::class,
            NotifyParticipantsListener::class,
        ],
        MatchForfeited::class => [
            AdvanceWinnerListener::class,
            BroadcastBracketUpdateListener::class,
            NotifyParticipantsListener::class,
        ],
        MatchDisputed::class => [
            NotifyParticipantsListener::class,
        ],
        MatchRematchCreated::class => [
            BroadcastBracketUpdateListener::class,
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
