<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Listeners;

use App\Modules\Community\Services\NotificationService;
use App\Modules\Wallet\Events\WithdrawalApproved;
use App\Modules\Wallet\Events\WithdrawalRejected;
use App\Modules\Wallet\Models\Withdrawal;
use Illuminate\Queue\InteractsWithQueue;

class SendNotificationListener
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
     * Handle incoming domain events.
     */
    public function handle(object $event): void
    {
        if ($event instanceof WithdrawalApproved) {
            $this->handleWithdrawalApproved($event);
        } elseif ($event instanceof WithdrawalRejected) {
            $this->handleWithdrawalRejected($event);
        }
    }

    private function handleWithdrawalApproved(WithdrawalApproved $event): void
    {
        $withdrawal = Withdrawal::query()->findOrFail($event->withdrawalId);
        $user = $withdrawal->user;

        if ($user !== null) {
            $this->notificationService->send(
                $user,
                'withdrawal_approved',
                'Withdrawal Approved',
                "Your withdrawal request of PHP {$withdrawal->amount} was approved."
            );
        }
    }

    private function handleWithdrawalRejected(WithdrawalRejected $event): void
    {
        $withdrawal = Withdrawal::query()->findOrFail($event->withdrawalId);
        $user = $withdrawal->user;

        if ($user !== null) {
            $this->notificationService->send(
                $user,
                'withdrawal_rejected',
                'Withdrawal Rejected',
                "Your withdrawal request of PHP {$withdrawal->amount} was rejected. Reason: {$event->reason}"
            );
        }
    }
}
