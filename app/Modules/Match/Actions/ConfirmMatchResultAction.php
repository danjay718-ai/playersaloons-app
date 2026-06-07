<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Match\Events\MatchCompleted;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\StateMachines\MatchStateMachine;
use App\Shared\Enums\MatchStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConfirmMatchResultAction
{
    public function __construct(private readonly MatchStateMachine $stateMachine) {}

    /**
     * Confirm match result (result_submitted -> completed).
     */
    public function execute(GameMatch $match, int $winnerRegistrationId): void
    {
        DB::transaction(function () use ($match, $winnerRegistrationId) {
            if ($winnerRegistrationId !== $match->player_a_registration_id && $winnerRegistrationId !== $match->player_b_registration_id) {
                throw new InvalidArgumentException('Winner must be one of the match participants.');
            }

            $match->winner_registration_id = $winnerRegistrationId;

            $this->stateMachine->transition($match, MatchStatus::COMPLETED);

            MatchCompleted::dispatch($match->id, $match->tournament_id, $winnerRegistrationId);
        });
    }
}
