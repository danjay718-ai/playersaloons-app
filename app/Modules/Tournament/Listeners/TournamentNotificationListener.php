<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Listeners;

use App\Modules\Community\Services\NotificationService;
use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Events\TournamentCheckinOpened;
use App\Modules\Tournament\Events\TournamentSeatReserved;
use App\Modules\Tournament\Events\TournamentStarted;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Wallet\Events\PrizeAwarded;
use App\Modules\Wallet\Models\Wallet;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;
use Illuminate\Queue\InteractsWithQueue;

class TournamentNotificationListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Queue the listener on the 'notifications' queue.
     */
    public string $queue = 'notifications';

    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    /**
     * Handle tournament seat reservation (registration confirmed).
     */
    public function handleTournamentSeatReserved(TournamentSeatReserved $event): void
    {
        $tournament = Tournament::query()->find($event->tournamentId);
        $user = User::query()->find($event->userId);

        if ($tournament !== null && $user !== null) {
            $this->notificationService->send(
                $user,
                'registration_confirmed',
                'Registration Confirmed',
                "You have successfully registered for the tournament '{$tournament->name}'."
            );
        }
    }

    /**
     * Handle tournament check-in opened (check-in reminder).
     */
    public function handleTournamentCheckinOpened(TournamentCheckinOpened $event): void
    {
        $tournament = Tournament::query()->find($event->tournamentId);
        if ($tournament === null) {
            return;
        }

        // Send check-in reminder to all registered users
        foreach ($tournament->registrations as $registration) {
            $user = $registration->user;
            if ($user !== null) {
                $this->notificationService->send(
                    $user,
                    'checkin_reminder',
                    'Check-in Reminder',
                    "Check-in is now open for tournament '{$tournament->name}'. Please check in before check-in closes."
                );
            }
        }
    }

    /**
     * Handle tournament started.
     */
    public function handleTournamentStarted(TournamentStarted $event): void
    {
        $tournament = Tournament::query()->find($event->tournamentId);
        if ($tournament === null) {
            return;
        }

        // Send started notification to all participants/registered users
        foreach ($tournament->registrations as $registration) {
            $user = $registration->user;
            if ($user !== null) {
                $this->notificationService->send(
                    $user,
                    'tournament_started',
                    'Tournament Started',
                    "Tournament '{$tournament->name}' has started! Brackets are now generated."
                );
            }
        }
    }

    /**
     * Handle prize awarded.
     */
    public function handlePrizeAwarded(PrizeAwarded $event): void
    {
        $wallet = Wallet::query()->find($event->walletId);
        $tournament = Tournament::query()->find($event->tournamentId);

        if ($wallet !== null && $wallet->user !== null && $tournament !== null) {
            $this->notificationService->send(
                $wallet->user,
                'prize_awarded',
                'Prize Awarded',
                "Congratulations! You have been awarded PHP {$event->amount} for placing Rank {$event->rank} in tournament '{$tournament->name}'."
            );
        }
    }

    /**
     * Register listeners for subscriber.
     *
     * @param  Dispatcher  $events
     */
    public function subscribe(object $events): void
    {
        $events->listen(
            TournamentSeatReserved::class,
            [self::class, 'handleTournamentSeatReserved']
        );

        $events->listen(
            TournamentCheckinOpened::class,
            [self::class, 'handleTournamentCheckinOpened']
        );

        $events->listen(
            TournamentStarted::class,
            [self::class, 'handleTournamentStarted']
        );

        $events->listen(
            PrizeAwarded::class,
            [self::class, 'handlePrizeAwarded']
        );
    }
}
