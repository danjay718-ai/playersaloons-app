<?php

declare(strict_types=1);

namespace App\Modules\Match\Listeners;

use App\Modules\Match\Events\MatchStarted;
use App\Modules\Match\Services\V2StalledMatchService;

final class ArmV2RoundDeadlineListener
{
    public function __construct(private readonly V2StalledMatchService $deadlines) {}

    public function handle(MatchStarted $event): void
    {
        $this->deadlines->armRoundDeadline($event->matchId);
    }
}
