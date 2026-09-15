<?php

declare(strict_types=1);

namespace App\Modules\Localization\Services;

use App\Modules\Localization\Models\TranslationString;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

final class TranslationCatalogService
{
    /**
     * @return array<string, array{native: string, english: string}>
     */
    public function supportedLanguages(): array
    {
        return config('localization.supported', []);
    }

    public function syncFromJsonFiles(): int
    {
        $synced = 0;
        $now = now();

        foreach (array_keys($this->supportedLanguages()) as $locale) {
            $path = lang_path($locale.'.json');
            $translations = File::exists($path)
                ? json_decode((string) File::get($path), true)
                : [];

            if (! is_array($translations)) {
                $translations = [];
            }

            $rows = [];
            foreach ($translations as $key => $text) {
                if (! is_string($key)) {
                    continue;
                }

                $rows[] = [
                    'key' => $key,
                    'locale' => $locale,
                    'text' => is_scalar($text) ? (string) $text : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $synced++;
            }

            if ($rows !== []) {
                TranslationString::query()->upsert($rows, ['key', 'locale'], ['text', 'updated_at']);
            }
        }

        $this->createMissingLocaleRows();

        return $synced;
    }

    public function createMissingLocaleRows(): void
    {
        $keys = TranslationString::query()
            ->where('locale', 'en')
            ->pluck('key');

        $now = now();
        $rows = [];

        foreach ($keys as $key) {
            foreach (array_keys($this->supportedLanguages()) as $locale) {
                $rows[] = [
                    'key' => $key,
                    'locale' => $locale,
                    'text' => $locale === 'en' ? $key : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            // Only insert if missing — don't overwrite existing translations
            TranslationString::query()->upsert($rows, ['key', 'locale'], []);
        }
    }

    public function createKey(string $key): void
    {
        $key = trim($key);

        foreach (array_keys($this->supportedLanguages()) as $locale) {
            $row = TranslationString::withTrashed()->firstOrCreate(
                ['key' => $key, 'locale' => $locale],
                ['text' => $locale === 'en' ? $key : null],
            );
            if ($row->trashed()) {
                $row->restore();
            }
        }
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public function saveKey(string $key, array $values): void
    {
        foreach (array_keys($this->supportedLanguages()) as $locale) {
            TranslationString::query()->updateOrCreate(
                ['key' => $key, 'locale' => $locale],
                ['text' => $values[$locale] ?? null],
            );
        }
    }

    public function deleteKey(string $key): void
    {
        TranslationString::query()->where('key', $key)->delete();
    }

    public function fillMissingWithEnglishFallback(): int
    {
        $filled = 0;
        $englishRows = TranslationString::query()
            ->where('locale', 'en')
            ->pluck('text', 'key');

        foreach (array_keys($this->supportedLanguages()) as $locale) {
            if ($locale === 'en') {
                continue;
            }

            foreach ($englishRows as $key => $englishText) {
                if ($englishText === null || $englishText === '') {
                    continue;
                }

                $row = TranslationString::query()->firstOrCreate(
                    ['key' => $key, 'locale' => $locale],
                    ['text' => null],
                );

                if ($row->text === null || $row->text === '') {
                    $row->text = $englishText;
                    $row->save();
                    $filled++;
                }
            }
        }

        return $filled;
    }

    public function exportJsonFiles(): void
    {
        foreach (array_keys($this->supportedLanguages()) as $locale) {
            $translations = TranslationString::query()
                ->where('locale', $locale)
                ->whereNotNull('text')
                ->where('text', '!=', '')
                ->orderBy('key')
                ->pluck('text', 'key')
                ->all();

            File::ensureDirectoryExists(lang_path());
            File::put(
                lang_path($locale.'.json'),
                json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL,
            );
        }
    }

    /**
     * @return Collection<int, string>
     */
    public function missingKeysForLocale(string $locale): Collection
    {
        return TranslationString::query()
            ->where('locale', $locale)
            ->where(function ($query): void {
                $query->whereNull('text')->orWhere('text', '');
            })
            ->pluck('key');
    }
}
