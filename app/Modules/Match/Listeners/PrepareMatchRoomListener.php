<?php

declare(strict_types=1);

namespace App\Modules\Match\Listeners;

use App\Modules\Match\Events\MatchCreated;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Services\MatchReadinessService;

final class PrepareMatchRoomListener
{
    public function __construct(private readonly MatchReadinessService $readiness) {}

    public function handle(MatchCreated $event): void
    {
        $match = GameMatch::query()->find($event->matchId);
        if ($match !== null) {
            $this->readiness->prepare($match);
        }
    }
}
