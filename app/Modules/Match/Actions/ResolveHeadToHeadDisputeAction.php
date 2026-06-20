<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Match\Models\HeadToHeadMatch;
use App\Modules\Match\StateMachines\HeadToHeadMatchStateMachine;
use App\Shared\Enums\HeadToHeadDisputeResolution;
use App\Shared\Enums\HeadToHeadMatchStatus;
use Illuminate\Support\Facades\DB;
use LogicException;

class ResolveHeadToHeadDisputeAction
{
    public function __construct(
        private readonly HeadToHeadMatchStateMachine $stateMachine,
        private readonly ResolveHeadToHeadStakeAction $resolveStake,
        private readonly RefundHeadToHeadStakeAction $refundStake
    ) {}

    public function execute(HeadToHeadMatch $match, User $admin, HeadToHeadDisputeResolution $resolution): void
    {
        if (! $admin->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'SUPPORT_AGENT', 'TOURNAMENT_ORGANIZER'])) {
            throw new LogicException('Only admin staff can resolve head-to-head disputes.');
        }

        DB::transaction(function () use ($match, $admin, $resolution): void {
            /** @var HeadToHeadMatch $lockedMatch */
            $lockedMatch = HeadToHeadMatch::query()
                ->where('id', $match->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedMatch->status !== HeadToHeadMatchStatus::DISPUTED) {
                throw new LogicException('Only disputed head-to-head matches can be resolved.');
            }

            $lockedMatch->dispute_resolution = $resolution;
            $lockedMatch->dispute_resolved_by = $admin->getKey();
            $lockedMatch->dispute_resolved_at = now();

            if ($resolution === HeadToHeadDisputeResolution::REFUND) {
                $creator = $lockedMatch->creator;
                $opponent = $lockedMatch->opponent;

                if (! $creator || ! $opponent) {
                    throw new LogicException('Match participants are required for refund.');
                }

                $lockedMatch->winner_user_id = null;
                $lockedMatch->save();

                $this->refundStake->execute(
                    $creator,
                    (string) $lockedMatch->stake_amount,
                    HeadToHeadMatch::class,
                    (string) $lockedMatch->getKey()
                );
                $this->refundStake->execute(
                    $opponent,
                    (string) $lockedMatch->stake_amount,
                    HeadToHeadMatch::class,
                    (string) $lockedMatch->getKey()
                );

                $this->stateMachine->transition($lockedMatch, HeadToHeadMatchStatus::CANCELLED);

                return;
            }

            $winner = $resolution === HeadToHeadDisputeResolution::PLAYER_A
                ? $lockedMatch->creator
                : $lockedMatch->opponent;

            if (! $winner) {
                throw new LogicException('Winner must be one of the match participants.');
            }

            $lockedMatch->winner_user_id = $winner->getKey();
            $lockedMatch->save();

            $this->resolveStake->execute($lockedMatch, $winner);
            $this->stateMachine->transition($lockedMatch, HeadToHeadMatchStatus::COMPLETED);
        });
    }
}
