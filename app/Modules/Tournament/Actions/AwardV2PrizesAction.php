<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Modules\Wallet\Models\PrizeDistribution;
use App\Modules\Wallet\Services\WalletService;
use App\Shared\Enums\LedgerType;
use App\Shared\Support\DecimalMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class AwardV2PrizesAction
{
    public function __construct(private readonly WalletService $wallets) {}

    public function execute(Tournament $tournament): void
    {
        DB::transaction(function () use ($tournament): void {
            $locked = Tournament::query()->lockForUpdate()->findOrFail($tournament->id);
            if ((int) $locked->workflow_version !== 2 || $locked->payout_status === 'paid') {
                return;
            }

            $finalRoundId = $locked->rounds()->max('round_number');
            $finalMatch = $finalRoundId === null ? null : GameMatch::query()
                ->whereHas('round', fn ($round) => $round
                    ->whereHas('bracket', fn ($bracket) => $bracket->where('tournament_id', $locked->id))
                    ->where('round_number', $finalRoundId))
                ->first();

            if ($locked->completion_reason === 'no_champion') {
                if ($finalMatch === null || $finalMatch->player_a_registration_id === null || $finalMatch->player_b_registration_id === null) {
                    throw new LogicException('Both finalists are required for a no-champion settlement.');
                }
                $this->payNoChampionSettlement($locked, $finalMatch);

                return;
            }

            if ($finalMatch?->winner_registration_id === null) {
                $finalMatch = null;
            }
            if ($finalMatch === null) {
                $locked->forceFill(['payout_status' => 'held'])->save();
                throw new LogicException('The prize pool is held until a champion is resolved.');
            }

            $winnerId = (int) $finalMatch->winner_registration_id;
            $runnerUpId = (int) ($finalMatch->player_a_registration_id === $winnerId
                ? $finalMatch->player_b_registration_id
                : $finalMatch->player_a_registration_id);

            $this->payRank($locked, 1, $winnerId, (string) ($locked->finalized_first_prize ?? '0.00'));
            if (DecimalMoney::toMinor((string) ($locked->finalized_second_prize ?? '0.00')) > 0 && $runnerUpId > 0) {
                $this->payRank($locked, 2, $runnerUpId, (string) $locked->finalized_second_prize);
            }

            $this->creditCommission($locked, (string) ($locked->finalized_commission_amount ?? '0.00'));

            $locked->forceFill(['payout_status' => 'paid'])->save();
        }, 3);
    }

    private function payNoChampionSettlement(Tournament $tournament, GameMatch $finalMatch): void
    {
        $grossMinor = DecimalMoney::toMinor((string) ($tournament->finalized_gross_pool ?? '0.00'));
        $commissionMinor = DecimalMoney::percentage($grossMinor, 1000);
        $sharedMinor = $grossMinor - $commissionMinor;
        $playerBMinor = intdiv($sharedMinor, 2);
        $playerAMinor = $playerBMinor + ($sharedMinor % 2);

        $this->payRank($tournament, 1, (int) $finalMatch->player_a_registration_id, DecimalMoney::format($playerAMinor));
        $this->payRank($tournament, 2, (int) $finalMatch->player_b_registration_id, DecimalMoney::format($playerBMinor));
        $this->creditCommission($tournament, DecimalMoney::format($commissionMinor));

        $tournament->forceFill([
            'finalized_commission_amount' => DecimalMoney::format($commissionMinor),
            'finalized_first_prize' => DecimalMoney::format($playerAMinor),
            'finalized_second_prize' => DecimalMoney::format($playerBMinor),
            'prize_pool' => DecimalMoney::format($sharedMinor),
            'payout_status' => 'paid',
        ])->save();
    }

    private function creditCommission(Tournament $tournament, string $commission): void
    {
        $platform = User::query()->where('email', 'platform@playersaloons.com')->first();
        if (DecimalMoney::toMinor($commission) > 0 && $platform?->wallet === null) {
            throw new LogicException('Platform commission wallet is unavailable; payout remains pending.');
        }
        if (DecimalMoney::toMinor($commission) > 0) {
            $this->wallets->credit(
                $platform->wallet,
                $commission,
                LedgerType::PLATFORM_COMMISSION,
                Tournament::class,
                (string) $tournament->id,
                "Tournament V2 platform commission: {$tournament->name}",
                "tournament-v2:{$tournament->id}:commission",
            );
        }
    }

    private function payRank(Tournament $tournament, int $rank, int $registrationId, string $amount): void
    {
        if (DecimalMoney::toMinor($amount) <= 0) {
            return;
        }
        $registration = TournamentRegistration::query()->with('user.wallet')->find($registrationId);
        $wallet = $registration?->user?->wallet;
        if ($wallet === null) {
            throw new LogicException("Rank {$rank} does not have an available wallet.");
        }

        $key = "tournament-v2:{$tournament->id}:rank:{$rank}";
        $distribution = PrizeDistribution::query()->firstOrCreate(
            ['idempotency_key' => $key],
            [
                'uuid' => Str::uuid()->toString(),
                'wallet_id' => $wallet->id,
                'tournament_id' => $tournament->id,
                'rank' => $rank,
                'amount' => $amount,
                'distribution_reference_uuid' => Str::uuid()->toString(),
                'status' => 'completed',
                'created_at' => now(),
            ],
        );

        $this->wallets->credit(
            $wallet,
            $amount,
            LedgerType::PRIZE,
            PrizeDistribution::class,
            (string) $distribution->id,
            "Tournament V2 Rank {$rank} prize: {$tournament->name}",
            $key,
        );
    }
}
