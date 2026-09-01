<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentParticipant;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Modules\Wallet\Models\Refund;
use App\Modules\Wallet\Services\WalletService;
use App\Shared\Enums\LedgerType;
use App\Shared\Enums\PaymentStatus;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class CancelV2RegistrationAction
{
    public function __construct(private readonly WalletService $wallets) {}

    public function execute(TournamentRegistration $registration): TournamentRegistration
    {
        return DB::transaction(function () use ($registration): TournamentRegistration {
            $tournamentId = TournamentRegistration::query()->whereKey($registration->id)->value('tournament_id');
            $tournament = Tournament::query()->lockForUpdate()->findOrFail($tournamentId);
            $locked = TournamentRegistration::query()->with('user.wallet')->lockForUpdate()->findOrFail($registration->id);
            if ((int) $tournament->workflow_version !== 2 || $tournament->start_at?->isPast()) {
                throw new LogicException('This V2 registration can no longer be cancelled.');
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
            if ($locked->payment_status === PaymentStatus::PAID && $locked->user?->wallet !== null) {
                $cycle = $locked->registered_at?->getTimestamp() ?? now()->getTimestamp();
                $key = "tournament-registration-refund:{$locked->id}:{$cycle}";
                $refund = Refund::query()->firstOrCreate(['idempotency_key' => $key], [
                    'uuid' => Str::uuid()->toString(),
                    'wallet_id' => $locked->user->wallet->id,
                    'tournament_id' => $tournament->id,
                    'registration_id' => $locked->id,
                    'amount' => (string) $tournament->entry_fee,
                    'status' => 'completed',
                    'refund_reference_uuid' => Str::uuid()->toString(),
                    'created_at' => now(),
                ]);
                $this->wallets->credit(
                    $locked->user->wallet,
                    (string) $tournament->entry_fee,
                    LedgerType::REFUND,
                    Refund::class,
                    (string) $refund->id,
                    "Approved cancellation refund: {$tournament->name}",
                    $key,
                );
                $locked->payment_status = PaymentStatus::REFUNDED;
            }
            $locked->save();

            return $locked->fresh() ?? $locked;
        }, 3);
    }
}
