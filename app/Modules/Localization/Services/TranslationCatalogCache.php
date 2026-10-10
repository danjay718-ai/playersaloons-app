<?php

declare(strict_types=1);

namespace App\Modules\Localization\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class TranslationCatalogCache
{
    public static function invalidate(?string $locale = null): void
    {
        DB::afterCommit(function () use ($locale): void {
            Cache::forget('localization.catalog_statistics');
            foreach ($locale === null || $locale === 'en' ? array_keys(app(LanguageRegistry::class)->supported()) : [$locale] as $code) {
                Cache::forget('localization.catalog.'.$code);
            }
            if (app()->resolved('translator')) {
                app('translator')->setLoaded([]);
            }
        });
    }
}
