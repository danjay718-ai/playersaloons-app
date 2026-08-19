<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Listeners;

use App\Modules\Community\Services\NotificationService;
use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Events\TournamentCancelled;
use App\Modules\Tournament\Events\TournamentCheckinOpened;
use App\Modules\Tournament\Events\TournamentCompleted;
use App\Modules\Tournament\Events\TournamentExtraRegistrationStarted;
use App\Modules\Tournament\Events\TournamentSeatReserved;
use App\Modules\Tournament\Events\TournamentStarted;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Wallet\Events\PrizeAwarded;
use App\Modules\Wallet\Models\Wallet;
use Illuminate\Events\Dispatcher;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;

class TournamentNotificationListener
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
        $tournament = $this->tournamentWithPlayers($event->tournamentId);
        $user = User::query()->find($event->userId);

        if ($tournament !== null && $user !== null) {
            $this->notificationService->send(
                $user,
                'registration_confirmed',
                'Registration Confirmed',
                "You have successfully registered for the tournament '{$tournament->name}'.",
                "/tournaments/{$tournament->uuid}/view",
            );
        }
    }

    public function handleExtraRegistrationStarted(TournamentExtraRegistrationStarted $event): void
    {
        $tournament = $this->tournamentWithPlayers($event->tournamentId);
        if ($tournament === null) {
            return;
        }

        foreach ($this->registeredUsers($tournament) as $user) {
            $this->notificationService->send(
                $user,
                'extra_registration_started',
                'Extra Registration Time Started',
                "'{$tournament->name}' needs more players. Your entry is secured and the schedule has moved by {$event->durationMinutes} minutes.",
                "/tournaments/{$tournament->uuid}/view",
            );
        }
    }

    /**
     * Handle tournament check-in opened (check-in reminder).
     */
    public function handleTournamentCheckinOpened(TournamentCheckinOpened $event): void
    {
        $tournament = $this->tournamentWithPlayers($event->tournamentId);
        if ($tournament === null) {
            return;
        }

        // The legacy check-in state now represents automatic entry locking.
        foreach ($this->registeredUsers($tournament) as $user) {
            $this->notificationService->send(
                $user,
                'tournament_entries_locked',
                'Tournament Entry Locked',
                "Your entry for '{$tournament->name}' is secured. Match Rooms are now being prepared.",
                "/tournaments/{$tournament->uuid}/view",
            );
        }
    }

    /**
     * Handle tournament started.
     */
    public function handleTournamentStarted(TournamentStarted $event): void
    {
        $tournament = $this->tournamentWithPlayers($event->tournamentId);
        if ($tournament === null) {
            return;
        }

        // Send started notification to all participants/registered users
        foreach ($this->registeredUsers($tournament) as $user) {
            $this->notificationService->send(
                $user,
                'tournament_started',
                'Tournament Started',
                "Tournament '{$tournament->name}' has started. Open your Match Room for current instructions.",
                "/tournaments/{$tournament->uuid}/view",
            );
        }
    }

    public function handleTournamentCompleted(TournamentCompleted $event): void
    {
        $tournament = $this->tournamentWithPlayers($event->tournamentId);
        if ($tournament === null) {
            return;
        }

        foreach ($this->registeredUsers($tournament) as $user) {
            $this->notificationService->send($user, 'tournament_completed', 'Tournament Completed', "'{$tournament->name}' is complete. Your results, prizes, and XP are now being finalized.", "/tournaments/{$tournament->uuid}/view");
        }
    }

    public function handleTournamentCancelled(TournamentCancelled $event): void
    {
        $tournament = $this->tournamentWithPlayers($event->tournamentId);
        if ($tournament === null) {
            return;
        }

        foreach ($this->registeredUsers($tournament) as $user) {
            $this->notificationService->send($user, 'tournament_cancelled', 'Tournament Cancelled', "'{$tournament->name}' was cancelled. Any eligible paid entry will be refunded automatically.", "/tournaments/{$tournament->uuid}/view");
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
            TournamentExtraRegistrationStarted::class,
            [self::class, 'handleExtraRegistrationStarted']
        );

        $events->listen(
            TournamentStarted::class,
            [self::class, 'handleTournamentStarted']
        );

        $events->listen(TournamentCompleted::class, [self::class, 'handleTournamentCompleted']);
        $events->listen(TournamentCancelled::class, [self::class, 'handleTournamentCancelled']);

        $events->listen(
            PrizeAwarded::class,
            [self::class, 'handlePrizeAwarded']
        );
    }

    private function tournamentWithPlayers(int $tournamentId): ?Tournament
    {
        return Tournament::query()->with([
            'registrations.user.notificationPreference',
            'registrations.rosterMembers.user.notificationPreference',
        ])->find($tournamentId);
    }

    /** @return Collection<int, User> */
    private function registeredUsers(Tournament $tournament): Collection
    {
        return $tournament->registrations
            ->flatMap(fn ($registration) => collect([$registration->user])->merge($registration->rosterMembers->pluck('user')))
            ->filter()
            ->unique('id')
            ->values();
    }
}
