<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use App\Modules\Operations\Models\SystemSetting;
use DateTimeZone;

final class TournamentTimezone
{
    public const AMSTERDAM = 'Europe/Amsterdam';

    public function value(): string
    {
        $request = request();
        if (! app()->runningInConsole() && $request->attributes->has('app_timezone')) {
            return (string) $request->attributes->get('app_timezone');
        }

        $timezone = (string) (SystemSetting::query()->where('key', 'tournament.timezone')->value('value')
            ?? config('app.tournament_timezone', 'Europe/Amsterdam'));

        $timezone = in_array($timezone, DateTimeZone::listIdentifiers(), true) ? $timezone : self::AMSTERDAM;
        $request->attributes->set('app_timezone', $timezone);

        return $timezone;
    }

    public function forget(): void
    {
        request()->attributes->remove('app_timezone');
    }
}
