<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Match\Events\MatchStarted;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\StateMachines\MatchStateMachine;
use App\Shared\Enums\MatchStatus;
use Illuminate\Support\Facades\DB;
use LogicException;

class StartMatchAction
{
    public function __construct(private readonly MatchStateMachine $stateMachine) {}

    /**
     * Start the match (ready -> in_progress).
     */
    public function execute(GameMatch $match): void
    {
        $started = DB::transaction(function () use ($match): ?GameMatch {
            $locked = GameMatch::query()->lockForUpdate()->findOrFail($match->id);
            if ($locked->status === MatchStatus::IN_PROGRESS) {
                return null;
            }
            if ($locked->status !== MatchStatus::READY) {
                throw new LogicException('This match is no longer ready to start.');
            }
            $this->stateMachine->transition($locked, MatchStatus::IN_PROGRESS);

            return $locked;
        }, 3);

        if ($started !== null) {
            MatchStarted::dispatch((int) $started->id, (int) $started->tournament_id);
        }
    }
}
