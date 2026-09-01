<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use App\Modules\Operations\Models\SystemSetting;
use DateTimeZone;

final class TournamentTimezone
{
    public function value(): string
    {
        $timezone = (string) (SystemSetting::query()->where('key', 'tournament.timezone')->value('value')
            ?? config('app.tournament_timezone', 'UTC'));

        return in_array($timezone, DateTimeZone::listIdentifiers(), true) ? $timezone : 'UTC';
    }
}
