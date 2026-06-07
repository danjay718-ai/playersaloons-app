<?php

declare(strict_types=1);

namespace App\Modules\Match\Listeners;

use App\Modules\Community\Events\NotificationCreated;
use App\Modules\Community\Models\Notification;
use App\Modules\Match\Events\MatchCompleted;
use App\Modules\Match\Events\MatchDisputed;
use App\Modules\Match\Events\MatchForfeited;
use App\Modules\Match\Events\MatchResultSubmitted;
use App\Modules\Match\Events\MatchStarted;
use App\Modules\Match\Models\GameMatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;

class NotifyParticipantsListener implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $matchId = null;

        if (property_exists($event, 'matchId')) {
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

        if ($event instanceof MatchStarted) {
            if ($playerAUser !== null) {
                $this->sendNotification($playerAUser->id, 'match', 'Match Started', "Your match in tournament '{$tournament->name}' has started.");
            }
            if ($playerBUser !== null) {
                $this->sendNotification($playerBUser->id, 'match', 'Match Started', "Your match in tournament '{$tournament->name}' has started.");
            }
        } elseif ($event instanceof MatchResultSubmitted) {
            $playerAUserId = $playerAUser !== null ? $playerAUser->id : 0;
            $opponentUser = ($event->submittedByUserId === $playerAUserId) ? $playerBUser : $playerAUser;
            if ($opponentUser !== null) {
                $this->sendNotification($opponentUser->id, 'match', 'Match Result Submitted', "A match result has been submitted for your match in tournament '{$tournament->name}'. Please verify or dispute it.");
            }
        } elseif ($event instanceof MatchCompleted) {
            $winnerName = 'Participant';
            if ($match->winnerRegistration !== null && $match->winnerRegistration->user !== null) {
                $winnerName = $match->winnerRegistration->user->username;
            }
            if ($playerAUser !== null) {
                $this->sendNotification($playerAUser->id, 'match', 'Match Completed', "Your match in tournament '{$tournament->name}' has completed. Winner: {$winnerName}.");
            }
            if ($playerBUser !== null) {
                $this->sendNotification($playerBUser->id, 'match', 'Match Completed', "Your match in tournament '{$tournament->name}' has completed. Winner: {$winnerName}.");
            }
        } elseif ($event instanceof MatchForfeited) {
            $winnerUser = ($event->forfeitedByRegistrationId === $match->player_a_registration_id) ? $playerBUser : $playerAUser;
            if ($winnerUser !== null) {
                $this->sendNotification($winnerUser->id, 'match', 'Opponent Forfeited', "Your opponent has forfeited the match in tournament '{$tournament->name}'. You won!");
            }
        } elseif ($event instanceof MatchDisputed) {
            if ($playerAUser !== null) {
                $this->sendNotification($playerAUser->id, 'match', 'Match Disputed', "A dispute has been opened for your match in tournament '{$tournament->name}'. Please upload your evidence.");
            }
            if ($playerBUser !== null) {
                $this->sendNotification($playerBUser->id, 'match', 'Match Disputed', "A dispute has been opened for your match in tournament '{$tournament->name}'. Please upload your evidence.");
            }
        }
    }

    /**
     * Send a notification record and dispatch the event.
     */
    private function sendNotification(int $userId, string $type, string $title, string $message): void
    {
        $notification = Notification::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'read_at' => null,
        ]);

        NotificationCreated::dispatch(
            (int) $notification->getKey(),
            $userId,
            $type
        );
    }
}
