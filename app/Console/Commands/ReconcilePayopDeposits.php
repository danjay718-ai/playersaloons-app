<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Wallet\Actions\SyncPayopDepositAction;
use App\Modules\Wallet\Models\PaymentCheckout;
use Illuminate\Console\Command;
use Throwable;

class ReconcilePayopDeposits extends Command
{
    protected $signature = 'payments:reconcile-payop-deposits {invoice? : Limit verification to one Payop invoice ID}';

    protected $description = 'Verify and credit paid Payop deposits when callbacks were missed';

    public function handle(SyncPayopDepositAction $sync): int
    {
        $failed = false;
        PaymentCheckout::query()->where('provider', 'payop')->whereNull('completed_at')->whereNotNull('provider_reference')
            ->when($this->argument('invoice'), fn ($query, $invoice) => $query->where('provider_reference', $invoice))
            ->chunkById(100, function ($checkouts) use ($sync, &$failed): void {
                foreach ($checkouts as $checkout) {
                    try {
                        if ($sync->execute($checkout->provider_reference)) {
                            $this->info('Credited verified invoice '.$checkout->provider_reference);
                        }
                    } catch (Throwable $exception) {
                        report($exception);
                        $failed = true;
                        $this->error('Unable to verify invoice '.$checkout->provider_reference);
                    }
                }
            });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
