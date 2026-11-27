<?php

declare(strict_types=1);

namespace App\Modules\Match\Jobs;

use App\Modules\Match\Actions\EvaluateAiDisputeAutoResolveAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EvaluateAiDisputeAutoResolveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly int $disputeId)
    {
        $this->afterCommit();
        $this->onQueue('default');
    }

    public function handle(EvaluateAiDisputeAutoResolveAction $evaluator): void
    {
        $evaluator->execute($this->disputeId);
    }
}
