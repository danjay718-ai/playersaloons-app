<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Services;

use App\Modules\Operations\Services\SystemSettings;
use App\Modules\Wallet\Models\GatewayConfiguration;
use App\Modules\Wallet\Models\PaymentGateway;
use Illuminate\Support\Facades\DB;

class PaymentSettings
{
    public const CREDENTIALS = ['payop.public_key', 'payop.secret', 'stripe.secret', 'stripe.webhook_secret', 'payop.payout_token', 'payop.payout_certificate'];

    private array $configurations = [];

    public function payoutProvider(): string
    {
        return (string) app(SystemSettings::class)->get('payments.payout_provider', 'manual');
    }

    public function provider(): string
    {
        return (string) app(SystemSettings::class)->get('payments.provider', 'stripe');
    }

    public function enabled(): bool
    {
        return filter_var(app(SystemSettings::class)->get('payments.enabled', true), FILTER_VALIDATE_BOOL);
    }

    public function current(string $provider): ?GatewayConfiguration
    {
        if (! array_key_exists($provider, $this->configurations)) {
            $this->configurations[$provider] = GatewayConfiguration::query()->with('credentials')
                ->where('payment_gateway_id', PaymentGateway::query()->where('provider', $provider)->select('id'))->latest('id')->first();
        }

        return $this->configurations[$provider];
    }

    public function credential(string $key): string
    {
        [$provider, $name] = explode('.', $key, 2);
        $credential = $this->current($provider)?->credentials->firstWhere('name', $name);

        return $credential && ! $credential->uses_environment ? (string) $credential->value : (string) config('services.'.$key, '');
    }

    public function source(string $key): string
    {
        [$provider, $name] = explode('.', $key, 2);
        $credential = $this->current($provider)?->credentials->firstWhere('name', $name);

        return $credential && ! $credential->uses_environment ? 'database' : 'environment';
    }

    public function forget(): void
    {
        $this->configurations = [];
    }

    /** Capture immutable credentials before contacting a provider. */
    public function capture(string $provider): GatewayConfiguration
    {
        return DB::transaction(function () use ($provider) {
            $gateway = PaymentGateway::query()->where('provider', $provider)->lockForUpdate()->firstOrFail();
            $this->forget();
            $values = $sources = [];
            foreach (self::CREDENTIALS as $key) {
                if (str_starts_with($key, $provider.'.')) {
                    $name = explode('.', $key, 2)[1];
                    $values[$name] = $this->credential($key);
                    $sources[$name] = $this->source($key) === 'environment';
                }
            }

            return $this->store($gateway, $values, $sources);
        });
    }

    public function store(PaymentGateway $gateway, array $values, array $sources, ?int $actorId = null): GatewayConfiguration
    {
        $current = $this->current($gateway->provider);
        $unchanged = $current !== null;
        foreach ($values as $name => $value) {
            $saved = $current?->credentials->firstWhere('name', $name);
            $unchanged = $unchanged && $saved && $saved->value === $value && $saved->uses_environment === $sources[$name];
        }
        if ($unchanged && $current !== null) {
            return $current;
        }
        $configuration = GatewayConfiguration::query()->create(['payment_gateway_id' => $gateway->id, 'updated_by' => $actorId]);
        foreach ($values as $name => $value) {
            $configuration->credentials()->create(['name' => $name, 'value' => $value, 'uses_environment' => $sources[$name]]);
        }
        $this->forget();

        return $configuration->load('credentials');
    }
}
