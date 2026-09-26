<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Match\Events\MatchCompleted;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchDispute;
use App\Modules\Match\StateMachines\MatchStateMachine;
use App\Shared\Enums\DisputeResolution;
use App\Shared\Enums\DisputeStatus;
use App\Shared\Enums\MatchStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class OverrideMatchResultAction
{
    public function __construct(private MatchStateMachine $stateMachine) {}

    public function execute(GameMatch $match, int $winnerRegistrationId, User $actor): GameMatch
    {
        if (! $actor->can('submitResult', $match)) {
            throw new AuthorizationException('You are not allowed to override this match result.');
        }

        $shouldDispatchCompletion = true;
        $completed = DB::transaction(function () use ($match, $winnerRegistrationId, $actor, &$shouldDispatchCompletion): GameMatch {
            $locked = GameMatch::query()->lockForUpdate()->findOrFail($match->id);
            if (! in_array($winnerRegistrationId, [
                (int) $locked->player_a_registration_id,
                (int) $locked->player_b_registration_id,
            ], true)) {
                throw new LogicException('Winner must be one of the match participants.');
            }

            if (in_array($locked->status, [MatchStatus::COMPLETED, MatchStatus::FORFEITED], true)) {
                if ((int) $locked->winner_registration_id === $winnerRegistrationId) {
                    $shouldDispatchCompletion = false;

                    return $locked;
                }

                throw new LogicException('A terminal match result cannot be replaced by the quick override action.');
            }

            if ($locked->status === MatchStatus::DISPUTED) {
                $dispute = MatchDispute::query()
                    ->where('match_id', $locked->id)
                    ->whereIn('status', [DisputeStatus::OPEN, DisputeStatus::UNDER_REVIEW])
                    ->lockForUpdate()
                    ->first();
                if ($dispute !== null) {
                    $dispute->forceFill([
                        'status' => DisputeStatus::RESOLVED,
                        'resolution' => $winnerRegistrationId === (int) $locked->player_a_registration_id
                            ? DisputeResolution::PLAYER_A
                            : DisputeResolution::PLAYER_B,
                        'resolved_by' => $actor->id,
                        'resolved_at' => now(),
                    ])->save();
                }
            }

            $locked->winner_registration_id = $winnerRegistrationId;
            $locked->resolution_reason = 'admin_resolution';
            $locked->save();

            foreach ([MatchStatus::READY, MatchStatus::IN_PROGRESS, MatchStatus::WAITING_FOR_CONFIRMATION, MatchStatus::COMPLETED] as $next) {
                if ($locked->status === MatchStatus::PENDING && $next !== MatchStatus::READY) {
                    continue;
                }
                if ($locked->status === MatchStatus::DISPUTED && $next !== MatchStatus::COMPLETED) {
                    continue;
                }
                if ($locked->status === $next) {
                    continue;
                }
                if (! $this->stateMachine->can($locked->status, $next)) {
                    continue;
                }
                $this->stateMachine->transition($locked, $next);
                $locked->refresh();
            }

            if ($locked->status !== MatchStatus::COMPLETED) {
                throw new LogicException('The match could not be advanced to a completed state.');
            }

            return $locked;
        }, 3);

        if ($shouldDispatchCompletion) {
            MatchCompleted::dispatch((int) $completed->id, (int) $completed->tournament_id, $winnerRegistrationId);
        }

        return $completed;
    }
}
