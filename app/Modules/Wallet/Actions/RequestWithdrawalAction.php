<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Actions;

use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Events\WithdrawalRequested;
use App\Modules\Wallet\Exceptions\InsufficientBalanceException;
use App\Modules\Wallet\Exceptions\WithdrawalEligibilityException;
use App\Modules\Wallet\Models\Withdrawal;
use App\Modules\Wallet\Services\PaymentSettings;
use App\Modules\Wallet\Services\PayopPayoutService;
use App\Shared\Enums\KycStatus;
use App\Shared\Enums\WithdrawalStatus;
use App\Shared\Support\Currency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RequestWithdrawalAction
{
    /**
     * Request a withdrawal for a user.
     *
     * @param  string|float  $amount
     */
    public function execute(User $user, $amount, array $recipient = []): Withdrawal
    {
        if (! $user->hasVerifiedEmail()) {
            throw new WithdrawalEligibilityException('Verify your email before requesting a withdrawal.');
        }

        Validator::make(['amount' => $amount], ['amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2']])->validate();
        $amountFloat = (float) $amount;
        $provider = app(PaymentSettings::class)->payoutProvider();
        if (! in_array($provider, ['manual', 'payop'], true)) {
            throw new WithdrawalEligibilityException('Unsupported withdrawal gateway.');
        }
        $currency = Currency::code();
        if ($provider === 'payop') {
            if (is_string($recipient['iban'] ?? null)) {
                $recipient['iban'] = strtoupper(str_replace(' ', '', $recipient['iban']));
            }
            $recipient = Validator::make($recipient, [
                'iban' => ['required', 'string', 'regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/'],
                'name' => ['required', 'string', 'max:150'],
            ])->validate();
            $iban = substr($recipient['iban'], 4).substr($recipient['iban'], 0, 4);
            $remainder = 0;
            foreach (str_split($iban) as $character) {
                foreach (str_split(ctype_alpha($character) ? (string) (ord($character) - 55) : $character) as $digit) {
                    $remainder = ($remainder * 10 + (int) $digit) % 97;
                }
            }
            if ($remainder !== 1) {
                throw ValidationException::withMessages(['iban' => __('Enter a valid IBAN.')]);
            }
            $recipient['email'] = $user->email;
        }

        return DB::transaction(function () use ($user, $amountFloat, $provider, $currency, $recipient): Withdrawal {
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

            if ($provider === 'payop') {
                try {
                    app(PayopPayoutService::class)->assertBankPayoutAvailable($currency);
                } catch (\LogicException $e) {
                    throw new WithdrawalEligibilityException($e->getMessage(), __('Bank withdrawals are currently unavailable. Please contact support.'));
                }
            }

            $withdrawal = Withdrawal::query()->create([
                'uuid' => Str::uuid()->toString(),
                'wallet_id' => $wallet->getKey(),
                'user_id' => $user->getKey(),
                'amount' => $amountFloat,
                'status' => WithdrawalStatus::PENDING,
                'payout_provider' => $provider,
                'payout_currency' => $currency,
                'payout_recipient' => $provider === 'payop' ? $recipient : null,
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
