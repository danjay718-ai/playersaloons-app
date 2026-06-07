<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Events\WalletFrozen;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\StateMachines\WalletStateMachine;
use App\Shared\Enums\WalletStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class FreezeWalletAction
{
    public function __construct(private readonly WalletStateMachine $stateMachine) {}

    /**
     * Freeze a wallet.
     *
     * @param Wallet $wallet
     * @param User $actor
     * @return void
     * @throws AuthorizationException
     */
    public function execute(Wallet $wallet, User $actor): void
    {
        if (! $actor->hasAnyRole(['ADMIN', 'SUPER_ADMIN'])) {
            throw new AuthorizationException('Only admins or super admins may freeze a wallet.');
        }

        DB::transaction(function () use ($wallet, $actor): void {
            $this->stateMachine->transition($wallet, WalletStatus::FROZEN, $actor);

            WalletFrozen::dispatch(
                (int) $wallet->getKey(),
                (int) $wallet->getAttribute('user_id')
            );
        });
    }
}
