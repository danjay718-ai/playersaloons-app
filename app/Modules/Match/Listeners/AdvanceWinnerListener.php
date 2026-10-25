<?php

declare(strict_types=1);

namespace App\Modules\Match\Listeners;

use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Services\RandomWinnerAdvancementService;

class AdvanceWinnerListener
{
    public function __construct(
        private readonly RandomWinnerAdvancementService $randomAdvancement,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        // Support both MatchCompleted and MatchForfeited
        if (! property_exists($event, 'matchId')) {
            return;
        }

        $match = GameMatch::query()->find($event->matchId);
        if ($match !== null) {
            $this->randomAdvancement->advance($match);
        }
    }
}
