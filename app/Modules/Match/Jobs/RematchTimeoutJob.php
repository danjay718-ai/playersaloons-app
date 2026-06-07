<?php

declare(strict_types=1);

namespace App\Modules\Match\Jobs;

use App\Modules\Match\Actions\ForfeitMatchAction;
use App\Modules\Match\Models\GameMatch;
use App\Shared\Enums\MatchStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RematchTimeoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly int $rematchMatchId,
        public readonly int $forfeitRegistrationId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ForfeitMatchAction $forfeitAction): void
    {
        /** @var GameMatch|null $match */
        $match = GameMatch::query()->find($this->rematchMatchId);

        if ($match === null) {
            return;
        }

        // Only auto-forfeit if the rematch hasn't started yet (still READY)
        if ($match->status !== MatchStatus::READY) {
            return;
        }

        $forfeitAction->execute($match, $this->forfeitRegistrationId);
    }
}
