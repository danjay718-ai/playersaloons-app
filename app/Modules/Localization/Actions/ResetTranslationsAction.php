<?php

declare(strict_types=1);

namespace App\Modules\Localization\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Localization\Models\TranslationString;
use App\Modules\Localization\Services\LanguageRegistry;
use App\Modules\Localization\Services\TranslationCatalogCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class ResetTranslationsAction
{
    public function execute(User $actor, string $locale, string $confirmation): int
    {
        abort_unless($actor->hasRole('SUPER_ADMIN') || $actor->can('translations.reset'), 403);
        $languages = array_keys(app(LanguageRegistry::class)->supported());
        abort_unless($locale === 'all' || ($locale !== 'en' && in_array($locale, $languages, true)), 422);
        $expected = $locale === 'all' ? 'RESET ALL' : strtoupper($locale);
        if ($confirmation !== $expected) {
            throw ValidationException::withMessages(['resetConfirmation' => __('Type :confirmation to confirm the reset.', ['confirmation' => $expected])]);
        }
        if (! Schema::hasTable('translation_language_resets')) {
            throw ValidationException::withMessages([
                'resetConfirmation' => __('Reset is unavailable until the application update is complete. No translations were changed.'),
            ]);
        }

        return DB::transaction(function () use ($actor, $locale, $languages): int {
            $query = TranslationString::withTrashed();
            $locale === 'all' ? $query->where('locale', '!=', 'en') : $query->where('locale', $locale);
            $locales = $locale === 'all'
                ? array_unique(array_merge(array_diff($languages, ['en']), (clone $query)->distinct()->pluck('locale')->all()))
                : [$locale];
            $count = $query->forceDelete();
            // Image-bundled JSON translations must not reappear after a reset.
            // Keep the language itself and its English fallback available.
            foreach ($locales as $code) {
                DB::table('translation_language_resets')->updateOrInsert(['locale' => $code], ['reset_at' => now()]);
            }
            activity()->causedBy($actor)->withProperties(['locale' => $locale, 'removed_rows' => $count])->log('translations_reset');
            DB::afterCommit(fn () => Cache::forget('localization.reset_languages'));
            TranslationCatalogCache::invalidate($locale === 'all' ? null : $locale);

            return $count;
        });
    }
}
