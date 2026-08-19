<?php

declare(strict_types=1);

namespace App\Modules\Match\Listeners;

use App\Modules\Community\Services\NotificationService;
use App\Modules\Match\Events\MatchCompleted;
use App\Modules\Match\Events\MatchCreated;
use App\Modules\Match\Events\MatchDisputed;
use App\Modules\Match\Events\MatchForfeited;
use App\Modules\Match\Events\MatchRematchCreated;
use App\Modules\Match\Events\MatchResultSubmitted;
use App\Modules\Match\Events\MatchStarted;
use App\Modules\Match\Models\GameMatch;
use Illuminate\Support\Collection;

class NotifyParticipantsListener
{
    /**
     * Create a new listener instance.
     */
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $matchId = null;

        if ($event instanceof MatchCreated) {
            $matchId = $event->matchId;
        } elseif ($event instanceof MatchRematchCreated) {
            $matchId = $event->rematchMatchId;
        } elseif (property_exists($event, 'matchId')) {
            $matchId = $event->matchId;
        }

        if ($matchId === null) {
            return;
        }

        /** @var GameMatch|null $match */
        $match = GameMatch::query()->with([
            'tournament', 'playerARegistration.user.notificationPreference', 'playerARegistration.rosterMembers.user.notificationPreference',
            'playerBRegistration.user.notificationPreference', 'playerBRegistration.rosterMembers.user.notificationPreference', 'winnerRegistration.user',
            'disputes:id,match_id',
        ])->find($matchId);
        if ($match === null) {
            return;
        }

        $tournament = $match->tournament;
        $playerAUser = $match->playerARegistration?->user;
        $playerBUser = $match->playerBRegistration?->user;
        $playerAUsers = $this->registrationUsers($match->playerARegistration);
        $playerBUsers = $this->registrationUsers($match->playerBRegistration);
        $matchUrl = "/matches/{$match->uuid}";

        if ($event instanceof MatchCreated) {
            // Match Ready notification
            foreach ($playerAUsers as $recipient) {
                $opponentName = $playerBUser ? $playerBUser->username : 'Opponent';
                $this->notificationService->send($recipient, 'match_ready', 'Match Ready', "Your match against {$opponentName} in tournament '{$tournament->name}' is now ready.", $matchUrl);
            }
            foreach ($playerBUsers as $recipient) {
                $opponentName = $playerAUser ? $playerAUser->username : 'Opponent';
                $this->notificationService->send($recipient, 'match_ready', 'Match Ready', "Your match against {$opponentName} in tournament '{$tournament->name}' is now ready.", $matchUrl);
            }
        } elseif ($event instanceof MatchRematchCreated) {
            // Rematch created (dispute resolved)
            foreach ($playerAUsers->merge($playerBUsers)->unique('id') as $recipient) {
                $this->notificationService->send($recipient, 'match_rematch', 'Rematch Scheduled', "A dispute on your match in tournament '{$tournament->name}' was resolved with a rematch. A new match is ready.", $matchUrl);
            }
        } elseif ($event instanceof MatchStarted) {
            foreach ($playerAUsers->merge($playerBUsers)->unique('id') as $recipient) {
                $this->notificationService->send($recipient, 'match_started', 'Match Started', "Your match in tournament '{$tournament->name}' has started. Open the Match Room now.", $matchUrl);
            }
        } elseif ($event instanceof MatchResultSubmitted) {
            $opponents = $match->playerARegistration?->includesUser($event->submittedByUserId) ? $playerBUsers : $playerAUsers;
            foreach ($opponents as $opponent) {
                $this->notificationService->send($opponent, 'match_result_submitted', 'Match Result Submitted', "A match result has been submitted for your match in tournament '{$tournament->name}'. Please verify or dispute it.", $matchUrl);
            }
        } elseif ($event instanceof MatchCompleted) {
            $winnerName = 'Participant';
            if ($match->winnerRegistration !== null && $match->winnerRegistration->user !== null) {
                $winnerName = $match->winnerRegistration->user->username;
            }

            // Check if the match had a dispute
            $wasDisputed = $match->disputes->isNotEmpty();
            $title = 'Match Completed';
            $message = $wasDisputed
                ? "The dispute for your match in tournament '{$tournament->name}' has been resolved. Winner: {$winnerName}."
                : "Your match in tournament '{$tournament->name}' has completed. Winner: {$winnerName}.";

            foreach ($playerAUsers->merge($playerBUsers)->unique('id') as $recipient) {
                $this->notificationService->send($recipient, 'match_completed', $title, $message, $matchUrl);
            }
        } elseif ($event instanceof MatchForfeited) {
            $winnerUsers = ($event->forfeitedByRegistrationId === $match->player_a_registration_id) ? $playerBUsers : $playerAUsers;
            foreach ($winnerUsers as $winnerUser) {
                $this->notificationService->send($winnerUser, 'match_forfeited', 'Opponent Forfeited', "Your opponent has forfeited the match in tournament '{$tournament->name}'. You won!", $matchUrl);
            }
        } elseif ($event instanceof MatchDisputed) {
            foreach ($playerAUsers->merge($playerBUsers)->unique('id') as $recipient) {
                $this->notificationService->send($recipient, 'match_disputed', 'Match Disputed', "A dispute has been opened for your match in tournament '{$tournament->name}'. Please upload your evidence.", $matchUrl);
            }
        }
    }

    private function registrationUsers(mixed $registration): Collection
    {
        if ($registration === null) {
            return collect();
        }

        return collect([$registration->user])
            ->merge($registration->rosterMembers->pluck('user'))
            ->filter()
            ->unique('id')
            ->values();
    }
}
