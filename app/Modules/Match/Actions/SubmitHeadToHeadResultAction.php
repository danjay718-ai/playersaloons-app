<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Match\Models\HeadToHeadMatch;
use App\Modules\Match\StateMachines\HeadToHeadMatchStateMachine;
use App\Shared\Enums\HeadToHeadMatchStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class SubmitHeadToHeadResultAction
{
    public function __construct(private readonly HeadToHeadMatchStateMachine $stateMachine) {}

    public function execute(HeadToHeadMatch $match, User $submitter, int $winnerUserId, ?string $notes = null): void
    {
        DB::transaction(function () use ($match, $submitter, $winnerUserId, $notes): void {
            /** @var HeadToHeadMatch $lockedMatch */
            $lockedMatch = HeadToHeadMatch::query()
                ->where('id', $match->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($submitter->getKey(), [$lockedMatch->creator_user_id, $lockedMatch->opponent_user_id], true)) {
                throw new LogicException('Only match participants can submit results.');
            }

            if (! in_array($winnerUserId, [$lockedMatch->creator_user_id, $lockedMatch->opponent_user_id], true)) {
                throw new InvalidArgumentException('Winner must be one of the match participants.');
            }

            if ($lockedMatch->status !== HeadToHeadMatchStatus::IN_PROGRESS) {
                throw new LogicException('Match is not in progress.');
            }

            $lockedMatch->winner_user_id = $winnerUserId;
            $lockedMatch->result_submitted_by = $submitter->getKey();
            $lockedMatch->result_notes = $notes;
            $lockedMatch->result_submitted_at = now();
            $lockedMatch->confirmation_due_at = now()->addMinutes(15);
            $lockedMatch->save();

            $this->stateMachine->transition($lockedMatch, HeadToHeadMatchStatus::WAITING_FOR_CONFIRMATION);
        });
    }
}
