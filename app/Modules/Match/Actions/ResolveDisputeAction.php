<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Match\Events\MatchCompleted;
use App\Modules\Match\Events\MatchRematchCreated;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchDispute;
use App\Modules\Match\StateMachines\MatchStateMachine;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\DisputeResolution;
use App\Shared\Enums\DisputeStatus;
use App\Shared\Enums\MatchStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class ResolveDisputeAction
{
    public function __construct(private readonly MatchStateMachine $stateMachine) {}

    /**
     * Resolve a match dispute.
     *
     * @throws AuthorizationException
     */
    public function execute(
        MatchDispute $dispute,
        User $actor,
        DisputeResolution $resolution
    ): void {
        if (! $actor->hasAnyRole(['ADMIN', 'SUPER_ADMIN'])) {
            throw new AuthorizationException('Only admins may resolve disputes.');
        }

        DB::transaction(function () use ($dispute, $actor, $resolution) {
            if ($dispute->status === DisputeStatus::RESOLVED) {
                throw new LogicException('Dispute is already resolved.');
            }

            $match = $dispute->match;

            // Update dispute record
            $dispute->status = DisputeStatus::RESOLVED;
            $dispute->resolution = $resolution;
            $dispute->resolved_by = $actor->getKey();
            $dispute->resolved_at = Carbon::now();
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
                    'scheduled_at' => Carbon::now(),
                ]);

                MatchRematchCreated::dispatch($match->id, $rematch->id);
            }
        });
    }
}
