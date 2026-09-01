<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Tournament\Events\TournamentBracketGenerated;
use App\Modules\Tournament\Models\Bracket;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentParticipant;
use App\Modules\Tournament\Services\BracketGenerationService;
use App\Modules\Tournament\StateMachines\TournamentStateMachine;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Facades\DB;

class GenerateBracketAction
{
    public function __construct(
        private readonly TournamentStateMachine $stateMachine,
        private readonly BracketGenerationService $bracketGenerationService,
    ) {}

    /**
     * Generate a single-elimination bracket for a tournament.
     *
     * Shuffles participants, seeds them, and creates Round records.
     * Transitions tournament to BRACKET_GENERATED.
     *
     * @throws InvalidStateTransitionException
     * @throws \LogicException
     */
    public function execute(Tournament $tournament): Bracket
    {
        $bracket = DB::transaction(function () use ($tournament): Bracket {
            $locked = Tournament::query()->lockForUpdate()->findOrFail($tournament->id);
            $existing = $locked->brackets()->first();
            if ($existing !== null) {
                return $existing;
            }

            // State machine validates min participants via guard
            $this->stateMachine->transition($locked, TournamentStatus::BRACKET_GENERATED);

            /** @var Collection<int, TournamentParticipant> $participants */
            $participants = TournamentParticipant::query()
                ->where('tournament_id', $locked->getKey())
                ->inRandomOrder()
                ->get();

            // Assign seeds
            $participants->each(function (TournamentParticipant $p, int $index): void {
                $p->seed = $index + 1;
                $p->save();
            });

            // Delegate core bracket structure and matches creation to the service
            $bracket = $this->bracketGenerationService->generate($locked);

            TournamentBracketGenerated::dispatch(
                (int) $locked->getKey(),
                (int) $bracket->getKey(),
                $participants->count()
            );

            return $bracket;
        });

        $tournament->refresh();

        return $bracket;
    }
}
