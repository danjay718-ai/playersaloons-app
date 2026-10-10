<?php

declare(strict_types=1);

namespace App\Modules\Localization\Services;

use App\Modules\CMS\Models\CmsPageTranslation;
use App\Modules\CMS\Models\GameHeadToHeadDefault;
use App\Modules\CMS\Models\GameTournamentDefault;
use App\Modules\CMS\Models\GameTranslation;
use App\Modules\CMS\Models\LandingSection;
use App\Modules\CMS\Models\LandingSectionItem;
use App\Modules\CMS\Models\PolicyPage;
use App\Modules\CMS\Models\PublicNavigationItem;
use App\Modules\Localization\Models\TranslationString;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentScheduleSlot;
use App\Modules\Tournament\Models\TournamentTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

final class TranslationCatalogService
{
    private const WRITE_BATCH_SIZE = 100;

    public function __construct(private readonly ContentTranslationSegments $segments) {}

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

                if (count($rows) === self::WRITE_BATCH_SIZE) {
                    TranslationString::query()->upsert($rows, ['key', 'locale'], ['text', 'updated_at']);
                    $rows = [];
                }
            }

            if ($rows !== []) {
                TranslationString::query()->upsert($rows, ['key', 'locale'], ['text', 'updated_at']);
            }
        }

        $this->createMissingLocaleRows();

        return $synced;
    }

    public function syncFromDatabaseContent(): int
    {
        // Import public, staff-managed copy only; never private or player-authored data.
        $sources = [
            [LandingSection::query(), ['title', 'subtitle', 'body', 'cta_label']],
            [LandingSectionItem::query(), ['title', 'subtitle', 'body', 'label']],
            [PolicyPage::query(), ['title', 'summary', 'content']],
            [PublicNavigationItem::query(), ['label']],
            [GameTranslation::query()->where('locale', 'en')->whereHas('game'), ['name', 'description']],
            [CmsPageTranslation::query()->where('locale', 'en')->whereHas('page'), ['title', 'excerpt', 'content']],
        ];
        $content = [];
        foreach ($sources as [$query, $fields]) {
            foreach ($query->select($fields)->cursor() as $record) {
                foreach ($fields as $field) {
                    $content[] = $record->getAttribute($field);
                }
            }
        }
        $count = $this->syncContent($content);
        foreach ([Tournament::class, TournamentTemplate::class, TournamentScheduleSlot::class, GameTournamentDefault::class, GameHeadToHeadDefault::class] as $modelClass) {
            foreach ($modelClass::query()->cursor() as $record) {
                $count += $this->syncCompetitionContent($record);
            }
        }

        return $count;
    }

    public function syncCompetitionContent(Model $model): int
    {
        $content = [];
        foreach (['name', 'description', 'rules'] as $field) {
            // Read attributes explicitly: Tournament also has a rules relationship.
            $content[] = $model->getAttributes()[$field] ?? null;
        }
        foreach (['settings_json', 'overrides_json'] as $field) {
            $settings = $model->getAttribute($field);
            if (is_array($settings)) {
                foreach (['name', 'description', 'rules'] as $key) {
                    $content[] = $settings[$key] ?? null;
                }
            }
        }

        return $this->syncContent($content);
    }

    /** @param iterable<mixed> $content */
    public function syncContent(iterable $content): int
    {
        $phrases = [];
        foreach ($content as $text) {
            if (is_string($text)) {
                foreach ($this->segments->phrases($text) as $phrase) {
                    $phrases[$phrase] = true;
                }
            }
        }
        $rows = [];
        $now = now();
        foreach (array_keys($phrases) as $phrase) {
            foreach (array_keys($this->supportedLanguages()) as $locale) {
                $rows[] = [
                    'key' => $phrase,
                    'locale' => $locale,
                    'text' => $locale === 'en' ? $phrase : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        $count = 0;
        foreach (array_chunk($rows, 100) as $batch) {
            // The unique key protects simultaneous saves too. Keep existing values
            // and soft-deleted phrases intact; only insert genuinely new rows.
            $count += TranslationString::query()->insertOrIgnore($batch);
        }

        return $count;
    }

    public function createMissingLocaleRows(): void
    {
        $keys = TranslationString::query()
            ->where('locale', 'en')
            ->select(['id', 'key'])
            ->lazyById(self::WRITE_BATCH_SIZE);

        $now = now();
        $rows = [];
        $locales = array_keys($this->supportedLanguages());

        foreach ($keys as $source) {
            foreach ($locales as $locale) {
                $rows[] = [
                    'key' => $source->key,
                    'locale' => $locale,
                    'text' => $locale === 'en' ? $source->key : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($rows) === self::WRITE_BATCH_SIZE) {
                    TranslationString::query()->insertOrIgnore($rows);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            // Keep existing translations, timestamps, and soft deletions intact.
            TranslationString::query()->insertOrIgnore($rows);
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

    public function saveTranslation(string $key, string $locale, ?string $text): void
    {
        abort_unless(array_key_exists($locale, $this->supportedLanguages()), 422);

        TranslationString::query()->updateOrCreate(
            ['key' => $key, 'locale' => $locale],
            ['text' => $text],
        );
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
