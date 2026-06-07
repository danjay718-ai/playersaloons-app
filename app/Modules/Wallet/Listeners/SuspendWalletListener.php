<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Listeners;

use App\Modules\Identity\Events\UserSuspended;
use App\Modules\Wallet\Actions\SuspendWalletAction;
use App\Modules\Wallet\Models\Wallet;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SuspendWalletListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Queue the listener on the 'wallet' queue.
     */
    public string $queue = 'wallet';

    public function __construct(private readonly SuspendWalletAction $suspendWalletAction) {}

    /**
     * Suspend the user's wallet when they are suspended.
     */
    public function handle(UserSuspended $event): void
    {
        $wallet = Wallet::query()->where('user_id', $event->userId)->first();

        if ($wallet instanceof Wallet) {
            $this->suspendWalletAction->execute($wallet);
        }
    }
}
