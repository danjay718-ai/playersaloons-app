<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Listeners;

use App\Modules\Identity\Events\UserUnsuspended;
use App\Modules\Wallet\Actions\UnsuspendWalletAction;
use App\Modules\Wallet\Models\Wallet;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UnsuspendWalletListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Queue the listener on the 'wallet' queue.
     */
    public string $queue = 'wallet';

    public function __construct(private readonly UnsuspendWalletAction $unsuspendWalletAction) {}

    /**
     * Unsuspend the user's wallet when they are unsuspended.
     */
    public function handle(UserUnsuspended $event): void
    {
        $wallet = Wallet::query()->where('user_id', $event->userId)->first();

        if ($wallet instanceof Wallet) {
            $this->unsuspendWalletAction->execute($wallet);
        }
    }
}
