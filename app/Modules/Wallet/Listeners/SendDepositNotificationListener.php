<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Listeners;

use App\Modules\Community\Models\Notification;
use App\Modules\Community\Services\NotificationService;
use App\Modules\Wallet\Events\WalletCredited;
use App\Modules\Wallet\Models\Wallet;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendDepositNotificationListener
{
    use InteractsWithQueue;

    /**
     * Queue the listener on the 'notifications' queue.
     */
    public string $queue = 'notifications';

    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    /**
     * Send a notification when a deposit is processed successfully.
     */
    public function handle(WalletCredited $event): void
    {
        $wallet = Wallet::query()->findOrFail($event->walletId);
        $user = $wallet->user;

        if ($user !== null) {
            $this->notificationService->send(
                $user,
                'deposit_completed',
                'Deposit Successful',
                "Your deposit of PHP {$event->amount} was successfully credited to your wallet."
            );
        }
    }
}
