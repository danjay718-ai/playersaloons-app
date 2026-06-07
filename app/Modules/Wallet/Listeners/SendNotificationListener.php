<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Listeners;

use App\Modules\Community\Models\Notification;
use App\Modules\Wallet\Events\WithdrawalApproved;
use App\Modules\Wallet\Events\WithdrawalRejected;
use App\Modules\Wallet\Models\Withdrawal;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;

class SendNotificationListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Queue the listener on the 'notifications' queue.
     */
    public string $queue = 'notifications';

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

        Notification::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $withdrawal->getAttribute('user_id'),
            'type' => 'withdrawal_approved',
            'title' => 'Withdrawal Approved',
            'message' => "Your withdrawal request of PHP {$withdrawal->amount} was approved.",
            'read_at' => null,
        ]);
    }

    private function handleWithdrawalRejected(WithdrawalRejected $event): void
    {
        $withdrawal = Withdrawal::query()->findOrFail($event->withdrawalId);

        Notification::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $withdrawal->getAttribute('user_id'),
            'type' => 'withdrawal_rejected',
            'title' => 'Withdrawal Rejected',
            'message' => "Your withdrawal request of PHP {$withdrawal->amount} was rejected. Reason: {$event->reason}",
            'read_at' => null,
        ]);
    }
}
