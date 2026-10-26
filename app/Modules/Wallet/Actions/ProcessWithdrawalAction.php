<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Exceptions\PayoutSubmissionRejectedException;
use App\Modules\Wallet\Models\Withdrawal;
use App\Modules\Wallet\Services\PayopPayoutService;
use App\Modules\Wallet\StateMachines\WithdrawalStateMachine;
use App\Shared\Enums\LedgerType;
use App\Shared\Enums\WithdrawalStatus;
use App\Shared\Support\Currency;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

class ProcessWithdrawalAction
{
    public function __construct(private readonly WithdrawalStateMachine $stateMachine) {}

    /**
     * Submit PayOp payouts for verification, or mark externally paid manual withdrawals processed.
     *
     * @throws AuthorizationException
     */
    public function execute(Withdrawal $withdrawal, User $operator): void
    {
        if (! $operator->hasAnyRole(['FINANCE_OPERATOR', 'ADMIN', 'SUPER_ADMIN'])) {
            throw new AuthorizationException('Only finance operators or admins may process withdrawals.');
        }

        if ((int) $withdrawal->user_id === (int) $operator->getKey()) {
            throw new AuthorizationException('Requestors cannot process their own withdrawals.');
        }

        if ($withdrawal->payout_provider === 'payop') {
            $this->submitPayop($withdrawal);

            return;
        }

        DB::transaction(function () use ($withdrawal): void {
            $this->stateMachine->transition($withdrawal, WithdrawalStatus::PROCESSED);

            $withdrawal->update([
                'processed_at' => now(),
            ]);
        });
    }

    private function submitPayop(Withdrawal $withdrawal): void
    {
        $service = app(PayopPayoutService::class);
        if ($withdrawal->payout_currency !== Currency::code()) {
            throw new LogicException('Withdrawal currency no longer matches the wallet currency.');
        }
        $service->assertBankPayoutAvailable($withdrawal->payout_currency);
        $claimed = DB::transaction(function () use ($withdrawal): bool {
            $locked = Withdrawal::query()->lockForUpdate()->findOrFail($withdrawal->getKey());
            if ($locked->payout_submitted_at !== null) {
                return false;
            }
            if ($locked->status !== WithdrawalStatus::APPROVED
                || ! $locked->wallet->ledgerEntries()->where('reference_type', Withdrawal::class)
                    ->where('reference_id', (string) $locked->getKey())->where('type', LedgerType::WITHDRAWAL->value)->where('amount', '-'.$locked->amount)->exists()) {
                throw new LogicException('The withdrawal must be approved and debited before submitting a payout.');
            }
            $locked->update(['payout_submitted_at' => now(), 'payout_status' => 'submitting']);

            return true;
        });
        if (! $claimed) {
            return;
        }
        try {
            $reference = $service->submit($withdrawal->fresh());
            DB::transaction(function () use ($withdrawal, $reference): void {
                $locked = Withdrawal::query()->lockForUpdate()->findOrFail($withdrawal->getKey());
                if ($locked->payout_reference !== null && $locked->payout_reference !== $reference) {
                    throw new LogicException('PayOp returned a conflicting payout reference.');
                }
                $locked->payout_reference = $reference;
                if ($locked->status === WithdrawalStatus::APPROVED) {
                    $locked->payout_status = 'pending';
                }
                $locked->save();
            });
        } catch (PayoutSubmissionRejectedException $e) {
            app(FailPayopPayoutAction::class)->execute($withdrawal);
            throw $e;
        } catch (Throwable $e) {
            Withdrawal::query()->whereKey($withdrawal->getKey())->whereNull('payout_reference')
                ->where('status', WithdrawalStatus::APPROVED->value)->update(['payout_status' => 'unknown']);
            throw $e;
        }
        $withdrawal->refresh();
    }
}
