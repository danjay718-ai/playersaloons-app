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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        return app(LanguageRegistry::class)->supported();
    }

    public function syncFromJsonFiles(): int
    {
        $synced = 0;
        $now = now();

        $path = lang_path('en.json');
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
                'locale' => 'en',
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

        TranslationCatalogCache::invalidate('en');

        $synced += $this->syncInterfacePhrases(app(InterfaceTranslationCatalog::class)->phrases());

        return $synced;
    }

    private function syncInterfacePhrases(iterable $phrases): int
    {
        $rows = [];
        $count = 0;
        $now = now();
        foreach ($phrases as $phrase) {
            // Interface keys must match __() and browser labels exactly, even
            // when one label contains multiple sentences.
            $rows[$phrase] = ['key' => $phrase, 'locale' => 'en', 'text' => $phrase, 'created_at' => $now, 'updated_at' => $now];
            if (count($rows) === self::WRITE_BATCH_SIZE) {
                $count += TranslationString::query()->insertOrIgnore(array_values($rows));
                $rows = [];
            }
        }
        if ($rows !== []) {
            $count += TranslationString::query()->insertOrIgnore(array_values($rows));
        }
        if ($count > 0) {
            TranslationCatalogCache::invalidate('en');
        }

        return $count;
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
        $rows = [];
        $count = 0;
        $now = now();
        foreach ($content as $text) {
            if (! is_string($text)) {
                continue;
            }
            foreach ($this->segments->phrases($text) as $phrase) {
                $rows[$phrase] = [
                    'key' => $phrase, 'locale' => 'en', 'text' => $phrase,
                    'created_at' => $now, 'updated_at' => $now,
                ];
                if (count($rows) >= self::WRITE_BATCH_SIZE) {
                    $count += TranslationString::query()->insertOrIgnore(array_values($rows));
                    $rows = [];
                }
            }
        }
        if ($rows !== []) {
            $count += TranslationString::query()->insertOrIgnore(array_values($rows));
        }
        if ($count > 0) {
            TranslationCatalogCache::invalidate('en');
        }

        return $count;
    }

    public function sourceCatalog(): array
    {
        $source = $this->sourceQuery()->orderBy('key')->pluck('text', 'key')->all();
        $source = self::normalizeSource($source);
        ksort($source, SORT_STRING);

        return $source;
    }

    public function sourceQuery(): Builder
    {
        $query = TranslationString::query()->where('locale', 'en')->whereIn('id',
            DB::table('translation_strings')->where('locale', 'en')->whereNull('deleted_at')
                ->selectRaw('COALESCE(MIN(CASE WHEN `key` = text THEN id END), MIN(id))')
                ->groupByRaw("COALESCE(NULLIF(text, ''), `key`)"));
        // Keep historical translations stored, while retiring generated dated titles from the work list.
        foreach (ContentTranslationSegments::MONTHS as $month) {
            $query->whereRaw("COALESCE(NULLIF(text, ''), `key`) NOT LIKE ?", ['% - '.$month.' __, ____ %:__%']);
        }

        return $query;
    }

    public static function normalizeSource(array $source): array
    {
        foreach ($source as $key => $text) {
            if ($text === null || $text === '') {
                $source[$key] = (string) $key;
            }
        }

        return $source;
    }

    public function englishExport(): array
    {
        $source = $this->sourceCatalog();
        $entries = [];
        foreach ($source as $key => $text) {
            $entries[] = ['key' => (string) $key, 'source' => $text, 'translation' => $text];
        }

        return [
            'version' => 1, 'source_locale' => 'en',
            'source_hash' => self::sourceHash($source), 'entries' => $entries,
        ];
    }

    public static function sourceHash(array $source): string
    {
        ksort($source, SORT_STRING);

        return hash('sha256', json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function statistics(): array
    {
        return Cache::remember('localization.catalog_statistics', 300, function (): array {
            $total = $this->sourceQuery()->count();
            $translated = DB::table('translation_strings as translated')
                ->join('translation_strings as source', function ($join): void {
                    $join->on('source.key', '=', 'translated.key')->where('source.locale', 'en')->whereNull('source.deleted_at');
                })
                ->whereIn('source.id', $this->sourceQuery()->select('id'))
                ->whereNull('translated.deleted_at')->whereNotNull('translated.text')->where('translated.text', '!=', '')
                ->selectRaw('translated.locale, COUNT(*) as total')->groupBy('translated.locale')
                ->pluck('total', 'translated.locale')->all();
            $missing = [];
            foreach (array_keys($this->supportedLanguages()) as $locale) {
                $missing[$locale] = max(0, $total - ($translated[$locale] ?? 0));
            }

            return ['total' => $total, 'missing' => $missing];
        });
    }

    public function createKey(string $key): void
    {
        $key = trim($key);

        $row = TranslationString::withTrashed()->firstOrCreate(
            ['key' => $key, 'locale' => 'en'], ['text' => $key],
        );
        if ($row->trashed()) {
            $row->restore();
        }
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public function saveKey(string $key, array $values): void
    {
        foreach ($values as $locale => $text) {
            $this->saveTranslation($key, $locale, $text);
        }
    }

    public function saveTranslation(string $key, string $locale, ?string $text): void
    {
        abort_unless(array_key_exists($locale, $this->supportedLanguages()), 422);
        abort_unless(TranslationString::query()->where('key', $key)->where('locale', 'en')->exists(), 404);
        $sourceText = TranslationString::query()->where('key', $key)->where('locale', 'en')->value('text');
        $keys = $locale === 'en' || $sourceText === null || $sourceText === ''
            ? collect([$key])
            : TranslationString::query()->where('locale', 'en')->where('text', $sourceText)->pluck('key');
        DB::transaction(function () use ($keys, $locale, $text): void {
            foreach ($keys as $alias) {
                if ($locale !== 'en' && ($text === null || trim($text) === '')) {
                    TranslationString::query()->where('key', $alias)->where('locale', $locale)->first()?->delete();

                    continue;
                }
                $row = TranslationString::withTrashed()->firstOrNew(['key' => $alias, 'locale' => $locale]);
                $row->text = $text;
                $row->exists && $row->trashed() ? $row->restore() : $row->save();
            }
        });
    }

    public function deleteKey(string $key): void
    {
        $sourceText = TranslationString::query()->where('key', $key)->where('locale', 'en')->value('text');
        $keys = $sourceText === null || $sourceText === '' ? collect([$key])
            : TranslationString::query()->where('locale', 'en')->where('text', $sourceText)->pluck('key');
        TranslationString::query()->whereIn('key', $keys)->delete();
        TranslationCatalogCache::invalidate();
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
        return $this->sourceQuery()
            ->whereNotIn('key', TranslationString::query()->select('key')->where('locale', $locale)->whereNotNull('text')->where('text', '!=', ''))
            ->pluck('key');
    }
}
