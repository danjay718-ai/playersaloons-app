<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Match\Models\HeadToHeadMatch;
use App\Modules\Match\StateMachines\HeadToHeadMatchStateMachine;
use App\Shared\Enums\HeadToHeadMatchStatus;
use Illuminate\Support\Facades\DB;
use LogicException;

class ConfirmHeadToHeadResultAction
{
    public function __construct(
        private readonly HeadToHeadMatchStateMachine $stateMachine,
        private readonly ResolveHeadToHeadStakeAction $resolveStake
    ) {}

    public function execute(HeadToHeadMatch $match, User $confirmer): void
    {
        DB::transaction(function () use ($match, $confirmer): void {
            /** @var HeadToHeadMatch $lockedMatch */
            $lockedMatch = HeadToHeadMatch::query()
                ->where('id', $match->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedMatch->status !== HeadToHeadMatchStatus::WAITING_FOR_CONFIRMATION) {
                throw new LogicException('Match is not waiting for confirmation.');
            }

            if ($lockedMatch->result_submitted_by === $confirmer->getKey()) {
                throw new LogicException('You cannot confirm your own result submission.');
            }

            if (! in_array($confirmer->getKey(), [$lockedMatch->creator_user_id, $lockedMatch->opponent_user_id], true)) {
                throw new LogicException('Only match participants can confirm results.');
            }

            $winner = User::query()->findOrFail($lockedMatch->winner_user_id);

            $this->resolveStake->execute($lockedMatch, $winner);
            $this->stateMachine->transition($lockedMatch, HeadToHeadMatchStatus::COMPLETED);
        });
    }
}
