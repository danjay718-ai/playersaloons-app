<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Match\Events\MatchForfeited;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\StateMachines\MatchStateMachine;
use App\Shared\Enums\MatchStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class ForfeitMatchAction
{
    public function __construct(private readonly MatchStateMachine $stateMachine) {}

    /**
     * Forfeit the match (ready/in_progress -> forfeited).
     */
    public function execute(GameMatch $match, int $forfeitedByRegistrationId): void
    {
        DB::transaction(function () use ($match, $forfeitedByRegistrationId) {
            if ($forfeitedByRegistrationId !== $match->player_a_registration_id && $forfeitedByRegistrationId !== $match->player_b_registration_id) {
                throw new InvalidArgumentException('Forfeiting player must be one of the match participants.');
            }

            $winnerRegistrationId = ($forfeitedByRegistrationId === $match->player_a_registration_id)
                ? $match->player_b_registration_id
                : $match->player_a_registration_id;

            if ($winnerRegistrationId === null) {
                throw new LogicException('Opponent player registration is missing.');
            }

            $match->winner_registration_id = $winnerRegistrationId;

            $this->stateMachine->transition($match, MatchStatus::FORFEITED);

            MatchForfeited::dispatch($match->id, $match->tournament_id, $forfeitedByRegistrationId);
        });
    }
}
