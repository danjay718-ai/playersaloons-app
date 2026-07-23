<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\TournamentStatus;
use App\Modules\Tournament\Actions\CancelTournamentAction;
use Illuminate\Support\Facades\Log;

class AutoCancelTournaments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tournaments:auto-cancel';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically cancels underfilled tournaments that have passed their start time.';

    /**
     * Execute the console command.
     */
    public function handle(CancelTournamentAction $cancelAction)
    {
        $tournaments = Tournament::query()
            ->where('is_auto_cancel_underfilled', true)
            ->whereIn('status', [
                TournamentStatus::REGISTRATION_OPEN, 
                TournamentStatus::REGISTRATION_CLOSED, 
                TournamentStatus::CHECKIN_OPEN, 
                TournamentStatus::CHECKIN_CLOSED, 
                TournamentStatus::PUBLISHED,
                TournamentStatus::DRAFT
            ])
            ->where('start_at', '<=', now())
            ->withCount('registrations')
            ->get();

        foreach ($tournaments as $tournament) {
            if ($tournament->registrations_count < $tournament->min_participants) {
                try {
                    $cancelAction->execute($tournament, null, 'Auto-cancelled: Minimum participants not met by start time.');
                    $this->info("Auto-cancelled tournament #{$tournament->id}");
                } catch (\Exception $e) {
                    Log::error("Failed to auto-cancel tournament {$tournament->id}: " . $e->getMessage());
                    $this->error("Failed to auto-cancel tournament #{$tournament->id}");
                }
            }
        }
    }
}
