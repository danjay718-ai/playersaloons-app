<?php

declare(strict_types=1);

namespace App\Modules\Localization\Services;

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class CatalogTranslationLoader implements Loader
{
    public function __construct(private readonly Loader $files) {}

    public function load($locale, $group, $namespace = null)
    {
        $lines = $this->files->load($locale, $group, $namespace);
        if ($group !== '*' || $namespace !== '*') {
            return $lines;
        }
        if ($locale !== 'en' && app(LanguageRegistry::class)->wasReset($locale)) {
            $lines = $this->load('en', '*', '*');
        }
        $stored = Cache::get('localization.catalog.'.$locale);
        if ($stored === null) {
            if (! Schema::hasTable('translation_strings')) {
                return $lines;
            }
            $stored = DB::table('translation_strings as translated')
                ->join('translation_strings as source', function ($join): void {
                    $join->on('source.key', '=', 'translated.key')->where('source.locale', 'en')->whereNull('source.deleted_at');
                })
                ->where('translated.locale', $locale)->whereNull('translated.deleted_at')
                ->whereNotNull('translated.text')->where('translated.text', '!=', '')
                // English source rows already fall back to their key. Only
                // edited English values need to be loaded into runtime memory.
                ->when($locale === 'en', fn ($query) => $query->whereColumn('translated.text', '!=', 'translated.key'))
                ->pluck('translated.text', 'translated.key')->all();
            Cache::put('localization.catalog.'.$locale, $stored, 300);
        }

        return array_replace($lines, $stored);
    }

    public function addNamespace($namespace, $hint)
    {
        $this->files->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path)
    {
        $this->files->addJsonPath($path);
    }

    public function namespaces()
    {
        return $this->files->namespaces();
    }
}
