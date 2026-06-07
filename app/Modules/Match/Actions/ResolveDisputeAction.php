<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchDispute;
use App\Modules\Match\StateMachines\MatchStateMachine;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\DisputeStatus;
use App\Shared\Enums\DisputeResolution;
use App\Modules\Match\Events\MatchCompleted;
use App\Modules\Match\Events\MatchRematchCreated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class ResolveDisputeAction
{
    public function __construct(private readonly MatchStateMachine $stateMachine) {}

    /**
     * Resolve a match dispute.
     */
    public function execute(
        MatchDispute $dispute,
        int $resolvedByAdminUserId,
        DisputeResolution $resolution
    ): void {
        DB::transaction(function () use ($dispute, $resolvedByAdminUserId, $resolution) {
            if ($dispute->status === DisputeStatus::RESOLVED) {
                throw new LogicException('Dispute is already resolved.');
            }

            $match = $dispute->match;

            // Update dispute record
            $dispute->status = DisputeStatus::RESOLVED;
            $dispute->resolution = $resolution;
            $dispute->resolved_by = $resolvedByAdminUserId;
            $dispute->resolved_at = \Illuminate\Support\Carbon::now();
            $dispute->save();

            if ($resolution === DisputeResolution::PLAYER_A || $resolution === DisputeResolution::PLAYER_B) {
                $winnerRegistrationId = ($resolution === DisputeResolution::PLAYER_A)
                    ? $match->player_a_registration_id
                    : $match->player_b_registration_id;

                if ($winnerRegistrationId === null) {
                    throw new LogicException('Winner player registration is missing on the match.');
                }

                $match->winner_registration_id = $winnerRegistrationId;
                
                // Transition match to COMPLETED
                $this->stateMachine->transition($match, MatchStatus::COMPLETED);

                MatchCompleted::dispatch($match->id, $match->tournament_id, $winnerRegistrationId);
            } elseif ($resolution === DisputeResolution::REMATCH) {
                // For rematch, transition original match to COMPLETED (terminal state for this match)
                $this->stateMachine->transition($match, MatchStatus::COMPLETED);

                // Create new GameMatch copy for rematch
                $rematch = GameMatch::query()->create([
                    'uuid' => Str::uuid()->toString(),
                    'tournament_id' => $match->tournament_id,
                    'round_id' => $match->round_id,
                    'player_a_registration_id' => $match->player_a_registration_id,
                    'player_b_registration_id' => $match->player_b_registration_id,
                    'status' => MatchStatus::READY,
                    'scheduled_at' => \Illuminate\Support\Carbon::now(),
                ]);

                MatchRematchCreated::dispatch($match->id, $rematch->id);
            }
        });
    }
}
