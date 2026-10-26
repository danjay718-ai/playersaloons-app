<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Wallet\Actions\SyncPayopPayoutAction;
use App\Modules\Wallet\Models\Withdrawal;
use App\Shared\Enums\WithdrawalStatus;
use Illuminate\Console\Command;
use Throwable;

class ReconcilePayopPayouts extends Command
{
    protected $signature = 'payments:reconcile-payop-payouts';

    protected $description = 'Verify pending PayOp payouts against the merchant API';

    public function handle(SyncPayopPayoutAction $sync): int
    {
        $failed = false;
        Withdrawal::query()->where('payout_provider', 'payop')->where('status', WithdrawalStatus::APPROVED->value)
            ->whereNotNull('payout_reference')->whereNotNull('payout_submitted_at')
            ->chunkById(100, function ($withdrawals) use ($sync, &$failed): void {
                foreach ($withdrawals as $withdrawal) {
                    try {
                        $sync->execute($withdrawal->payout_reference);
                    } catch (Throwable $e) {
                        report($e);
                        $failed = true;
                        $this->error('Unable to reconcile withdrawal '.$withdrawal->uuid);
                    }
                }
            });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
