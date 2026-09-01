<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Tournament\Actions\PurgeEmptyV2OccurrencesAction;
use Illuminate\Console\Command;

final class PurgeEmptyV2Occurrences extends Command
{
    protected $signature = 'tournaments:purge-empty-v2';

    protected $description = 'Permanently remove expired V2 occurrences that never acquired protected data.';

    public function handle(PurgeEmptyV2OccurrencesAction $purge): int
    {
        $count = $purge->execute();
        $this->info("Purged {$count} empty V2 occurrence(s).");

        return self::SUCCESS;
    }
}
