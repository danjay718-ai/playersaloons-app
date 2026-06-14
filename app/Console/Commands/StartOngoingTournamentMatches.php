<?php

namespace App\Console\Commands;

use App\Modules\Match\Actions\StartMatchAction;
use App\Modules\Match\Models\GameMatch;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Console\Command;

class StartOngoingTournamentMatches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tournaments:start-matches {tournament_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start READY matches for ongoing tournaments';

    /**
     * Execute the console command.
     */
    public function handle(StartMatchAction $startMatchAction)
    {
        $query = GameMatch::query()
            ->where('status', MatchStatus::READY->value)
            ->whereHas('tournament', function ($q) {
                $q->where('status', TournamentStatus::ONGOING->value);
            });

        if ($id = $this->argument('tournament_id')) {
            $query->where('tournament_id', $id);
        }

        $matches = $query->get();
        
        if ($matches->isEmpty()) {
            $this->info("No READY matches found for ongoing tournaments.");
            return;
        }

        $this->info("Found {$matches->count()} matches to start.");

        foreach ($matches as $match) {
            try {
                $startMatchAction->execute($match);
                $this->line("Started match #{$match->id}");
            } catch (\Exception $e) {
                $this->error("Failed to start match #{$match->id}: " . $e->getMessage());
            }
        }

        $this->info('Done.');
    }
}
