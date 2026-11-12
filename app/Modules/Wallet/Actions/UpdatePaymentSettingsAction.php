<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Operations\Services\SystemSettings;
use App\Modules\Wallet\Models\PaymentGateway;
use App\Modules\Wallet\Services\PaymentSettings;
use App\Modules\Wallet\Services\PayopPayoutService;
use App\Shared\Support\Currency;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdatePaymentSettingsAction
{
    public function execute(User $actor, string $provider, bool $enabled, array $credentials, string $payoutProvider = 'manual', array $resetCredentials = []): void
    {
        if (! $actor->can('system_settings.manage')) {
            throw new AuthorizationException;
        }
        Validator::make(['paymentProvider' => $provider, 'credentials' => $credentials, 'payoutProvider' => $payoutProvider, 'resetCredentials' => $resetCredentials], [
            'paymentProvider' => ['required', Rule::in(['stripe', 'payop'])],
            'credentials' => ['array'], 'credentials.*' => ['nullable', 'string', 'max:4096'],
            'payoutProvider' => ['required', Rule::in(['manual', 'payop'])],
            'resetCredentials' => ['array'], 'resetCredentials.*' => [Rule::in(PaymentSettings::CREDENTIALS)],
        ])->validate();
        $settings = app(PaymentSettings::class);
        $before = $values = $sources = $oldSources = [];
        foreach (PaymentSettings::CREDENTIALS as $key) {
            [$gateway, $name] = explode('.', $key, 2);
            $before[$key] = $settings->credential($key);
            $oldSources[$key] = $settings->source($key);
            $reset = in_array($key, $resetCredentials, true);
            $entered = trim((string) ($credentials[$key] ?? ''));
            $values[$gateway][$name] = $reset ? (string) config('services.'.$key, '') : ($entered !== '' ? $entered : $before[$key]);
            $sources[$gateway][$name] = $reset || ($entered === '' && $oldSources[$key] === 'environment');
        }
        foreach ($provider === 'payop' ? ['public_key', 'secret'] : ['secret', 'webhook_secret'] as $name) {
            if ($enabled && $values[$provider][$name] === '') {
                throw ValidationException::withMessages(['paymentProvider' => __('Configure the selected gateway credentials before enabling deposits.')]);
            }
        }
        $currency = Currency::code();
        if ($payoutProvider === 'payop') {
            try {
                app(PayopPayoutService::class)->assertBankPayoutAvailable($currency, $values['payop']);
            } catch (\Exception $exception) {
                throw ValidationException::withMessages(['payoutProvider' => __('PayOp bank payouts could not be verified for this currency. Check the token, certificate, and merchant payout permissions.')]);
            }
        }
        DB::transaction(function () use ($actor, $provider, $enabled, $payoutProvider, $settings, $before, $oldSources, $values, $sources, $currency): void {
            $gateways = PaymentGateway::query()->orderBy('id')->lockForUpdate()->get();
            Currency::forget();
            if (Currency::code() !== $currency) {
                throw ValidationException::withMessages(['paymentProvider' => __('Payment settings changed. Reload this page and try again.')]);
            }
            $settings->forget();
            foreach (PaymentSettings::CREDENTIALS as $key) {
                if ($settings->credential($key) !== $before[$key] || $settings->source($key) !== $oldSources[$key]) {
                    throw ValidationException::withMessages(['paymentProvider' => __('Payment settings changed. Reload this page and try again.')]);
                }
            }
            foreach ($gateways as $gateway) {
                $configuration = $settings->store($gateway, $values[$gateway->provider], $sources[$gateway->provider], (int) $actor->id);
                $changes = [];
                foreach ($values[$gateway->provider] as $name => $value) {
                    $key = $gateway->provider.'.'.$name;
                    if ($value !== $before[$key] || $sources[$gateway->provider][$name] !== ($oldSources[$key] === 'environment')) {
                        $changes[] = $name;
                    }
                }
                if ($changes !== []) {
                    activity()->causedBy($actor)->performedOn($configuration)->withProperties(['provider' => $gateway->provider, 'changed_credentials' => $changes])->log('gateway_credentials_updated');
                }
            }
            app(SystemSettings::class)->save($actor, ['payments.provider' => $provider, 'payments.enabled' => $enabled ? 'true' : 'false', 'payments.payout_provider' => $payoutProvider]);
        });
    }
}
