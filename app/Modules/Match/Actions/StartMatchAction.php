<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\StateMachines\MatchStateMachine;
use App\Shared\Enums\MatchStatus;
use App\Modules\Match\Events\MatchStarted;
use Illuminate\Support\Facades\DB;

class StartMatchAction
{
    public function __construct(private readonly MatchStateMachine $stateMachine) {}

    /**
     * Start the match (ready -> in_progress).
     */
    public function execute(GameMatch $match): void
    {
        DB::transaction(function () use ($match) {
            $this->stateMachine->transition($match, MatchStatus::IN_PROGRESS);
            MatchStarted::dispatch($match->id, $match->tournament_id);
        });
    }
}
