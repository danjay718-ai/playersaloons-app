<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Identity\Events\UserKycSubmitted;
use App\Modules\Identity\Events\UserRegistered;
use App\Modules\Identity\Events\UserSuspended;
use App\Modules\Identity\Events\UserUnsuspended;
use App\Modules\Identity\Listeners\NotifyAdminsOfKycSubmissionListener;
use App\Modules\Identity\Listeners\QualifyReferralOnDepositListener;
use App\Modules\Match\Events\MatchCompleted;
use App\Modules\Match\Events\MatchCreated;
use App\Modules\Match\Events\MatchDisputed;
use App\Modules\Match\Events\MatchForfeited;
use App\Modules\Match\Events\MatchRematchCreated;
use App\Modules\Match\Events\MatchResultSubmitted;
use App\Modules\Match\Events\MatchStarted;
use App\Modules\Match\Listeners\AdvanceWinnerListener;
use App\Modules\Match\Listeners\ArmV2RoundDeadlineListener;
use App\Modules\Match\Listeners\ArmV2StalledMatchTimerListener;
use App\Modules\Match\Listeners\BroadcastBracketUpdateListener;
use App\Modules\Match\Listeners\NotifyAdminsOfDisputeListener;
use App\Modules\Match\Listeners\NotifyParticipantsListener;
use App\Modules\Match\Listeners\PrepareMatchRoomListener;
use App\Modules\Operations\Services\ErrorIncidentReporter;
use App\Modules\Tournament\Events\TournamentCancelled;
use App\Modules\Tournament\Events\TournamentCompleted;
use App\Modules\Tournament\Events\TournamentStarted;
use App\Modules\Tournament\Listeners\AutoStartMatchesListener;
use App\Modules\Tournament\Listeners\AwardPrizesListener;
use App\Modules\Tournament\Listeners\AwardTournamentExperienceListener;
use App\Modules\Tournament\Listeners\AwardV2ChampionExperienceListener;
use App\Modules\Tournament\Listeners\AwardV2EliminationExperienceListener;
use App\Modules\Tournament\Listeners\AwardV2PrizesListener;
use App\Modules\Tournament\Listeners\BroadcastTournamentLifecycleListener;
use App\Modules\Tournament\Listeners\IssueRefundsListener;
use App\Modules\Tournament\Listeners\TournamentNotificationListener;
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
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Queue\Events\JobFailed as QueueJobFailed;
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
        UserKycSubmitted::class => [
            NotifyAdminsOfKycSubmissionListener::class,
        ],
        UserSuspended::class => [
            SuspendWalletListener::class,
        ],
        UserUnsuspended::class => [
            UnsuspendWalletListener::class,
        ],

        // ── Wallet ──────────────────────────────────────────────────────────
        WalletCredited::class => [
            QualifyReferralOnDepositListener::class,
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
        TournamentStarted::class => [
            BroadcastTournamentLifecycleListener::class,
            AutoStartMatchesListener::class,
            ArmV2StalledMatchTimerListener::class,
        ],
        TournamentCompleted::class => [
            AwardPrizesListener::class,
            AwardV2PrizesListener::class,
            AwardTournamentExperienceListener::class,
            AwardV2ChampionExperienceListener::class,
            BroadcastTournamentLifecycleListener::class,
        ],
        TournamentCancelled::class => [
            IssueRefundsListener::class,
        ],

        // ── Match ───────────────────────────────────────────────────────────
        MatchCreated::class => [
            PrepareMatchRoomListener::class,
            NotifyParticipantsListener::class,
        ],
        MatchStarted::class => [
            ArmV2RoundDeadlineListener::class,
            NotifyParticipantsListener::class,
        ],
        MatchResultSubmitted::class => [
            NotifyParticipantsListener::class,
        ],
        MatchCompleted::class => [
            AdvanceWinnerListener::class,
            AwardV2EliminationExperienceListener::class,
            ArmV2StalledMatchTimerListener::class,
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
            NotifyAdminsOfDisputeListener::class,
        ],
        MatchRematchCreated::class => [
            BroadcastBracketUpdateListener::class,
            NotifyParticipantsListener::class,
        ],
    ];

    /**
     * The subscriber classes to register.
     *
     * @var array<int, class-string>
     */
    protected array $subscribe = [
        TournamentNotificationListener::class,
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

        foreach ($this->subscribe as $subscriber) {
            Event::subscribe($subscriber);
        }

        Event::listen(QueueJobFailed::class, function (QueueJobFailed $event): void {
            app(ErrorIncidentReporter::class)->capture($event->exception, [
                'connection' => $event->connectionName,
                'job' => $event->job->resolveName(),
                'queue' => $event->job->getQueue(),
            ], 'queue');
        });

        Event::listen(ScheduledTaskFailed::class, function (ScheduledTaskFailed $event): void {
            app(ErrorIncidentReporter::class)->capture($event->exception, [
                'task' => $event->task->getSummaryForDisplay(),
            ], 'scheduler');
        });
    }
}
