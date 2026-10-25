<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Livewire\Admin\TranslationAdmin;
use App\Modules\Identity\Models\User;
use App\Modules\Localization\Actions\AddTranslationLanguageAction;
use App\Modules\Localization\Actions\ImportTranslationsAction;
use App\Modules\Localization\Models\TranslationString;
use App\Modules\Localization\Services\TranslationCatalogService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class TranslationImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::query()->create([
            'uuid' => Str::uuid(), 'email' => 'translation-import@example.com',
            'username' => 'translation_import', 'password' => bcrypt('password'), 'status' => 'active',
        ]);
        $this->admin->assignRole('ADMIN');
        app(TranslationCatalogService::class)->syncContent(['Hello :name.', 'Welcome back.']);
    }

    public function test_downloaded_english_template_imports_a_complete_language_and_updates_runtime_translation(): void
    {
        $catalog = app(TranslationCatalogService::class);
        Livewire::actingAs($this->admin)->test(TranslationAdmin::class)
            ->call('exportEnglish')->assertFileDownloaded('english-translations.json');
        $file = $catalog->englishExport();
        foreach ($file['entries'] as &$entry) {
            $entry['translation'] = $entry['key'] === 'Hello :name.' ? 'Bonjour :name.' : 'Bienvenue.';
        }
        unset($entry);
        app()->setLocale('fr');
        __('Hello :name.'); // Warm the runtime cache before the import.
        $page = Livewire::actingAs($this->admin)->test(TranslationAdmin::class)
            ->set('importLocale', 'fr')
            ->set('importFile', UploadedFile::fake()->createWithContent('fr.json', json_encode($file)))
            ->call('importTranslations')->assertHasNoErrors()->assertSet('localeFilter', 'fr');
        self::assertSame('Bonjour :name.', __('Hello :name.'));
        self::assertDatabaseHas('translation_strings', ['key' => 'Welcome back.', 'locale' => 'fr', 'text' => 'Bienvenue.']);
        self::assertDatabaseMissing('translation_strings', ['locale' => 'es']);
    }

    public static function invalidImports(): array
    {
        return [['missing'], ['extra'], ['duplicate'], ['source'], ['hash'], ['empty'], ['placeholder'], ['version'], ['invalid_json']];
    }

    #[DataProvider('invalidImports')]
    public function test_non_equivalent_imports_leave_all_existing_translations_unchanged(string $case): void
    {
        $catalog = app(TranslationCatalogService::class);
        $catalog->saveTranslation('Hello :name.', 'fr', 'Original :name.');
        $file = $catalog->englishExport();
        foreach ($file['entries'] as &$entry) {
            $entry['translation'] = 'Updated '.$entry['source'];
        }
        unset($entry);
        match ($case) {
            'missing' => array_pop($file['entries']),
            'extra' => $file['entries'][] = ['key' => 'Extra', 'source' => 'Extra', 'translation' => 'Extra'],
            'duplicate' => $file['entries'][1] = $file['entries'][0],
            'source' => $file['entries'][1]['source'] = 'Changed English',
            'hash' => $file['source_hash'] = 'outdated',
            'empty' => $file['entries'][1]['translation'] = '',
            'placeholder' => $file['entries'][0]['translation'] = 'No substitution',
            'version' => $file['version'] = 2,
            default => null,
        };
        $json = $case === 'invalid_json' ? '{bad json' : json_encode($file);
        Livewire::actingAs($this->admin)->test(TranslationAdmin::class)
            ->set('importLocale', 'fr')->set('importFile', UploadedFile::fake()->createWithContent('fr.json', $json))
            ->call('importTranslations')->assertHasErrors('importFile');
        self::assertDatabaseHas('translation_strings', ['key' => 'Hello :name.', 'locale' => 'fr', 'text' => 'Original :name.']);
        self::assertSame(1, TranslationString::query()->where('locale', 'fr')->count());
    }

    public function test_a_late_invalid_entry_rolls_back_already_written_batches_and_retains_runtime_cache(): void
    {
        $catalog = app(TranslationCatalogService::class);
        for ($index = 0; $index < 150; $index++) {
            $catalog->createKey(sprintf('Phrase %03d', $index));
        }
        $catalog->saveTranslation('Hello :name.', 'fr', 'Original :name.');
        app()->setLocale('fr');
        self::assertSame('Original :name.', __('Hello :name.'));
        $file = $catalog->englishExport();
        foreach ($file['entries'] as &$entry) {
            $entry['translation'] = 'Translated '.$entry['source'];
        }
        unset($entry);
        $file['entries'][count($file['entries']) - 1]['translation'] = 'Unexpected :placeholder';
        $writes = 0;
        DB::listen(function (QueryExecuted $query) use (&$writes): void {
            if (str_contains($query->sql, 'translation_strings') && str_starts_with(strtolower($query->sql), 'insert')) {
                $writes++;
            }
        });
        try {
            app(ImportTranslationsAction::class)->execute($this->admin, 'fr', json_encode($file));
            self::fail('A non-equivalent import must fail.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('importFile', $exception->errors());
        }
        self::assertGreaterThan(0, $writes);
        self::assertSame(1, TranslationString::query()->where('locale', 'fr')->count());
        self::assertSame('Original :name.', __('Hello :name.'));
    }

    public function test_new_english_phrases_do_not_invalidate_an_unchanged_export_for_filipino(): void
    {
        $catalog = app(TranslationCatalogService::class);
        $file = $catalog->englishExport();
        app(AddTranslationLanguageAction::class)->execute($this->admin, 'fil', 'Filipino', 'Filipino');
        $catalog->createKey('Added after export.');
        self::assertSame(2, app(ImportTranslationsAction::class)->execute($this->admin, 'fil', json_encode($file)));
        self::assertDatabaseMissing('translation_strings', ['key' => 'Added after export.', 'locale' => 'fil']);
    }

    public function test_changed_existing_english_source_rejects_an_earlier_export(): void
    {
        $catalog = app(TranslationCatalogService::class);
        $file = $catalog->englishExport();
        $catalog->saveTranslation('Welcome back.', 'en', 'Updated welcome.');
        $this->expectException(ValidationException::class);
        app(ImportTranslationsAction::class)->execute($this->admin, 'fr', json_encode($file));
    }

    public function test_duplicate_english_text_is_listed_and_exported_once_and_import_updates_aliases(): void
    {
        $catalog = app(TranslationCatalogService::class);
        TranslationString::query()->create(['key' => 'legacy.welcome', 'locale' => 'en', 'text' => 'Welcome back.']);
        $file = $catalog->englishExport();
        self::assertCount(2, $file['entries']);
        self::assertSame(2, $catalog->statistics()['total']);
        Livewire::actingAs($this->admin)->test(TranslationAdmin::class)
            ->set('search', 'Welcome back.')->assertViewHas('keys', fn ($keys) => $keys->total() === 1);
        foreach ($file['entries'] as &$entry) {
            $entry['translation'] = 'Salin '.$entry['source'];
        }
        unset($entry);
        app(ImportTranslationsAction::class)->execute($this->admin, 'fr', json_encode($file));
        self::assertDatabaseHas('translation_strings', ['key' => 'legacy.welcome', 'locale' => 'fr', 'text' => 'Salin Welcome back.']);
        $catalog->saveTranslation('Welcome back.', 'fr', 'Bienvenue.');
        self::assertDatabaseHas('translation_strings', ['key' => 'legacy.welcome', 'locale' => 'fr', 'text' => 'Bienvenue.']);
    }

    public function test_import_works_for_new_languages_and_duplicate_or_unsafe_codes_are_rejected(): void
    {
        $action = app(AddTranslationLanguageAction::class);
        $action->execute($this->admin, 'fil', 'Filipino', 'Filipino');
        $file = app(TranslationCatalogService::class)->englishExport();
        foreach ($file['entries'] as &$entry) {
            $entry['translation'] = 'Salin '.$entry['source'];
        }
        unset($entry);
        self::assertSame(2, app(ImportTranslationsAction::class)->execute($this->admin, 'fil', json_encode($file)));
        app()->setLocale('fil');
        self::assertSame('Salin Welcome back.', __('Welcome back.'));
        foreach (['fil', 'en', '../fr', 'fr.json'] as $code) {
            try {
                $action->execute($this->admin, $code, 'Invalid', 'Invalid');
                self::fail('Invalid or duplicate locale accepted.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('code', $exception->errors());
            }
        }
    }

    public function test_catalog_summary_queries_are_constant_and_cached_between_page_actions(): void
    {
        Cache::forget('localization.catalog_statistics');
        $counts = [];
        DB::listen(function (QueryExecuted $query) use (&$counts): void {
            if (str_contains($query->sql, 'translation_strings') && str_contains(strtolower($query->sql), 'count(')) {
                $counts[] = $query->sql;
            }
        });
        $page = Livewire::actingAs($this->admin)->test(TranslationAdmin::class);
        self::assertLessThanOrEqual(2, count($counts));
        $before = count($counts);
        $page->call('$refresh');
        self::assertSame(0, count($counts) - $before);
    }

    public function test_placeholder_cleanup_preserves_actual_translations_and_recoverable_entries(): void
    {
        TranslationString::query()->create(['key' => 'Empty French', 'locale' => 'fr', 'text' => null]);
        TranslationString::query()->create(['key' => 'Empty Spanish', 'locale' => 'es', 'text' => '']);
        $deleted = TranslationString::query()->create(['key' => 'Recoverable French', 'locale' => 'fr', 'text' => null]);
        $deleted->delete();
        TranslationString::query()->create(['key' => 'Existing French', 'locale' => 'fr', 'text' => 'Bonjour']);
        $migration = require database_path('migrations/2026_10_10_010100_remove_empty_translation_placeholders.php');
        $migration->up();
        self::assertDatabaseMissing('translation_strings', ['key' => 'Empty French']);
        self::assertDatabaseMissing('translation_strings', ['key' => 'Empty Spanish']);
        self::assertDatabaseHas('translation_strings', ['key' => 'Existing French', 'text' => 'Bonjour']);
        self::assertTrue($deleted->fresh()->trashed());
        self::assertDatabaseHas('translation_strings', ['key' => 'Hello :name.', 'locale' => 'en']);
    }

    public function test_blank_target_edits_do_not_create_placeholder_rows_and_english_exports_remain_canonical(): void
    {
        $catalog = app(TranslationCatalogService::class);
        $catalog->saveTranslation('Welcome back.', 'fr', null);
        self::assertDatabaseMissing('translation_strings', ['key' => 'Welcome back.', 'locale' => 'fr']);
        TranslationString::query()->where('locale', 'en')->where('key', 'Welcome back.')->update(['text' => null]);
        $file = $catalog->englishExport();
        $file['entries'] = array_reverse($file['entries']);
        foreach ($file['entries'] as &$entry) {
            $entry['translation'] = 'Translated '.$entry['source'];
        }
        unset($entry);
        self::assertSame(2, app(ImportTranslationsAction::class)->execute($this->admin, 'fr', json_encode($file)));
    }

    public function test_runtime_english_cache_contains_only_edits_instead_of_every_source_phrase(): void
    {
        $catalog = app(TranslationCatalogService::class);
        app()->setLocale('en');
        self::assertSame('Welcome back.', __('Welcome back.'));
        self::assertSame([], Cache::get('localization.catalog.en'));
        $catalog->saveTranslation('Welcome back.', 'en', 'Welcome again.');
        self::assertSame('Welcome again.', __('Welcome back.'));
        self::assertSame(['Welcome back.' => 'Welcome again.'], Cache::get('localization.catalog.en'));
    }

    public function test_import_and_add_language_actions_authorize_the_actor(): void
    {
        $player = User::query()->create([
            'uuid' => Str::uuid(), 'email' => 'translation-player@example.com',
            'username' => 'translation_player', 'password' => bcrypt('password'), 'status' => 'active',
        ]);
        $player->assignRole('PLAYER');
        foreach ([
            fn () => app(AddTranslationLanguageAction::class)->execute($player, 'fil', 'Filipino', 'Filipino'),
            fn () => app(ImportTranslationsAction::class)->execute($player, 'fr', json_encode(app(TranslationCatalogService::class)->englishExport())),
        ] as $attempt) {
            try {
                $attempt();
                self::fail('Unauthorized mutation accepted.');
            } catch (HttpException $exception) {
                self::assertSame(403, $exception->getStatusCode());
            }
        }
    }
}
