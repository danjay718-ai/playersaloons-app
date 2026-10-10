<?php

declare(strict_types=1);

namespace App\Modules\Localization\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Localization\Models\TranslationLanguage;
use App\Modules\Localization\Services\LanguageRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class AddTranslationLanguageAction
{
    public function execute(User $actor, string $code, string $english, string $native, string $flag = 'un'): void
    {
        abort_unless($actor->can('translations.manage'), 403);
        if (! Schema::hasTable('translation_languages') || ! Schema::hasColumn('translation_languages', 'flag')) {
            throw ValidationException::withMessages([
                'code' => __('Adding languages is unavailable until the application update is complete. No language was added.'),
            ]);
        }
        $flags = array_merge(['un'], array_map('strtolower', array_keys(config('countries', []))));
        $data = Validator::make(compact('code', 'english', 'native', 'flag'), [
            'code' => ['required', 'string', 'max:10', 'regex:/^[a-z]{2,3}(?:-[a-z0-9]{2,8})?$/', Rule::notIn(array_keys(app(LanguageRegistry::class)->supported())), Rule::unique('translation_languages', 'code')],
            'english' => ['required', 'string', 'max:80'],
            'native' => ['required', 'string', 'max:80'],
            'flag' => ['required', 'string', Rule::in($flags)],
        ])->validate();
        TranslationLanguage::query()->create($data);
        Cache::forget('localization.custom_languages');
        Cache::forget('localization.catalog_statistics');
    }
}
