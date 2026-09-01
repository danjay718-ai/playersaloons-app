<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Match\Events\MatchCompleted;
use App\Modules\Match\Events\MatchRematchCreated;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchAttempt;
use App\Modules\Match\Models\MatchDispute;
use App\Modules\Match\StateMachines\MatchStateMachine;
use App\Modules\Tournament\Actions\CompleteTournamentAction;
use App\Modules\Tournament\Models\Tournament;
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
    public function __construct(
        private readonly MatchStateMachine $stateMachine,
        private readonly CompleteTournamentAction $completeTournament,
    ) {}

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
            $matchId = MatchDispute::query()->whereKey($dispute->id)->value('match_id');
            $tournamentId = GameMatch::query()->whereKey($matchId)->value('tournament_id');
            Tournament::query()->lockForUpdate()->findOrFail($tournamentId);
            $match = GameMatch::query()->lockForUpdate()->findOrFail($matchId);
            $dispute = MatchDispute::query()->lockForUpdate()->findOrFail($dispute->id);
            if ($dispute->status === DisputeStatus::RESOLVED) {
                throw new LogicException('Dispute is already resolved.');
            }

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
                if ((int) $match->tournament->workflow_version === 2) {
                    $match->resolution_reason = 'admin_resolution';
                }

                // Transition match to COMPLETED
                $this->stateMachine->transition($match, MatchStatus::COMPLETED);

                MatchCompleted::dispatch($match->id, $match->tournament_id, $winnerRegistrationId);
            } elseif ($resolution === DisputeResolution::NO_CHAMPION) {
                if ((int) $match->tournament->workflow_version !== 2
                    || $match->final_resolution_eligible_at === null
                    || $match->final_resolution_eligible_at->isFuture()) {
                    throw new LogicException('No-champion resolution is available only for an eligible unresolved V2 final.');
                }
                $maxRound = $match->tournament->rounds()->max('round_number');
                if ((int) $match->round->round_number !== (int) $maxRound) {
                    throw new LogicException('Only the final match can be completed without a champion.');
                }

                $match->forceFill([
                    'status' => MatchStatus::FORFEITED,
                    'winner_registration_id' => null,
                    'completed_at' => now(),
                    'stalled_deadline_at' => null,
                    'round_deadline_at' => null,
                    'resolution_reason' => 'no_champion',
                ])->save();
                $match->attempts()->where('attempt_number', $match->active_attempt_number)->update([
                    'status' => 'expired',
                    'resolution' => 'no_champion',
                    'resolved_at' => now(),
                    'updated_at' => now(),
                ]);
                $match->tournament->forceFill([
                    'completion_reason' => 'no_champion',
                    'payout_status' => 'pending',
                ])->save();
                $this->completeTournament->execute($match->tournament->fresh());
            } elseif (in_array($resolution, [DisputeResolution::REMATCH, DisputeResolution::DRAW], true)) {
                if ((int) $match->tournament->workflow_version === 2) {
                    $nextAttempt = $match->active_attempt_number + 1;
                    $stalledDeadline = $match->stalled_deadline_at !== null ? now()->addMinutes(30) : null;
                    $match->forceFill([
                        'status' => MatchStatus::IN_PROGRESS,
                        'active_attempt_number' => $nextAttempt,
                        'result_submitted_at' => null,
                        'resolution_reason' => 'admin_draw_rematch',
                        'stalled_deadline_at' => $stalledDeadline,
                        'final_resolution_eligible_at' => null,
                        'final_resolution_notified_at' => null,
                    ])->save();
                    if ($stalledDeadline !== null) {
                        MatchAttempt::query()->firstOrCreate(
                            ['match_id' => $match->id, 'attempt_number' => $nextAttempt],
                            ['uuid' => Str::uuid()->toString(), 'status' => 'open', 'stalled_deadline_at' => $stalledDeadline],
                        );
                    }
                    MatchRematchCreated::dispatch($match->id, $match->id);

                    return;
                }
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
