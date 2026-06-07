<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Events\TournamentFilled;
use App\Modules\Tournament\Events\TournamentSeatReserved;
use App\Modules\Tournament\Exceptions\TournamentAlreadyRegisteredException;
use App\Modules\Tournament\Exceptions\TournamentFullException;
use App\Modules\Tournament\Exceptions\TournamentNotOpenForRegistrationException;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Modules\Wallet\Exceptions\InsufficientBalanceException;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Services\WalletService;
use App\Shared\Enums\LedgerType;
use App\Shared\Enums\PaymentStatus;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterForTournamentAction
{
    public function __construct(private readonly WalletService $walletService) {}

    /**
     * Register a user for a tournament, collecting entry fee if applicable.
     *
     * @throws TournamentNotOpenForRegistrationException
     * @throws TournamentAlreadyRegisteredException
     * @throws TournamentFullException
     * @throws InsufficientBalanceException
     */
    public function execute(Tournament $tournament, User $user): TournamentRegistration
    {
        if ($tournament->status !== TournamentStatus::REGISTRATION_OPEN) {
            throw new TournamentNotOpenForRegistrationException(
                $tournament->name,
                $tournament->status->value
            );
        }

        return DB::transaction(function () use ($tournament, $user): TournamentRegistration {
            // Lock tournament row to prevent race conditions on participant count
            /** @var Tournament|null $locked */
            $locked = Tournament::query()->where('id', $tournament->getKey())->lockForUpdate()->first();
            if ($locked === null) {
                throw new \RuntimeException('Tournament not found.');
            }

            // Check for duplicate registration
            $existing = TournamentRegistration::query()
                ->where('tournament_id', $locked->getKey())
                ->where('user_id', $user->getKey())
                ->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value])
                ->first();

            if ($existing instanceof TournamentRegistration) {
                throw new TournamentAlreadyRegisteredException((int) $user->getKey(), (int) $locked->getKey());
            }

            // Check capacity
            $activeCount = TournamentRegistration::query()
                ->where('tournament_id', $locked->getKey())
                ->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value])
                ->count();

            if ($activeCount >= ($locked->max_participants ?? 0)) {
                throw new TournamentFullException($locked->name, $locked->max_participants ?? 0);
            }

            $entryFee = (float) ($locked->entry_fee ?? '0.00');
            $isFree = $entryFee <= 0;
            $paymentStatus = $isFree ? PaymentStatus::FREE : PaymentStatus::PAID;

            // Collect entry fee via wallet if applicable
            if (! $isFree) {
                /** @var Wallet|null $wallet */
                $wallet = $user->wallet;
                if ($wallet === null) {
                    throw new \RuntimeException('User does not have a wallet.');
                }

                $this->walletService->debit(
                    $wallet,
                    $entryFee,
                    LedgerType::ENTRY_FEE,
                    TournamentRegistration::class,
                    (string) $locked->getKey(),
                    "Entry fee for tournament: {$locked->name}"
                );
            }

            $registration = TournamentRegistration::query()->create([
                'uuid' => Str::uuid()->toString(),
                'tournament_id' => $locked->getKey(),
                'user_id' => $user->getKey(),
                'status' => RegistrationStatus::CONFIRMED,
                'payment_status' => $paymentStatus,
                'registered_at' => now(),
            ]);

            TournamentSeatReserved::dispatch((int) $locked->getKey(), (int) $registration->getKey(), (int) $user->getKey());

            // Check if tournament is now full
            $newCount = $activeCount + 1;
            if ($newCount >= ($locked->max_participants ?? 0)) {
                TournamentFilled::dispatch((int) $locked->getKey(), (int) ($locked->max_participants ?? 0));
            }

            return $registration;
        });
    }
}
