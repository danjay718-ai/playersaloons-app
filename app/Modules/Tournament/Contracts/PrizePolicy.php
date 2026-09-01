<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Contracts;

use App\Modules\Tournament\Models\Tournament;

interface PrizePolicy
{
    /** @return array<string, int|string|bool> */
    public function calculate(Tournament $tournament, int $joinedEntries): array;
}
