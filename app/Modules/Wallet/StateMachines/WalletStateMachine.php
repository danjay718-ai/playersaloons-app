<?php

declare(strict_types=1);

namespace App\Modules\Wallet\StateMachines;

use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Models\Wallet;
use App\Shared\Enums\WalletStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\StateMachines\AbstractStateMachine;

class WalletStateMachine extends AbstractStateMachine
{
    /**
     * {@inheritDoc}
     */
    protected function transitions(): array
    {
        return [
            WalletStatus::ACTIVE->value => [WalletStatus::SUSPENDED->value, WalletStatus::FROZEN->value],
            WalletStatus::SUSPENDED->value => [WalletStatus::ACTIVE->value, WalletStatus::FROZEN->value],
            WalletStatus::FROZEN->value => [WalletStatus::ACTIVE->value],
        ];
    }

    /**
     * Guard: only a super_admin may unfreeze a wallet.
     *
     * @throws \LogicException
     */
    public function guardCanUnfreeze(User $actor): void
    {
        if (! $actor->hasRole('SUPER_ADMIN') && ! $actor->hasRole('super_admin')) {
            throw new \LogicException('Only a super_admin may unfreeze a wallet.');
        }
    }

    /**
     * Transition a wallet to a new status.
     *
     * @throws InvalidStateTransitionException
     * @throws \LogicException
     */
    public function transition(Wallet $wallet, WalletStatus $to, ?User $actor = null): void
    {
        $this->assertValidTransition($wallet->status, $to);

        if ($to === WalletStatus::ACTIVE && $wallet->status === WalletStatus::FROZEN) {
            $this->guardCanUnfreeze($actor);
        }

        $wallet->status = $to;
        $wallet->save();
    }
}
