<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Actions;

use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Events\WithdrawalRequested;
use App\Modules\Wallet\Exceptions\InsufficientBalanceException;
use App\Modules\Wallet\Exceptions\WithdrawalEligibilityException;
use App\Modules\Wallet\Models\Withdrawal;
use App\Shared\Enums\KycStatus;
use App\Shared\Enums\WithdrawalStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RequestWithdrawalAction
{
    /**
     * Request a withdrawal for a user.
     *
     * @param  string|float  $amount
     */
    public function execute(User $user, $amount): Withdrawal
    {
        if (! $user->hasVerifiedEmail()) {
            throw new WithdrawalEligibilityException('Verify your email before requesting a withdrawal.');
        }

        $amountFloat = (float) $amount;
        if ($amountFloat <= 0) {
            throw new InvalidArgumentException('Withdrawal amount must be greater than zero.');
        }

        return DB::transaction(function () use ($user, $amountFloat): Withdrawal {
            // Verify KYC is approved
            $kyc = KycSubmission::query()
                ->where('user_id', $user->getKey())
                ->where('status', KycStatus::APPROVED)
                ->first();

            if (! $kyc instanceof KycSubmission) {
                throw new WithdrawalEligibilityException(
                    'KYC must be approved to request a withdrawal.',
                    'Your identity verification must be approved before you can withdraw. Check your verification status in Profile.'
                );
            }

            // Verify sufficient balance
            $wallet = $user->wallet;
            if (! $wallet) {
                throw new WithdrawalEligibilityException('User does not have a wallet.', 'Your wallet is unavailable. Please contact support.');
            }

            $currentBalance = (float) $wallet->getAttribute('cached_balance');
            if ($currentBalance < $amountFloat) {
                throw new InsufficientBalanceException(
                    $wallet->getAttribute('uuid'),
                    number_format($amountFloat, 2, '.', ''),
                    number_format($currentBalance, 2, '.', '')
                );
            }

            $withdrawal = Withdrawal::query()->create([
                'uuid' => Str::uuid()->toString(),
                'wallet_id' => $wallet->getKey(),
                'user_id' => $user->getKey(),
                'amount' => $amountFloat,
                'status' => WithdrawalStatus::PENDING,
            ]);

            WithdrawalRequested::dispatch(
                (int) $withdrawal->getKey(),
                (int) $wallet->getKey(),
                (int) $user->getKey(),
                number_format($amountFloat, 2, '.', '')
            );

            return $withdrawal;
        });
    }
}
