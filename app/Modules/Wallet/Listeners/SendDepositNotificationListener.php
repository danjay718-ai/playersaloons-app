<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Listeners;

use App\Modules\Community\Models\Notification;
use App\Modules\Wallet\Events\WalletCredited;
use App\Modules\Wallet\Models\Wallet;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;

class SendDepositNotificationListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Queue the listener on the 'notifications' queue.
     */
    public string $queue = 'notifications';

    /**
     * Send a notification when a deposit is processed successfully.
     */
    public function handle(WalletCredited $event): void
    {
        $wallet = Wallet::query()->findOrFail($event->walletId);

        Notification::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $wallet->getAttribute('user_id'),
            'type' => 'deposit_completed',
            'title' => 'Deposit Successful',
            'message' => "Your deposit of PHP {$event->amount} was successfully credited to your wallet.",
            'read_at' => null,
        ]);
    }
}
