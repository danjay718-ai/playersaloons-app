<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Tournament\Actions\PurgeEmptyV2OccurrencesAction;
use Illuminate\Console\Command;

final class PurgeEmptyV2Occurrences extends Command
{
    protected $signature = 'tournaments:purge-empty-v2';

    protected $description = 'Apply period-based purge/archive retention to cancelled V2 occurrences.';

    public function handle(PurgeEmptyV2OccurrencesAction $purge): int
    {
        $count = $purge->execute();
        $this->info("Cleaned up {$count} eligible V2 occurrence(s).");

        return self::SUCCESS;
    }
}
