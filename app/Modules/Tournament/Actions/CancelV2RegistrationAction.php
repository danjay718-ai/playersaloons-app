<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Events\TournamentSeatReleased;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentCancellationRequest;
use App\Modules\Tournament\Models\TournamentParticipant;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Modules\Tournament\Services\V2CancellationPolicy;
use App\Modules\Wallet\Models\Refund;
use App\Modules\Wallet\Services\WalletService;
use App\Shared\Enums\LedgerType;
use App\Shared\Enums\PaymentStatus;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Support\DecimalMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class CancelV2RegistrationAction
{
    public function __construct(private readonly WalletService $wallets) {}

    public function execute(TournamentRegistration $registration, TournamentCancellationRequest $request): TournamentRegistration
    {
        return DB::transaction(function () use ($registration, $request): TournamentRegistration {
            $tournamentId = TournamentRegistration::query()->whereKey($registration->id)->value('tournament_id');
            $tournament = Tournament::query()->lockForUpdate()->findOrFail($tournamentId);
            $locked = TournamentRegistration::query()->with('user.wallet')->lockForUpdate()->findOrFail($registration->id);
            if (! V2CancellationPolicy::isOpen($tournament)) {
                throw new LogicException('This V2 registration can no longer be cancelled.');
            }
            $request = TournamentCancellationRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($request->status !== 'approved' || (int) $request->registration_id !== (int) $locked->id
                || (int) $request->requested_by !== (int) $locked->user_id) {
                throw new LogicException('An approved cancellation request is required.');
            }
            if ($locked->status !== RegistrationStatus::CONFIRMED) {
                throw new LogicException('This registration is not active.');
            }

            if ($tournament->status === TournamentStatus::BRACKET_GENERATED) {
                $tournament->brackets()->each(function ($bracket): void {
                    $bracket->delete();
                });
                TournamentParticipant::query()->where('tournament_id', $tournament->id)->delete();
                $tournament->forceFill([
                    'status' => TournamentStatus::REGISTRATION_OPEN,
                    'registration_locked_at' => null,
                    'financial_finalized_at' => null,
                    'finalized_joined_entries' => null,
                    'finalized_gross_pool' => null,
                    'finalized_commission_amount' => null,
                    'finalized_first_prize' => null,
                    'finalized_second_prize' => null,
                    'payout_status' => 'pending',
                ])->save();
                $tournament->registrations()->update(['locked_at' => null]);
            }

            $locked->status = RegistrationStatus::CANCELLED;
            if ($locked->payment_status === PaymentStatus::PAID) {
                if ($locked->user?->wallet === null) {
                    throw new LogicException('The refund wallet is unavailable.');
                }
                $amounts = V2CancellationPolicy::amounts($tournament, $request->requested_at);
                $platformWallet = null;
                if (DecimalMoney::toMinor($amounts['fee']) > 0) {
                    $platformWallet = User::query()->where('email', 'platform@playersaloons.com')->first()?->wallet;
                    if ($platformWallet === null) {
                        throw new LogicException('The cancellation fee wallet is unavailable.');
                    }
                }
                $key = "tournament-registration-refund:{$locked->id}:request:{$request->id}";
                $refund = Refund::query()->firstOrCreate(['idempotency_key' => $key], [
                    'uuid' => Str::uuid()->toString(),
                    'wallet_id' => $locked->user->wallet->id,
                    'tournament_id' => $tournament->id,
                    'registration_id' => $locked->id,
                    'amount' => $amounts['refund'],
                    'status' => 'completed',
                    'refund_reference_uuid' => Str::uuid()->toString(),
                    'created_at' => now(),
                ]);
                $this->wallets->credit(
                    $locked->user->wallet,
                    $amounts['refund'],
                    LedgerType::REFUND,
                    Refund::class,
                    (string) $refund->id,
                    "Approved cancellation refund (fee {$amounts['fee']}): {$tournament->name}",
                    $key,
                );
                if ($platformWallet !== null) {
                    $this->wallets->credit(
                        $platformWallet,
                        $amounts['fee'],
                        LedgerType::PLATFORM_COMMISSION,
                        Refund::class,
                        (string) $refund->id,
                        "Late cancellation fee: {$tournament->name}",
                        $key.':cancellation-fee',
                    );
                }
                $locked->payment_status = PaymentStatus::REFUNDED;
            }
            $locked->save();
            TournamentSeatReleased::dispatch((int) $tournament->id, (int) $locked->id, (int) $locked->user_id);

            return $locked->fresh() ?? $locked;
        }, 3);
    }
}
