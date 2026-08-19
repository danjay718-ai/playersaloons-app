<?php

declare(strict_types=1);

namespace App\Modules\Compliance\Services;

use App\Modules\Compliance\Models\BlockedCountry;
use Illuminate\Support\Facades\Cache;

final class CountryEligibilityService
{
    public const CACHE_KEY = 'compliance:blocked-country-codes';

    /** @return list<string> */
    public function blockedCodes(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addHour(), static fn (): array => BlockedCountry::query()
            ->orderBy('country_code')
            ->pluck('country_code')
            ->map(static fn (string $code): string => strtoupper($code))
            ->all());
    }

    /** @return array<string, string> */
    public function selectableCountries(): array
    {
        return array_diff_key(
            config('countries', []),
            array_fill_keys($this->blockedCodes(), true),
        );
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
