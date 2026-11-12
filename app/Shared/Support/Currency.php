<?php

declare(strict_types=1);

namespace App\Shared\Support;

use App\Modules\Operations\Models\SystemSetting;
use Illuminate\Support\Facades\DB;

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

    /** Freeze the denomination before the first payment or ledger write can race an admin change. */
    public static function lockForFinancialActivity(): string
    {
        if (SystemSetting::query()->where('key', 'currency.locked')->value('value') === 'true') {
            return self::code();
        }

        return DB::transaction(function (): string {
            $setting = SystemSetting::query()->firstOrCreate(['key' => 'currency.code'], ['value' => self::code()]);
            $setting = SystemSetting::query()->lockForUpdate()->findOrFail($setting->id);
            SystemSetting::query()->updateOrCreate(['key' => 'currency.locked'], ['value' => 'true']);
            self::forget();

            return (string) $setting->value;
        });
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
