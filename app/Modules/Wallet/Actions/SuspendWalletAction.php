<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Actions;

use App\Modules\Wallet\Events\WalletSuspended;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\StateMachines\WalletStateMachine;
use App\Shared\Enums\WalletStatus;
use Illuminate\Support\Facades\DB;

class SuspendWalletAction
{
    public function __construct(private readonly WalletStateMachine $stateMachine) {}

    /**
     * Suspend a wallet.
     *
     * @param Wallet $wallet
     * @return void
     */
    public function execute(Wallet $wallet): void
    {
        DB::transaction(function () use ($wallet): void {
            $this->stateMachine->transition($wallet, WalletStatus::SUSPENDED);

            WalletSuspended::dispatch(
                (int) $wallet->getKey(),
                (int) $wallet->getAttribute('user_id')
            );
        });
    }
}
