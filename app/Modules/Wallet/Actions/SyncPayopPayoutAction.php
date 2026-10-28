<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Actions;

use App\Modules\Wallet\Models\Withdrawal;
use App\Modules\Wallet\Services\PayopPayoutService;
use App\Modules\Wallet\StateMachines\WithdrawalStateMachine;
use App\Shared\Enums\WithdrawalStatus;
use App\Shared\Support\DecimalMoney;
use Illuminate\Support\Facades\DB;
use LogicException;

class SyncPayopPayoutAction
{
    public function execute(string $reference, ?string $expectedUuid = null): void
    {
        // Never trust the IPN's status or bank details; query the authenticated merchant API.
        $data = app(PayopPayoutService::class)->fetch($reference);
        $uuid = $data['metadata']['internalWithdrawalId'] ?? null;
        if (! is_string($uuid) || ($expectedUuid !== null && $uuid !== $expectedUuid)) {
            throw new LogicException('PayOp payout has no matching withdrawal reference.');
        }

        DB::transaction(function () use ($data, $uuid, $reference): void {
            $withdrawal = Withdrawal::query()->where('uuid', $uuid)->where('payout_provider', 'payop')->lockForUpdate()->first();
            if (! $withdrawal) {
                throw new LogicException('PayOp payout does not match a local withdrawal.');
            }
            if ($withdrawal->payout_currency !== ($data['currency'] ?? null)
                || ! is_numeric($data['amount'] ?? null)
                || DecimalMoney::toMinor((string) $data['amount']) !== DecimalMoney::toMinor($withdrawal->amount)
                || ($withdrawal->payout_reference !== null && $withdrawal->payout_reference !== $reference)
                || $withdrawal->payout_submitted_at === null) {
                throw new LogicException('PayOp payout verification mismatch.');
            }
            $state = (int) ($data['status'] ?? $data['state'] ?? 0);
            if (! in_array($state, [1, 2, 3, 4], true)) {
                throw new LogicException('Unknown PayOp payout status.');
            }
            if (in_array($withdrawal->status, [WithdrawalStatus::PROCESSED, WithdrawalStatus::FAILED], true)) {
                if (($state === 2 && $withdrawal->status === WithdrawalStatus::PROCESSED)
                    || ($state === 3 && $withdrawal->status === WithdrawalStatus::FAILED)) {
                    return;
                }
                throw new LogicException('PayOp changed a final payout status; manual reconciliation is required.');
            }
            $withdrawal->update(['payout_reference' => $reference, 'payout_status' => match ($state) {
                2 => 'accepted', 3 => 'rejected', default => 'pending'
            }]);
            $machine = app(WithdrawalStateMachine::class);
            if ($state === 2) {
                $machine->transition($withdrawal, WithdrawalStatus::PROCESSED);
                $withdrawal->update(['processed_at' => now()]);
            } elseif ($state === 3) {
                app(FailPayopPayoutAction::class)->execute($withdrawal);
            }
        });
    }
}
