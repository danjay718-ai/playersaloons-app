<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Team\Models\Team;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Modules\Tournament\Models\TournamentTeam;
use App\Modules\Tournament\Models\TournamentTemplate;
use App\Modules\Tournament\Services\V2TournamentLifecycle;
use App\Shared\Enums\RegistrationStatus;
use Carbon\CarbonImmutable;
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

        return DB::transaction(function () use ($tournament, $user, $team, $gameIdValue, $readyMode, $tournamentTeam): TournamentRegistration {
            // Serialize registration across slots of the same parent. This is
            // a row lock on the template, so two simultaneous requests cannot
            // put one player into two daily slots of the same tournament.
            $template = TournamentTemplate::query()->lockForUpdate()->find($tournament->template_id);
            if ($template === null) {
                throw new LogicException('This tournament occurrence no longer has a valid schedule template.');
            }

            $alreadyEntered = TournamentRegistration::query()
                ->where('user_id', $user->id)
                ->where('status', RegistrationStatus::CONFIRMED->value)
                ->whereHas('tournament', function ($query) use ($template, $tournament): void {
                    $query->where('template_id', $template->id);
                    if ($template->is_recurring) {
                        $query->where('occurrence_period_key', $tournament->occurrence_period_key);

                        return;
                    }

                    // One-time templates have one key per slot. Their parent
                    // rule is still one active entry for the same local day.
                    $localStart = CarbonImmutable::instance($tournament->start_at)->setTimezone($template->timezone);
                    $query->whereBetween('start_at', [
                        $localStart->startOfDay()->utc(),
                        $localStart->endOfDay()->utc(),
                    ]);
                })
                ->exists();
            if ($alreadyEntered) {
                throw new LogicException('You already have an active entry in another slot for this tournament period.');
            }

            $registration = $this->register->execute($tournament, $user, $team, $gameIdValue, $readyMode, $tournamentTeam);
            $this->lifecycle->reconcile($tournament->fresh() ?? $tournament);

            return $registration;
        }, 3);
    }
}
