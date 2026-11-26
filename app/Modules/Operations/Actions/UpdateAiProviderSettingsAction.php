<?php

declare(strict_types=1);

namespace App\Modules\Operations\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Operations\Models\SystemSetting;
use App\Modules\Operations\Services\AiProviderSettings;
use App\Modules\Operations\Services\SystemSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class UpdateAiProviderSettingsAction
{
    public function __construct(
        private readonly SystemSettings $systemSettings,
        private readonly AiProviderSettings $aiSettings,
    ) {}

    /**
     * Save platform-wide AI provider selection, models, optional base URLs, and encrypted API keys.
     *
     * @param  array<string, string>  $apiKeys
     * @param  array<string, string>  $models
     * @param  array<string, string>  $baseUrls
     * @param  list<string>  $resetToEnvironment
     */
    public function execute(
        User $actor,
        string $activeProvider,
        array $apiKeys = [],
        array $models = [],
        array $baseUrls = [],
        array $resetToEnvironment = [],
    ): void {
        if (! $actor->can('system_settings.manage')) {
            throw new AuthorizationException;
        }

        $allowedSchemes = app()->environment(['local', 'testing']) ? ['http', 'https'] : ['https'];

        Validator::make([
            'aiProvider' => $activeProvider,
            'aiApiKeys' => $apiKeys,
            'aiModels' => $models,
            'aiBaseUrls' => $baseUrls,
            'resetAiCredentials' => $resetToEnvironment,
        ], [
            'aiProvider' => ['required', Rule::in(AiProviderSettings::PROVIDERS)],
            'aiApiKeys' => ['array'],
            'aiApiKeys.*' => ['nullable', 'string', 'max:4096'],
            'aiModels' => ['array'],
            'aiModels.*' => ['nullable', 'string', 'max:120'],
            'aiBaseUrls' => ['array'],
            'aiBaseUrls.*' => ['nullable', 'url:'.implode(',', $allowedSchemes), 'max:255'],
            'resetAiCredentials' => ['array'],
            'resetAiCredentials.*' => [Rule::in(AiProviderSettings::PROVIDERS)],
        ])->validate();

        $changedCredentials = [];
        $changedSettings = [];

        DB::transaction(function () use ($actor, $activeProvider, $apiKeys, $models, $baseUrls, $resetToEnvironment, &$changedCredentials, &$changedSettings): void {
            $nonSecretValues = ['ai.provider' => $activeProvider];

            foreach (AiProviderSettings::PROVIDERS as $provider) {
                $modelValue = trim((string) ($models[$provider] ?? ''));
                $nonSecretValues[$this->aiSettings->modelSettingKey($provider)] = $modelValue !== ''
                    ? $modelValue
                    : $this->aiSettings->catalog()[$provider]['default_model'];

                $baseUrlValue = rtrim(trim((string) ($baseUrls[$provider] ?? '')), '/');
                $nonSecretValues[$this->aiSettings->baseUrlSettingKey($provider)] = $baseUrlValue;

                $credentialKey = $this->aiSettings->credentialSettingKey($provider);
                $shouldReset = in_array($provider, $resetToEnvironment, true);
                $enteredKey = trim((string) ($apiKeys[$provider] ?? ''));

                if ($shouldReset) {
                    $deleted = SystemSetting::query()->where('key', $credentialKey)->delete();
                    if ($deleted > 0) {
                        $changedCredentials[] = "{$provider}.api_key:reset_to_env";
                    }
                } elseif ($enteredKey !== '') {
                    SystemSetting::query()->updateOrCreate(
                        ['key' => $credentialKey],
                        ['value' => Crypt::encryptString($enteredKey), 'updated_by' => $actor->id],
                    );
                    $changedCredentials[] = "{$provider}.api_key:updated";
                }
            }

            $before = SystemSetting::query()
                ->whereIn('key', array_keys($nonSecretValues))
                ->lockForUpdate()
                ->pluck('value', 'key')
                ->all();

            foreach ($nonSecretValues as $key => $value) {
                if (($before[$key] ?? null) !== $value) {
                    $changedSettings[$key] = ['before' => $before[$key] ?? null, 'after' => $value];
                }
                SystemSetting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $value, 'updated_by' => $actor->id],
                );
            }

            if ($changedSettings !== [] || $changedCredentials !== []) {
                activity()
                    ->causedBy($actor)
                    ->withProperties([
                        'active_provider' => $activeProvider,
                        'changed_settings' => $changedSettings,
                        'changed_credentials' => $changedCredentials,
                    ])
                    ->log('ai_provider_settings_updated');
            }
        });

        $this->systemSettings->forget();
    }
}
