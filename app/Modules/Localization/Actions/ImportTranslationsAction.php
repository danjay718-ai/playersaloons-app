<?php

declare(strict_types=1);

namespace App\Modules\Localization\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Localization\Models\TranslationString;
use App\Modules\Localization\Services\LanguageRegistry;
use App\Modules\Localization\Services\TranslationCatalogCache;
use App\Modules\Localization\Services\TranslationCatalogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;

final class ImportTranslationsAction
{
    public function execute(User $actor, string $locale, string $json): int
    {
        abort_unless($actor->can('translations.manage'), 403);
        abort_unless($locale !== 'en' && array_key_exists($locale, app(LanguageRegistry::class)->supported()), 422);
        try {
            $file = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->reject(__('Choose a valid translation JSON file.'));
        }
        if (! is_array($file) || ($file['version'] ?? null) !== 1 || ($file['source_locale'] ?? null) !== 'en'
            || ! is_array($file['entries'] ?? null) || ! array_is_list($file['entries'])) {
            $this->reject(__('Use the English export format and edit only the translation values.'));
        }

        return DB::transaction(function () use ($file, $locale): int {
            // Compare inside the transaction so a changed English catalog cannot
            // result in a partial or outdated import. All writes roll back together.
            $source = TranslationString::query()->where('locale', 'en')->orderBy('key')->lockForUpdate()->pluck('text', 'key')->all();
            $source = TranslationCatalogService::normalizeSource($source);
            if ($source === [] || ($file['source_hash'] ?? null) !== TranslationCatalogService::sourceHash($source)
                || count($file['entries']) !== count($source)) {
                $this->reject(__('The file does not match the current English catalog. Export English again. Nothing was imported.'));
            }
            $seen = [];
            $rows = [];
            $now = now();
            foreach ($file['entries'] as $entry) {
                if (! is_array($entry) || ! is_string($entry['key'] ?? null) || ! is_string($entry['translation'] ?? null)
                    || trim($entry['translation']) === '' || strlen($entry['translation']) > 60000) {
                    $this->reject(__('Every entry needs its English key, source, and a non-empty translation. Nothing was imported.'));
                }
                $key = $entry['key'];
                if (isset($seen[$key]) || ! array_key_exists($key, $source) || ($entry['source'] ?? null) !== $source[$key]) {
                    $this->reject(__('Missing, duplicate, extra, or changed English entries are not allowed. Nothing was imported.'));
                }
                // Preserve named substitutions and printf placeholders used by copy.
                if ($this->placeholders($entry['source']) !== $this->placeholders($entry['translation'])) {
                    $this->reject(__('Translation placeholders must match the English source. Nothing was imported.'));
                }
                $seen[$key] = true;
                $rows[] = ['key' => $key, 'locale' => $locale, 'text' => $entry['translation'],
                    'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null];
                if (count($rows) === 100) {
                    TranslationString::query()->upsert($rows, ['key', 'locale'], ['text', 'updated_at', 'deleted_at']);
                    $rows = [];
                }
            }
            if ($rows !== []) {
                TranslationString::query()->upsert($rows, ['key', 'locale'], ['text', 'updated_at', 'deleted_at']);
            }
            TranslationCatalogCache::invalidate($locale);

            return count($seen);
        });
    }

    private function placeholders(string $text): array
    {
        preg_match_all('/(?<![\pL\pN]):[a-zA-Z][a-zA-Z0-9_]*|%(?:\d+\$)?[-+0-9.]*[bcdeEfFgGosuxX]/u', $text, $matches);
        sort($matches[0]);

        return $matches[0];
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['importFile' => $message]);
    }
}
