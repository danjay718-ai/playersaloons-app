<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Wallet\Services\WalletService;
use App\Shared\Enums\LedgerType;
use App\Shared\Support\DecimalMoney;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class SponsoredPrizeFundingService
{
    public function __construct(private WalletService $wallets) {}

    public function reserve(Tournament $tournament): void
    {
        DB::transaction(function () use ($tournament): void {
            $locked = Tournament::query()->lockForUpdate()->findOrFail($tournament->id);
            if ($locked->prize_funding_mode !== 'sponsored' || $locked->funding_state === 'reserved') {
                return;
            }

            $total = DecimalMoney::toMinor((string) $locked->prize_1st) + DecimalMoney::toMinor((string) $locked->prize_2nd);
            $platform = $this->platformUser();
            if ($total > 0) {
                $this->wallets->debitAllowingLiability(
                    $platform->wallet,
                    DecimalMoney::format($total),
                    LedgerType::SPONSORED_PRIZE_RESERVE,
                    Tournament::class,
                    (string) $locked->id,
                    "Sponsored prize reserve: {$locked->name}",
                    "tournament-v2:{$locked->id}:sponsored-reserve",
                );
            }
            $locked->forceFill([
                'reserved_prize_amount' => DecimalMoney::format($total),
                'funding_state' => 'reserved',
            ])->save();
        }, 3);
    }

    public function configure(Tournament $tournament, string $entryFee, ?string $first, ?string $second): void
    {
        DB::transaction(function () use ($tournament, $entryFee, $first, $second): void {
            $locked = Tournament::query()->lockForUpdate()->findOrFail($tournament->id);
            if ($locked->registrations()->exists()) {
                throw new LogicException('Prize funding is locked after the first registration.');
            }
            $sponsored = $locked->competition_type->value === 'tournament'
                && DecimalMoney::toMinor($entryFee) === 0;

            if ($locked->prize_funding_mode === 'sponsored' && $sponsored) {
                $this->adjust($locked, (string) $first, $second);
                $locked->refresh()->forceFill(['entry_fee' => $entryFee])->save();

                return;
            }
            if ($locked->prize_funding_mode === 'sponsored') {
                $this->release($locked);
            }

            $locked->refresh()->forceFill([
                'entry_fee' => $entryFee,
                'prize_1st' => $sponsored ? $first : null,
                'prize_2nd' => $sponsored ? ($second ?? '0.00') : null,
                'prize_funding_mode' => $sponsored ? 'sponsored' : 'entry_fees',
                'funding_state' => 'none',
                'reserved_prize_amount' => '0.00',
            ])->save();
            if ($sponsored) {
                $this->reserve($locked->fresh());
            }
        }, 3);
    }

    public function adjust(Tournament $tournament, string $first, ?string $second): void
    {
        DB::transaction(function () use ($tournament, $first, $second): void {
            $locked = Tournament::query()->lockForUpdate()->findOrFail($tournament->id);
            if ($locked->registrations()->exists()) {
                throw new LogicException('Sponsored prizes are locked after the first registration.');
            }
            if ($locked->prize_funding_mode !== 'sponsored' || $locked->funding_state !== 'reserved') {
                throw new LogicException('This occurrence does not have an editable sponsored-prize reserve.');
            }

            $firstMinor = DecimalMoney::toMinor($first);
            $secondMinor = DecimalMoney::toMinor($second ?? '0.00');
            if ($firstMinor < 0 || $secondMinor < 0) {
                throw new LogicException('Sponsored prizes cannot be negative.');
            }
            $newTotal = $firstMinor + $secondMinor;
            $oldTotal = DecimalMoney::toMinor((string) $locked->reserved_prize_amount);
            $difference = $newTotal - $oldTotal;
            $platform = $this->platformUser();
            if ($difference > 0) {
                $this->wallets->debitAllowingLiability($platform->wallet, DecimalMoney::format($difference), LedgerType::SPONSORED_PRIZE_RESERVE, Tournament::class, (string) $locked->id, "Sponsored prize reserve adjustment: {$locked->name}");
            } elseif ($difference < 0) {
                $this->wallets->credit($platform->wallet, DecimalMoney::format(abs($difference)), LedgerType::SPONSORED_PRIZE_RELEASE, Tournament::class, (string) $locked->id, "Sponsored prize reserve adjustment release: {$locked->name}");
            }
            $locked->forceFill([
                'prize_1st' => DecimalMoney::format($firstMinor),
                'prize_2nd' => DecimalMoney::format($secondMinor),
                'reserved_prize_amount' => DecimalMoney::format($newTotal),
            ])->save();
        }, 3);
    }

    public function release(Tournament $tournament): void
    {
        DB::transaction(function () use ($tournament): void {
            $locked = Tournament::query()->lockForUpdate()->findOrFail($tournament->id);
            if ($locked->prize_funding_mode !== 'sponsored' || $locked->funding_state !== 'reserved') {
                return;
            }
            $amount = (string) $locked->reserved_prize_amount;
            if (DecimalMoney::toMinor($amount) > 0) {
                $this->wallets->credit($this->platformUser()->wallet, $amount, LedgerType::SPONSORED_PRIZE_RELEASE, Tournament::class, (string) $locked->id, "Sponsored prize reserve released: {$locked->name}", "tournament-v2:{$locked->id}:sponsored-release");
            }
            $locked->forceFill(['funding_state' => 'released', 'reserved_prize_amount' => '0.00'])->save();
        }, 3);
    }

    private function platformUser(): User
    {
        $platform = User::query()->with('wallet')->where('email', 'platform@playersaloons.com')->first();
        if ($platform?->wallet === null) {
            throw new LogicException('Platform sponsored-prize wallet is unavailable.');
        }

        return $platform;
    }
}
