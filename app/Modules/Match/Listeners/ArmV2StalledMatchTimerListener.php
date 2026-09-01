<?php

declare(strict_types=1);

namespace App\Modules\Match\Listeners;

use App\Modules\Match\Services\V2StalledMatchService;

final class ArmV2StalledMatchTimerListener
{
    public function __construct(private readonly V2StalledMatchService $stalled) {}

    public function handle(object $event): void
    {
        if (property_exists($event, 'tournamentId')) {
            $this->stalled->armForTournament((int) $event->tournamentId);
        }
    }
}
