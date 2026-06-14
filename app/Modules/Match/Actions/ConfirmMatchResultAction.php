<?php

namespace App\Modules\Match\Actions;

use App\Modules\Match\Models\GameMatch;
use App\Shared\Enums\MatchStatus;
use Illuminate\Support\Facades\Auth;
use LogicException;

class ConfirmMatchResultAction
{
    public function execute(GameMatch $match, int $userId): void
    {
        // 1. Validate authorization
        if (($match->playerARegistration?->user_id !== $userId) && ($match->playerBRegistration?->user_id !== $userId)) {
            throw new LogicException('You are not authorized to confirm this match result.');
        }

        // 2. Validate status
        if ($match->status !== MatchStatus::WAITING_FOR_CONFIRMATION) {
            throw new LogicException('Match is not awaiting confirmation.');
        }

        // 3. Logic to confirm - Assuming the submission logic stored the result 
        // in a result submission model/record, here we just flip status.
        
        $match->status = MatchStatus::COMPLETED;
        $match->save();
        
        // Dispatch completion event
        // event(new \App\Modules\Match\Events\MatchCompleted($match));
    }
}
