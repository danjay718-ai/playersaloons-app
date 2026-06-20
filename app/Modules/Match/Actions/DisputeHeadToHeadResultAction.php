<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Match\Models\HeadToHeadMatch;
use App\Modules\Match\StateMachines\HeadToHeadMatchStateMachine;
use App\Shared\Enums\HeadToHeadMatchStatus;
use Illuminate\Support\Facades\DB;
use LogicException;

class DisputeHeadToHeadResultAction
{
    public function __construct(private readonly HeadToHeadMatchStateMachine $stateMachine) {}

    public function execute(HeadToHeadMatch $match, User $actor): void
    {
        DB::transaction(function () use ($match, $actor): void {
            /** @var HeadToHeadMatch $lockedMatch */
            $lockedMatch = HeadToHeadMatch::query()
                ->where('id', $match->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($actor->getKey(), [$lockedMatch->creator_user_id, $lockedMatch->opponent_user_id], true)) {
                throw new LogicException('Only match participants can dispute results.');
            }

            if ($lockedMatch->status !== HeadToHeadMatchStatus::WAITING_FOR_CONFIRMATION) {
                throw new LogicException('Only submitted results can be disputed.');
            }

            $this->stateMachine->transition($lockedMatch, HeadToHeadMatchStatus::DISPUTED);
        });
    }
}
