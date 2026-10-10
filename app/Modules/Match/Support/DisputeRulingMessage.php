<?php

declare(strict_types=1);

namespace App\Modules\Match\Support;

use App\Modules\Match\Models\MatchDispute;
use App\Shared\Enums\DisputeResolution;

final class DisputeRulingMessage
{
    public static function for(MatchDispute $dispute): string
    {
        if (in_array($dispute->resolution, [DisputeResolution::REMATCH, DisputeResolution::DRAW], true)) {
            return __('An administrator ruled a rematch. Play again and submit a new result.');
        }

        $registration = $dispute->resolution === DisputeResolution::PLAYER_A
            ? $dispute->match->playerARegistration
            : $dispute->match->playerBRegistration;
        $winner = $registration?->team?->name;
        if (! $winner) {
            $participant = $registration?->user;
            $winner = $participant === null ? __('Participant') : $participant->username;
        }

        return __('An administrator resolved the dispute. Winner: :winner.', ['winner' => $winner]);
    }
}
