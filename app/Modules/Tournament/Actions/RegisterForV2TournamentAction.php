<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Team\Models\Team;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Modules\Tournament\Models\TournamentTeam;
use App\Modules\Tournament\Services\V2TournamentLifecycle;
use Illuminate\Support\Facades\DB;
use LogicException;

final class RegisterForV2TournamentAction
{
    public function __construct(
        private readonly RegisterForTournamentAction $register,
        private readonly V2TournamentLifecycle $lifecycle,
    ) {}

    public function execute(
        Tournament $tournament,
        User $user,
        ?Team $team = null,
        ?string $gameIdValue = null,
        string $readyMode = 'auto',
        ?TournamentTeam $tournamentTeam = null,
        ?int $platformId = null,
    ): TournamentRegistration {
        if (! config('features.tournament_v2.enabled')) {
            throw new LogicException('Tournament V2 is temporarily unavailable.');
        }
        if ((int) $tournament->workflow_version !== 2) {
            throw new LogicException('The V2 registration action only accepts V2 occurrences.');
        }
        if ($tournament->join_closes_at !== null && now()->greaterThanOrEqualTo($tournament->join_closes_at)) {
            throw new LogicException('This tournament occurrence is closed to new entries.');
        }

        return DB::transaction(function () use ($tournament, $user, $team, $gameIdValue, $readyMode, $tournamentTeam, $platformId): TournamentRegistration {
            $registration = $this->register->execute($tournament, $user, $team, $gameIdValue, $readyMode, $tournamentTeam, $platformId);
            $this->lifecycle->reconcile($tournament->fresh() ?? $tournament);

            return $registration;
        }, 3);
    }
}
