<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Events\TournamentSeatReleased;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Services\WalletService;
use App\Shared\Enums\LedgerType;
use App\Shared\Enums\PaymentStatus;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\DB;

class CancelRegistrationAction
{
    public function __construct(private readonly WalletService $walletService) {}

    /**
     * Cancel a tournament registration and refund entry fee if applicable.
     *
     * Only allowed while registration is still OPEN (before REGISTRATION_CLOSED).
     *
     * @throws \LogicException
     */
    public function execute(TournamentRegistration $registration, User $actor): TournamentRegistration
    {
        $tournament = $registration->tournament;

        if (! in_array($tournament->status, [
            TournamentStatus::REGISTRATION_OPEN,
            TournamentStatus::PUBLISHED,
        ], true)) {
            throw new \LogicException('Registration can only be cancelled while registration is open.');
        }

        if ($registration->status === RegistrationStatus::CANCELLED) {
            throw new \LogicException('Registration is already cancelled.');
        }

        return DB::transaction(function () use ($registration, $actor, $tournament): TournamentRegistration {
            $registration->status = RegistrationStatus::CANCELLED;
            $registration->save();

            // Refund entry fee if it was paid
            if ($registration->payment_status === PaymentStatus::PAID) {
                /** @var Wallet|null $wallet */
                $wallet = $actor->wallet;
                if ($wallet !== null) {
                    $registration->payment_status = PaymentStatus::REFUNDED;
                    $registration->save();

                    $this->walletService->credit(
                        $wallet,
                        (float) ($tournament->entry_fee ?? '0.00'),
                        LedgerType::REFUND,
                        TournamentRegistration::class,
                        (string) $registration->getKey(),
                        "Entry fee refund for tournament: {$tournament->name}"
                    );
                }
            }

            TournamentSeatReleased::dispatch((int) $tournament->getKey(), (int) $registration->getKey(), (int) $actor->getKey());

            return $registration->fresh() ?? $registration;
        });
    }
}
