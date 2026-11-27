<?php

declare(strict_types=1);

namespace App\Modules\Match\Jobs;

use App\Modules\Match\Actions\CreatePlayStrategyMatchGameAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class CreatePlayStrategyMatchGameJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Never blindly retry external game creation; uncertain outcomes go through reconciliation.
     */
    public int $tries = 1;

    public function __construct(
        public readonly int $matchId,
        public readonly ?int $attemptNumber = null,
    ) {
        // Always wait for the surrounding DB transaction to commit before touching PlayStrategy.
        $this->afterCommit();
    }

    public function handle(CreatePlayStrategyMatchGameAction $action): void
    {
        $action->execute($this->matchId, $this->attemptNumber);
    }
}
