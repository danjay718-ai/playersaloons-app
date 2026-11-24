<?php

declare(strict_types=1);

namespace App\Modules\Match\Jobs;

use App\Modules\Match\Actions\ReconcilePlayStrategyMatchGameAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ReconcilePlayStrategyMatchGameJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $externalMatchGameId)
    {
        $this->afterCommit();
    }

    public function handle(ReconcilePlayStrategyMatchGameAction $action): void
    {
        $action->execute($this->externalMatchGameId);
    }
}
