<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Operations\Models\SystemSetting;
use App\Modules\Wallet\Services\PaymentSettings;
use App\Modules\Wallet\Services\PayopPayoutService;
use App\Shared\Support\Currency;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdatePaymentSettingsAction
{
    public function execute(User $actor, string $provider, bool $enabled, array $credentials, string $payoutProvider = 'manual'): void
    {
        if (! $actor->can('system_settings.manage')) {
            throw new AuthorizationException;
        }

        Validator::make(['paymentProvider' => $provider, 'credentials' => $credentials, 'payoutProvider' => $payoutProvider], [
            'paymentProvider' => ['required', 'in:stripe,payop'],
            'credentials' => ['array'],
            'credentials.*' => ['nullable', 'string', 'max:4096'],
            'payoutProvider' => ['required', 'in:manual,payop'],
        ])->validate();

        DB::transaction(function () use ($actor, $provider, $enabled, $credentials, $payoutProvider): void {
            foreach (PaymentSettings::CREDENTIALS as $key) {
                $value = trim((string) ($credentials[$key] ?? ''));
                if ($value !== '') {
                    SystemSetting::query()->updateOrCreate(['key' => 'payments.'.$key], [
                        'value' => Crypt::encryptString($value), 'updated_by' => $actor->getKey(),
                    ]);
                }
            }

            $settings = app(PaymentSettings::class);
            if ($payoutProvider === 'payop') {
                if ($settings->credential('payop.payout_token') === '') {
                    throw ValidationException::withMessages(['payoutProvider' => __('Configure the PayOp payout token and upload its certificate first.')]);
                }
                try {
                    app(PayopPayoutService::class)->assertBankPayoutAvailable(Currency::code());
                } catch (\Exception $e) {
                    throw ValidationException::withMessages(['payoutProvider' => __('PayOp bank payouts could not be verified for this currency. Check the token, certificate, and merchant payout permissions.')]);
                }
            }
            $required = $provider === 'payop' ? ['payop.public_key', 'payop.secret'] : ['stripe.secret', 'stripe.webhook_secret'];
            foreach ($required as $key) {
                if ($enabled && $settings->credential($key) === '') {
                    throw ValidationException::withMessages(['paymentProvider' => __('Configure the selected gateway credentials before enabling deposits.')]);
                }
            }

            foreach (['payments.provider' => $provider, 'payments.enabled' => $enabled ? 'true' : 'false', 'payments.payout_provider' => $payoutProvider] as $key => $value) {
                SystemSetting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $actor->getKey()]);
            }
        });
    }
}
