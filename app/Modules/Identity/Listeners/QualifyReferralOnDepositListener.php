<?php

declare(strict_types=1);

namespace App\Modules\Identity\Listeners;

use App\Modules\Identity\Actions\AwardReferralRewardsAction;
use App\Modules\Wallet\Events\WalletCredited;
use App\Modules\Wallet\Models\Wallet;
use App\Shared\Enums\LedgerType;

class QualifyReferralOnDepositListener
{
    public function __construct(private AwardReferralRewardsAction $action) {}

    public function handle(WalletCredited $event): void
    {
        if ($event->type !== LedgerType::DEPOSIT->value) {
            return;
        }

        $wallet = Wallet::query()->with('user')->find($event->walletId);
        if ($wallet?->user) {
            $this->action->execute($wallet->user);
        }
    }
}
