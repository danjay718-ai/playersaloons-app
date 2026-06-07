<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Listeners;

use App\Modules\Identity\Models\User;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Tournament\Events\TournamentCompleted;
use App\Modules\Tournament\Models\Round;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Modules\Tournament\Services\PrizeCalculationService;
use App\Modules\Wallet\Models\PrizeDistribution;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Services\WalletService;
use App\Shared\Enums\LedgerType;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AwardPrizesListener implements ShouldQueue
{
    /**
     * The name of the queue the job should be sent to.
     *
     * @var string|null
     */
    public $queue = 'tournament';

    public function __construct(
        private readonly PrizeCalculationService $prizeCalculationService,
        private readonly WalletService $walletService,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(TournamentCompleted $event): void
    {
        /** @var Tournament|null $tournament */
        $tournament = Tournament::query()->find($event->tournamentId);

        if ($tournament === null) {
            return;
        }

        DB::transaction(function () use ($tournament): void {
            $calculations = $this->prizeCalculationService->calculate($tournament);

            // Determine players per rank
            /** @var array<int, User> $rankPlayers */
            $rankPlayers = [];
            $bracket = $tournament->brackets()->first();

            if ($bracket !== null) {
                /** @var Collection<int, Round> $rounds */
                $rounds = $bracket->rounds()->orderByDesc('round_number')->get();
                if ($rounds->isNotEmpty()) {
                    $finalRound = $rounds->first();
                    /** @var GameMatch|null $finalMatch */
                    $finalMatch = GameMatch::query()->where('round_id', $finalRound->getKey())->first();

                    if ($finalMatch !== null && $finalMatch->winner_registration_id !== null) {
                        $winnerRegId = $finalMatch->winner_registration_id;
                        $loserRegId = ($finalMatch->player_a_registration_id == $winnerRegId)
                            ? $finalMatch->player_b_registration_id
                            : $finalMatch->player_a_registration_id;

                        /** @var TournamentRegistration|null $winnerReg */
                        $winnerReg = TournamentRegistration::query()->find($winnerRegId);
                        if ($winnerReg !== null && $winnerReg->user !== null) {
                            $rankPlayers[1] = $winnerReg->user;
                        }

                        if ($loserRegId !== null) {
                            /** @var TournamentRegistration|null $loserReg */
                            $loserReg = TournamentRegistration::query()->find($loserRegId);
                            if ($loserReg !== null && $loserReg->user !== null) {
                                $rankPlayers[2] = $loserReg->user;
                            }
                        }
                    }

                    // Semifinals (Rank 3 & 4)
                    if ($rounds->count() >= 2) {
                        $semiRound = $rounds[1];
                        $semiMatches = GameMatch::query()->where('round_id', $semiRound->getKey())->get();
                        $semiLosers = [];
                        foreach ($semiMatches as $match) {
                            if ($match->winner_registration_id !== null) {
                                $loserId = ($match->player_a_registration_id == $match->winner_registration_id)
                                    ? $match->player_b_registration_id
                                    : $match->player_a_registration_id;
                                if ($loserId !== null) {
                                    $semiLosers[] = $loserId;
                                }
                            }
                        }

                        if (isset($semiLosers[0])) {
                            /** @var TournamentRegistration|null $reg3 */
                            $reg3 = TournamentRegistration::query()->find($semiLosers[0]);
                            if ($reg3 !== null && $reg3->user !== null) {
                                $rankPlayers[3] = $reg3->user;
                            }
                        }
                        if (isset($semiLosers[1])) {
                            /** @var TournamentRegistration|null $reg4 */
                            $reg4 = TournamentRegistration::query()->find($semiLosers[1]);
                            if ($reg4 !== null && $reg4->user !== null) {
                                $rankPlayers[4] = $reg4->user;
                            }
                        }
                    }
                }
            }

            // Award prizes per rank
            foreach ($calculations['distributions'] as $rank => $amount) {
                if ($amount <= 0.0) {
                    continue;
                }

                /** @var User|null $player */
                $player = $rankPlayers[$rank] ?? null;
                if ($player === null) {
                    continue;
                }

                /** @var Wallet|null $wallet */
                $wallet = $player->wallet;
                if ($wallet === null) {
                    continue;
                }

                $distributionRefUuid = Str::uuid()->toString();

                // Create PrizeDistribution record
                $distribution = PrizeDistribution::query()->create([
                    'uuid' => Str::uuid()->toString(),
                    'wallet_id' => $wallet->getKey(),
                    'tournament_id' => $tournament->getKey(),
                    'rank' => $rank,
                    'amount' => $amount,
                    'distribution_reference_uuid' => $distributionRefUuid,
                    'status' => 'completed',
                    'created_at' => now(),
                ]);

                // Credit the player's wallet
                $this->walletService->credit(
                    $wallet,
                    $amount,
                    LedgerType::PRIZE,
                    PrizeDistribution::class,
                    (string) $distribution->getKey(),
                    "Tournament prize for Rank {$rank} in '{$tournament->name}'"
                );
            }

            // Credit platform wallet with rake and rounding remainder
            $platformCredit = round($calculations['rake_amount'] + $calculations['rounding_remainder'], 2);
            if ($platformCredit > 0.0) {
                /** @var User|null $systemUser */
                $systemUser = User::query()->where('email', 'platform@playersaloons.com')->first();
                if ($systemUser !== null && $systemUser->wallet !== null) {
                    $this->walletService->credit(
                        $systemUser->wallet,
                        $platformCredit,
                        LedgerType::ADJUSTMENT,
                        Tournament::class,
                        (string) $tournament->getKey(),
                        "Rake and rounding remainder for tournament: {$tournament->name}"
                    );
                }
            }
        });
    }
}
