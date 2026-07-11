<?php

declare(strict_types=1);

namespace App\Modules\Identity\Listeners;

use App\Modules\Identity\Actions\AwardReferralRewardsAction;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Events\Verified;

class AwardReferralRewardsListener
{
    public function __construct(private AwardReferralRewardsAction $action) {}

    public function handle(Verified $event): void
    {
        if ($event->user instanceof User) {
            $this->action->execute($event->user);
        }
    }
}
