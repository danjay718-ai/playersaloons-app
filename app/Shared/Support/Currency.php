<?php

declare(strict_types=1);

namespace App\Shared\Support;

use App\Modules\Operations\Models\SystemSetting;

final class Currency
{
    public const SUPPORTED = ['EUR' => '€', 'GBP' => '£', 'USD' => '$'];

    public static function code(): string
    {
        $request = request();
        if (! app()->runningInConsole() && $request->attributes->has('app_currency')) {
            return (string) $request->attributes->get('app_currency');
        }

        $code = strtoupper((string) (SystemSetting::query()->where('key', 'currency.code')->value('value')
            ?? config('app.currency', 'EUR')));
        $code = array_key_exists($code, self::SUPPORTED) ? $code : 'EUR';
        $request->attributes->set('app_currency', $code);

        return $code;
    }

    public static function symbol(): string
    {
        return self::SUPPORTED[self::code()];
    }

    public static function format(string|int|float|null $amount): string
    {
        return self::symbol().number_format((float) ($amount ?? 0), 2);
    }

    public static function forget(): void
    {
        request()->attributes->remove('app_currency');
    }
}
