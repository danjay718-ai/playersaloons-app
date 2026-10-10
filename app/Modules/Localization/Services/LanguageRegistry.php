<?php

declare(strict_types=1);

namespace App\Modules\Localization\Services;

use App\Modules\Localization\Models\TranslationLanguage;
use App\Modules\Operations\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class LanguageRegistry
{
    public function available(): array
    {
        $disabled = Cache::remember('localization.disabled_languages', 300, fn () => SystemSetting::query()
            ->where('key', 'like', 'language.disabled.%')->where('value', '1')->pluck('key')
            ->map(fn (string $key) => substr($key, strlen('language.disabled.')))->all());

        return array_diff_key($this->supported(), array_flip(array_diff($disabled, ['en'])));
    }

    public function wasReset(string $locale): bool
    {
        $reset = Cache::get('localization.reset_languages');
        if ($reset === null) {
            if (! Schema::hasTable('translation_language_resets')) {
                return false;
            }
            $reset = DB::table('translation_language_resets')->pluck('locale')->all();
            Cache::put('localization.reset_languages', $reset, 300);
        }

        return in_array($locale, $reset, true);
    }

    public function supported(): array
    {
        $custom = Cache::get('localization.custom_languages');
        if ($custom === null) {
            // Startup commands can run before this migration is installed.
            if (! Schema::hasTable('translation_languages')) {
                return config('localization.supported', []);
            }
            $custom = TranslationLanguage::query()->orderBy('english')->get()
                ->mapWithKeys(fn ($language) => [$language->code => [
                    'english' => $language->english, 'native' => $language->native, 'flag' => $language->flag ?? 'un',
                ]])->all();
            Cache::put('localization.custom_languages', $custom, 300);
        }

        return array_replace(config('localization.supported', []), $custom);
    }
}
