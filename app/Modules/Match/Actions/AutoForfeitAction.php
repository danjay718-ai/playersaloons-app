<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Events\MatchCompleted;
use App\Modules\Match\StateMachines\MatchStateMachine;
use App\Shared\Enums\MatchStatus;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class AutoForfeitAction
{
    public function __construct(private readonly MatchStateMachine $stateMachine) {}

    public function execute(GameMatch $match): void
    {
        DB::transaction(function () use ($match) {
            $this->stateMachine->transition($match, MatchStatus::COMPLETED);
            
            activity()
                ->performedOn($match)
                ->event('auto_resolve_timeout')
                ->log('Opponent failed to confirm within tournament limit.');
            
            MatchCompleted::dispatch(
                (int) $match->id,
                (int) $match->tournament_id,
                (int) $match->winner_registration_id
            );
        });
    }
}
