<?php

declare(strict_types=1);

namespace App\Modules\Match\Services;

use App\Modules\Community\Services\NotificationService;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Actions\ForfeitMatchAction;
use App\Modules\Match\Actions\StartMatchAction;
use App\Modules\Match\Models\GameMatch;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\DB;

final class MatchReadinessService
{
    public function __construct(
        private readonly StartMatchAction $startMatch,
        private readonly ForfeitMatchAction $forfeitMatch,
        private readonly NotificationService $notifications,
    ) {}

    /** Initialize the Get Ready timer and apply each registration's saved preference. */
    public function prepare(GameMatch $match): void
    {
        $match->loadMissing(['tournament', 'playerARegistration', 'playerBRegistration']);
        if ($match->status !== MatchStatus::READY || $match->ready_started_at !== null) {
            return;
        }

        $now = now();
        $readyDeadline = $now->copy()->addMinutes(max(1, (int) $match->tournament->match_ready_minutes));
        $match->forceFill([
            'ready_started_at' => $now,
            'ready_deadline_at' => $readyDeadline,
            'scheduled_at' => $readyDeadline,
            'player_a_ready_at' => $match->playerARegistration?->ready_mode === 'auto' ? $now : null,
            'player_b_ready_at' => $match->playerBRegistration?->ready_mode === 'auto' ? $now : null,
        ])->save();

        if ($this->bothReady($match) && $match->tournament->status === TournamentStatus::ONGOING) {
            $this->startMatch->execute($match);
        }
    }

    public function markReady(GameMatch $match, int $userId): void
    {
        DB::transaction(function () use ($match, $userId): void {
            $locked = GameMatch::query()->lockForUpdate()->with([
                'tournament', 'playerARegistration.rosterMembers', 'playerBRegistration.rosterMembers',
            ])->findOrFail($match->getKey());

            if ($locked->status !== MatchStatus::READY) {
                throw new \LogicException('This match is no longer waiting for players.');
            }

            if ($locked->playerARegistration?->includesUser($userId)) {
                $locked->player_a_ready_at ??= now();
            } elseif ($locked->playerBRegistration?->includesUser($userId)) {
                $locked->player_b_ready_at ??= now();
            } else {
                throw new \LogicException('You are not a player in this match.');
            }
            $locked->save();

            if ($this->bothReady($locked) && $locked->tournament->status === TournamentStatus::ONGOING) {
                $this->startMatch->execute($locked);
            }
        });
    }

    public function reportOpponentAbsent(GameMatch $match, int $userId): void
    {
        DB::transaction(function () use ($match, $userId): void {
            $locked = GameMatch::query()->lockForUpdate()->with([
                'tournament', 'playerARegistration.user.notificationPreference', 'playerARegistration.rosterMembers.user.notificationPreference',
                'playerBRegistration.user.notificationPreference', 'playerBRegistration.rosterMembers.user.notificationPreference',
            ])->findOrFail($match->getKey());

            if ($locked->status !== MatchStatus::READY || $locked->ready_deadline_at?->isFuture()) {
                throw new \LogicException('You can report an absent opponent after Get Ready Time ends.');
            }

            $reportingRegistration = $locked->playerARegistration?->includesUser($userId)
                ? $locked->playerARegistration
                : ($locked->playerBRegistration?->includesUser($userId) ? $locked->playerBRegistration : null);
            if ($reportingRegistration === null) {
                throw new \LogicException('You are not a player in this match.');
            }

            $this->markReadySide($locked, (int) $reportingRegistration->getKey());
            $this->openExtraWait($locked, (int) $reportingRegistration->getKey());
        });
    }

    /** Resolve due readiness windows. Called in chunks by the minute scheduler. */
    public function reconcile(GameMatch $match): void
    {
        DB::transaction(function () use ($match): void {
            $locked = GameMatch::query()->lockForUpdate()->with([
                'tournament', 'playerARegistration.user.notificationPreference', 'playerARegistration.rosterMembers.user.notificationPreference',
                'playerBRegistration.user.notificationPreference', 'playerBRegistration.rosterMembers.user.notificationPreference',
            ])->findOrFail($match->getKey());
            if ($locked->status !== MatchStatus::READY) {
                return;
            }

            if ($this->bothReady($locked)) {
                if ($locked->tournament->status === TournamentStatus::ONGOING) {
                    $this->startMatch->execute($locked);
                }

                return;
            }

            if ($locked->extra_wait_deadline_at === null && $locked->ready_deadline_at?->isPast()) {
                $this->openExtraWait($locked, $this->readyRegistrationId($locked));

                return;
            }

            if ($locked->extra_wait_deadline_at?->isFuture() || $locked->extra_wait_deadline_at === null) {
                return;
            }

            $readyId = $this->readyRegistrationId($locked);
            if ($readyId !== null) {
                $absentId = $readyId === $locked->player_a_registration_id
                    ? $locked->player_b_registration_id
                    : $locked->player_a_registration_id;
                if ($absentId !== null) {
                    $this->forfeitMatch->execute($locked, (int) $absentId);
                }

                return;
            }

            $locked->forceFill([
                'status' => MatchStatus::FORFEITED,
                'double_no_show_at' => now(),
                'completed_at' => now(),
            ])->save();

            User::query()->role(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'TOURNAMENT_ORGANIZER'])
                ->with('notificationPreference')
                ->select(['id', 'uuid', 'email', 'username'])
                ->chunkById(100, function ($admins) use ($locked): void {
                    foreach ($admins as $admin) {
                        $this->notifications->send($admin, 'match_double_no_show', 'Match Needs Admin Review', "Both sides missed Match #{$locked->id} in '{$locked->tournament->name}'.", "/matches/{$locked->uuid}");
                    }
                });
        });
    }

    private function openExtraWait(GameMatch $match, ?int $reportingRegistrationId): void
    {
        if ($match->extra_wait_started_at !== null) {
            return;
        }

        $now = now();
        $match->forceFill([
            'absence_reported_by_registration_id' => $reportingRegistrationId,
            'extra_wait_started_at' => $now,
            'extra_wait_deadline_at' => $now->copy()->addMinutes(max(1, (int) $match->tournament->match_extra_wait_minutes)),
        ])->save();

        $users = collect([$match->playerARegistration, $match->playerBRegistration])
            ->filter()
            ->flatMap(fn ($registration) => collect([$registration->user])->merge($registration->rosterMembers->pluck('user')))
            ->filter()
            ->unique('id');
        foreach ($users as $user) {
            $this->notifications->send($user, 'match_extra_wait', 'Extra Wait Time Started', "Open Match Room now. Extra Wait Time has started for Match #{$match->id} in '{$match->tournament->name}'.", "/matches/{$match->uuid}");
        }
    }

    private function markReadySide(GameMatch $match, int $registrationId): void
    {
        if ($registrationId === $match->player_a_registration_id) {
            $match->player_a_ready_at ??= now();
        } elseif ($registrationId === $match->player_b_registration_id) {
            $match->player_b_ready_at ??= now();
        }
        $match->save();
    }

    private function bothReady(GameMatch $match): bool
    {
        return $match->player_a_ready_at !== null && $match->player_b_ready_at !== null;
    }

    private function readyRegistrationId(GameMatch $match): ?int
    {
        if ($match->player_a_ready_at !== null && $match->player_b_ready_at === null) {
            return (int) $match->player_a_registration_id;
        }
        if ($match->player_b_ready_at !== null && $match->player_a_ready_at === null) {
            return (int) $match->player_b_registration_id;
        }

        return null;
    }
}
