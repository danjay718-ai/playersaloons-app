<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Services;

use App\Modules\Operations\Models\SystemSetting;
use Illuminate\Support\Facades\Crypt;

class PaymentSettings
{
    public const CREDENTIALS = ['payop.public_key', 'payop.secret', 'stripe.secret', 'stripe.webhook_secret'];

    public function provider(): string
    {
        return (string) (SystemSetting::query()->where('key', 'payments.provider')->value('value') ?? 'stripe');
    }

    public function enabled(): bool
    {
        return filter_var(SystemSetting::query()->where('key', 'payments.enabled')->value('value') ?? true, FILTER_VALIDATE_BOOL);
    }

    public function credential(string $key): string
    {
        $value = SystemSetting::query()->where('key', 'payments.'.$key)->value('value');

        return $value === null ? (string) config('services.'.$key, '') : Crypt::decryptString($value);
    }
}
