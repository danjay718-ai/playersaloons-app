<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Identity\Models\PlayerProgression;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Modules\Wallet\Models\PrizeDistribution;
use App\Modules\Wallet\Models\Refund;
use App\Modules\Wallet\Models\Wallet;
use App\Shared\Support\DecimalMoney;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Local/testing-only destructive reset. This is deliberately separate from
 * lifecycle cleanup: it removes test financial effects and then rebuilds the
 * affected wallet and XP projections from the remaining immutable records.
 */
final class ResetTournamentTestingDataAction
{
    /** @return array{tournaments: int, templates: int, ledger_entries: int, experience_awards: int} */
    public function execute(): array
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Tournament test reset is available only in local or testing environments.');
        }

        return DB::transaction(function (): array {
            $tournamentIds = Tournament::query()->pluck('id');
            if ($tournamentIds->isEmpty()) {
                return ['tournaments' => 0, 'templates' => 0, 'ledger_entries' => 0, 'experience_awards' => 0];
            }

            $registrationIds = TournamentRegistration::query()->whereIn('tournament_id', $tournamentIds)->pluck('id');
            $prizeIds = PrizeDistribution::query()->whereIn('tournament_id', $tournamentIds)->pluck('id');
            $refundIds = Refund::query()->whereIn('tournament_id', $tournamentIds)->pluck('id');
            $xpAwards = DB::table('player_experience_awards')
                ->where('source_type', 'tournament')
                ->whereIn('source_id', $tournamentIds)
                ->get(['id', 'user_id']);

            $ledgerIds = DB::table('ledger_entries')->where(function ($query) use ($tournamentIds, $registrationIds, $prizeIds, $refundIds): void {
                $query->where(function ($entries) use ($tournamentIds): void {
                    $entries->where('reference_type', Tournament::class)->whereIn('reference_id', $tournamentIds);
                })->orWhere(function ($entries) use ($registrationIds, $tournamentIds): void {
                    $entries->where('reference_type', TournamentRegistration::class)
                        ->where(function ($references) use ($registrationIds, $tournamentIds): void {
                            $references->whereIn('reference_id', $registrationIds)->orWhereIn('reference_id', $tournamentIds);
                        });
                })->orWhere(function ($entries) use ($prizeIds): void {
                    $entries->where('reference_type', PrizeDistribution::class)->whereIn('reference_id', $prizeIds);
                })->orWhere(function ($entries) use ($refundIds): void {
                    $entries->where('reference_type', Refund::class)->whereIn('reference_id', $refundIds);
                });
            })->get(['id', 'wallet_id']);

            $walletIds = $ledgerIds->pluck('wallet_id')->unique()->values();
            $xpUserIds = $xpAwards->pluck('user_id')->unique()->values();
            $ledgerCount = $ledgerIds->count();
            $xpCount = $xpAwards->count();
            $tournamentCount = $tournamentIds->count();
            $templateCount = DB::table('tournament_templates')->count();

            // Direct query is intentional: ledger/audit models are immutable.
            // wallet_transactions cascade from ledger_entries; tournament FK
            // cascades remove brackets, matches, registrations, refunds,
            // prizes, disputes, streams, slots, and V2 operational records.
            if ($ledgerIds->isNotEmpty()) {
                DB::table('ledger_entries')->whereIn('id', $ledgerIds->pluck('id'))->delete();
            }
            DB::table('player_experience_awards')->whereIn('id', $xpAwards->pluck('id'))->delete();
            DB::table('tournaments')->delete();
            DB::table('tournament_templates')->delete();

            foreach ($walletIds as $walletId) {
                $this->rebuildWallet((int) $walletId);
            }
            foreach ($xpUserIds as $userId) {
                $this->rebuildProgression((int) $userId);
            }

            return ['tournaments' => $tournamentCount, 'templates' => $templateCount, 'ledger_entries' => $ledgerCount, 'experience_awards' => $xpCount];
        }, 3);
    }

    private function rebuildWallet(int $walletId): void
    {
        $balanceMinor = 0;
        foreach (DB::table('ledger_entries')->where('wallet_id', $walletId)->orderBy('created_at')->orderBy('id')->get(['id', 'amount']) as $entry) {
            $balanceMinor += DecimalMoney::toMinor((string) $entry->amount);
            DB::table('ledger_entries')->where('id', $entry->id)->update(['running_balance' => DecimalMoney::format($balanceMinor)]);
        }
        DB::table('wallets')->where('id', $walletId)->update(['cached_balance' => DecimalMoney::format($balanceMinor)]);
    }

    private function rebuildProgression(int $userId): void
    {
        $experience = (int) DB::table('player_experience_awards')->where('user_id', $userId)->sum('amount');
        $completed = DB::table('player_experience_awards')->where('user_id', $userId)->whereIn('reason', ['completion', 'participation'])->count();
        PlayerProgression::query()->updateOrCreate(['user_id' => $userId], [
            'experience_points' => $experience,
            'level' => intdiv($experience, PlayerProgression::XP_PER_LEVEL) + 1,
            'tournaments_completed' => $completed,
        ]);
    }
}
