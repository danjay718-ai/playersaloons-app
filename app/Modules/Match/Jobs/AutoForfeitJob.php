<?php

namespace App\Modules\Match\Jobs;

use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\StateMachines\MatchStateMachine;
use App\Shared\Enums\MatchStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AutoForfeitJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(MatchStateMachine $stateMachine): void
    {
        $matches = GameMatch::query()
            ->where('status', MatchStatus::WAITING_FOR_CONFIRMATION)
            ->with('tournament')
            ->get();

        foreach ($matches as $match) {
            $submittedAt = $match->result_submitted_at;
            if (!$submittedAt) continue;

            $waitTime = $match->tournament->waiting_result_time; // Mandatory: Tournament must have a value

            if ($submittedAt->addMinutes($waitTime)->isPast()) {
                // Time expired: Auto-confirm/forfeit the match
                $stateMachine->transition($match, MatchStatus::COMPLETED);
                
                // Dispatch event to trigger bracket progression
                \App\Modules\Match\Events\MatchCompleted::dispatch($match);
            }
        }
    }
}
