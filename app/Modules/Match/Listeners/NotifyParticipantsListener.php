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
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyParticipantsListener implements ShouldQueue
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
        $match = GameMatch::query()->find($matchId);
        if ($match === null) {
            return;
        }

        $tournament = $match->tournament;
        $playerAUser = $match->playerARegistration?->user;
        $playerBUser = $match->playerBRegistration?->user;

        if ($event instanceof MatchCreated) {
            // Match Ready notification
            if ($playerAUser !== null) {
                $opponentName = $playerBUser ? $playerBUser->username : 'Opponent';
                $this->notificationService->send($playerAUser, 'match_ready', 'Match Ready', "Your match against {$opponentName} in tournament '{$tournament->name}' is now ready.");
            }
            if ($playerBUser !== null) {
                $opponentName = $playerAUser ? $playerAUser->username : 'Opponent';
                $this->notificationService->send($playerBUser, 'match_ready', 'Match Ready', "Your match against {$opponentName} in tournament '{$tournament->name}' is now ready.");
            }
        } elseif ($event instanceof MatchRematchCreated) {
            // Rematch created (dispute resolved)
            if ($playerAUser !== null) {
                $this->notificationService->send($playerAUser, 'match_rematch', 'Rematch Scheduled', "A dispute on your match in tournament '{$tournament->name}' was resolved with a rematch. A new match is ready.");
            }
            if ($playerBUser !== null) {
                $this->notificationService->send($playerBUser, 'match_rematch', 'Rematch Scheduled', "A dispute on your match in tournament '{$tournament->name}' was resolved with a rematch. A new match is ready.");
            }
        } elseif ($event instanceof MatchStarted) {
            if ($playerAUser !== null) {
                $this->notificationService->send($playerAUser, 'match_started', 'Match Started', "Your match in tournament '{$tournament->name}' has started.");
            }
            if ($playerBUser !== null) {
                $this->notificationService->send($playerBUser, 'match_started', 'Match Started', "Your match in tournament '{$tournament->name}' has started.");
            }
        } elseif ($event instanceof MatchResultSubmitted) {
            $playerAUserId = $playerAUser !== null ? $playerAUser->id : 0;
            $opponentUser = ($event->submittedByUserId === $playerAUserId) ? $playerBUser : $playerAUser;
            if ($opponentUser !== null) {
                $this->notificationService->send($opponentUser, 'match_result_submitted', 'Match Result Submitted', "A match result has been submitted for your match in tournament '{$tournament->name}'. Please verify or dispute it.");
            }
        } elseif ($event instanceof MatchCompleted) {
            $winnerName = 'Participant';
            if ($match->winnerRegistration !== null && $match->winnerRegistration->user !== null) {
                $winnerName = $match->winnerRegistration->user->username;
            }

            // Check if the match had a dispute
            $wasDisputed = $match->disputes()->exists();
            $title = 'Match Completed';
            $message = $wasDisputed
                ? "The dispute for your match in tournament '{$tournament->name}' has been resolved. Winner: {$winnerName}."
                : "Your match in tournament '{$tournament->name}' has completed. Winner: {$winnerName}.";

            if ($playerAUser !== null) {
                $this->notificationService->send($playerAUser, 'match_completed', $title, $message);
            }
            if ($playerBUser !== null) {
                $this->notificationService->send($playerBUser, 'match_completed', $title, $message);
            }
        } elseif ($event instanceof MatchForfeited) {
            $winnerUser = ($event->forfeitedByRegistrationId === $match->player_a_registration_id) ? $playerBUser : $playerAUser;
            if ($winnerUser !== null) {
                $this->notificationService->send($winnerUser, 'match_forfeited', 'Opponent Forfeited', "Your opponent has forfeited the match in tournament '{$tournament->name}'. You won!");
            }
        } elseif ($event instanceof MatchDisputed) {
            if ($playerAUser !== null) {
                $this->notificationService->send($playerAUser, 'match_disputed', 'Match Disputed', "A dispute has been opened for your match in tournament '{$tournament->name}'. Please upload your evidence.");
            }
            if ($playerBUser !== null) {
                $this->notificationService->send($playerBUser, 'match_disputed', 'Match Disputed', "A dispute has been opened for your match in tournament '{$tournament->name}'. Please upload your evidence.");
            }
        }
    }
}
