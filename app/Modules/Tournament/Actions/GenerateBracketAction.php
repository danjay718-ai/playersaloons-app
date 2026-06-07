<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Tournament\Events\TournamentBracketGenerated;
use App\Modules\Tournament\Models\Bracket;
use App\Modules\Tournament\Models\Round;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentParticipant;
use App\Modules\Tournament\StateMachines\TournamentStateMachine;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\DB;

class GenerateBracketAction
{
    public function __construct(
        private readonly TournamentStateMachine $stateMachine,
        private readonly \App\Modules\Tournament\Services\BracketGenerationService $bracketGenerationService,
    ) {}

    /**
     * Generate a single-elimination bracket for a tournament.
     *
     * Shuffles participants, seeds them, and creates Round records.
     * Transitions tournament to BRACKET_GENERATED.
     *
     * @throws \App\Shared\Exceptions\InvalidStateTransitionException
     * @throws \LogicException
     */
    public function execute(Tournament $tournament): Bracket
    {
        return DB::transaction(function () use ($tournament): Bracket {
            // State machine validates min participants via guard
            $this->stateMachine->transition($tournament, TournamentStatus::BRACKET_GENERATED);

            /** @var \Illuminate\Database\Eloquent\Collection<int, TournamentParticipant> $participants */
            $participants = TournamentParticipant::query()
                ->where('tournament_id', $tournament->getKey())
                ->inRandomOrder()
                ->get();

            // Assign seeds
            $participants->each(function (TournamentParticipant $p, int $index): void {
                $p->seed = $index + 1;
                $p->save();
            });

            // Delegate core bracket structure and matches creation to the service
            $bracket = $this->bracketGenerationService->generate($tournament);

            TournamentBracketGenerated::dispatch(
                (int) $tournament->getKey(),
                (int) $bracket->getKey(),
                $participants->count()
            );

            return $bracket;
        });
    }
}
