<?php

namespace App\Modules\Match\Jobs;

use App\Modules\Match\Actions\AutoForfeitAction;
use App\Modules\Match\Models\GameMatch;
use App\Shared\Enums\MatchStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AutoForfeitJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AutoForfeitAction $action): void
    {
        $matches = GameMatch::query()
            ->where('status', MatchStatus::WAITING_FOR_CONFIRMATION)
            ->with('tournament')
            ->get();

        foreach ($matches as $match) {
            if ($match->isTimedOut()) {
                $action->execute($match);
            }
        }
    }
}
