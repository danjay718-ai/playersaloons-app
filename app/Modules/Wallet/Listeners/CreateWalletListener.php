<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Listeners;

use App\Modules\Identity\Events\UserRegistered;
use App\Modules\Wallet\Events\WalletCreated;
use App\Modules\Wallet\Models\Wallet;
use App\Shared\Enums\WalletStatus;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;

class CreateWalletListener
{
    use InteractsWithQueue;

    /**
     * Queue wallet creation on the 'wallet' queue.
     */
    public string $queue = 'wallet';

    /**
     * Create a wallet for every newly registered user.
     *
     * Triggered by: UserRegistered event.
     * Architecture: UserRegistered → CreateWalletListener (wallet queue)
     */
    public function handle(UserRegistered $event): void
    {
        // Idempotency guard — skip if wallet already exists
        if ((new Wallet)->newQuery()->where('user_id', $event->userId)->exists()) {
            return;
        }

        $wallet = new Wallet;
        $wallet->fill([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $event->userId,
            'cached_balance' => '0.00',
            'status' => WalletStatus::ACTIVE,
        ]);
        $wallet->save();

        WalletCreated::dispatch((int) $wallet->getKey(), $event->userId);
    }
}
