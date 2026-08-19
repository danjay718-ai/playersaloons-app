<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Operations\Models\ErrorIncident;
use Illuminate\Console\Command;

class PruneErrorIncidents extends Command
{
    protected $signature = 'errors:prune {--days=90 : Delete incidents last seen before this many days}';

    protected $description = 'Delete expired centralized error incidents';

    public function handle(): int
    {
        $days = max(7, min(3650, (int) $this->option('days')));
        $deleted = ErrorIncident::query()
            ->where('last_seen_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Pruned {$deleted} error incident(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
