<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Events\WalletUnfrozen;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\StateMachines\WalletStateMachine;
use App\Shared\Enums\WalletStatus;
use Illuminate\Support\Facades\DB;

class UnfreezeWalletAction
{
    public function __construct(private readonly WalletStateMachine $stateMachine) {}

    /**
     * Unfreeze a wallet.
     */
    public function execute(Wallet $wallet, User $actor): void
    {
        DB::transaction(function () use ($wallet, $actor): void {
            $this->stateMachine->transition($wallet, WalletStatus::ACTIVE, $actor);

            WalletUnfrozen::dispatch(
                (int) $wallet->getKey(),
                (int) $wallet->getAttribute('user_id')
            );
        });
    }
}
