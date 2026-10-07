<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\GameHeadToHeadDefault;
use App\Modules\CMS\Models\GameTournamentDefault;
use App\Modules\Identity\Models\User;
use App\Modules\Localization\Models\TranslationString;
use App\Modules\Localization\Services\ContentTranslationSegments;
use App\Modules\Localization\Services\TranslationCatalogService;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentScheduleSlot;
use App\Modules\Tournament\Models\TournamentTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CompetitionContentTranslationTest extends TestCase
{
    use RefreshDatabase;

    private string $temporaryLangPath;

    private Game $game;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporaryLangPath = sys_get_temp_dir().'/competition-translations-'.Str::uuid();
        File::makeDirectory($this->temporaryLangPath);
        $this->app->useLangPath($this->temporaryLangPath);
        $this->app->forgetInstance('translation.loader');
        $this->app->forgetInstance('translator');
        $this->game = Game::query()->create(['uuid' => Str::uuid(), 'slug' => 'translation-game', 'is_active' => true]);
        $this->admin = User::query()->create([
            'uuid' => Str::uuid(), 'email' => 'copy-admin@example.com',
            'username' => 'copy_admin', 'password' => bcrypt('password'), 'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryLangPath);
        parent::tearDown();
    }

    private function tournament(array $content = []): Tournament
    {
        return Tournament::query()->create([
            'uuid' => Str::uuid(), 'slug' => 'copy-'.Str::random(8), 'name' => 'Translation Cup',
            'game_id' => $this->game->id, 'created_by' => $this->admin->id,
            'status' => 'DRAFT', 'max_participants' => 16, 'min_participants' => 2, 'entry_fee' => 0,
            ...$content,
        ]);
    }

    public function test_new_tournaments_automatically_import_long_copy_without_duplicate_phrases(): void
    {
        $rules = '<p>'.str_repeat('Keep your account safe. Report your results promptly. ', 40).'</p>';
        $this->tournament(['description' => 'Keep your account safe.', 'rules' => $rules]);
        $this->tournament(['description' => 'Keep your account safe.', 'rules' => $rules]);
        foreach (['Translation Cup', 'Keep your account safe.', 'Report your results promptly.'] as $phrase) {
            $this->assertSame(count(config('localization.supported')), TranslationString::query()->where('key', $phrase)->count());
        }
        $this->assertSame(3, TranslationString::query()->where('locale', 'en')->count());
        $this->assertDatabaseMissing('translation_strings', ['key' => 'Keep']);
    }

    public function test_updates_add_new_phrases_and_preserve_existing_and_deleted_translations(): void
    {
        $tournament = $this->tournament(['description' => 'Keep your account safe.']);
        $catalog = app(TranslationCatalogService::class);
        $catalog->saveTranslation('Keep your account safe.', 'fr', 'Protégez votre compte.');
        $catalog->syncContent(['Deleted phrase.']);
        $catalog->deleteKey('Deleted phrase.');
        $tournament->update(['description' => 'Keep your account safe. New tournament instructions. Deleted phrase.']);
        $this->assertDatabaseHas('translation_strings', ['key' => 'New tournament instructions.', 'locale' => 'en']);
        $this->assertDatabaseHas('translation_strings', ['key' => 'Keep your account safe.', 'locale' => 'fr', 'text' => 'Protégez votre compte.']);
        $this->assertSame(0, TranslationString::query()->where('key', 'Deleted phrase.')->count());
        $catalog->syncFromDatabaseContent();
        $this->assertSame(0, $catalog->syncFromDatabaseContent());
    }

    public function test_templates_slots_and_game_defaults_import_copy_on_creation_and_update(): void
    {
        $template = TournamentTemplate::query()->create([
            'uuid' => Str::uuid(), 'name' => 'Recurring Cup', 'game_id' => $this->game->id,
            'max_participants' => 16, 'min_participants' => 2,
            'settings_json' => ['description' => 'Template description.', 'rules' => '<p>Shared rules.</p>'],
        ]);
        $slot = TournamentScheduleSlot::query()->create([
            'uuid' => Str::uuid(), 'tournament_template_id' => $template->id,
            'identity_key' => 'daily-12:00', 'local_start_time' => '12:00', 'overrides_json' => ['rules' => '<p>Slot rules.</p>'],
        ]);
        $slot->update(['overrides_json' => ['description' => 'Updated slot description.']]);
        $template->update(['settings_json' => ['description' => 'Updated template description.']]);
        $defaults = GameTournamentDefault::query()->create(['game_id' => $this->game->id, 'rules' => '<p>Shared rules.</p>']);
        $defaults->update(['description' => 'Updated default description.']);
        GameHeadToHeadDefault::query()->create(['game_id' => $this->game->id, 'description' => 'Head to head description.']);
        foreach (['Template description.', 'Shared rules.', 'Slot rules.', 'Updated slot description.', 'Updated template description.', 'Updated default description.', 'Head to head description.'] as $phrase) {
            $this->assertDatabaseHas('translation_strings', ['key' => $phrase, 'locale' => 'en', 'text' => $phrase]);
        }
        $this->assertSame(1, TranslationString::query()->where('key', 'Shared rules.')->where('locale', 'en')->count());
    }

    public function test_database_sync_backfills_existing_tournaments(): void
    {
        Tournament::withoutEvents(fn () => $this->tournament(['rules' => '<p>Existing competition rules.</p>']));
        $this->assertDatabaseMissing('translation_strings', ['key' => 'Existing competition rules.']);
        $catalog = app(TranslationCatalogService::class);
        $this->assertGreaterThan(0, $catalog->syncFromDatabaseContent());
        $this->assertDatabaseHas('translation_strings', ['key' => 'Existing competition rules.', 'locale' => 'en']);
        $this->assertSame(0, $catalog->syncFromDatabaseContent());
    }

    public function test_sentence_translations_render_in_html_and_livewire_with_markup_and_spacing_preserved(): void
    {
        $tournament = $this->tournament(['rules' => '<p>Keep your account safe. Report your results promptly.</p><strong>Accounts &amp; payments</strong><code>Leave this code.</code>']);
        $catalog = app(TranslationCatalogService::class);
        foreach (['Keep your account safe.' => 'Protégez votre compte.', 'Report your results promptly.' => 'Signalez vos résultats rapidement.', 'Accounts & payments' => 'Comptes et paiements'] as $key => $translation) {
            $catalog->saveTranslation($key, 'fr', $translation);
        }
        $catalog->exportJsonFiles();
        $rules = $tournament->getAttribute('rules');
        Route::middleware('web')->get('/test-competition-copy', fn () => response($rules));
        Route::middleware('web')->get('/livewire/test-competition-copy', fn () => response()->json(['components' => [['effects' => ['html' => $rules]]]]));
        $expected = '<p>Protégez votre compte. Signalez vos résultats rapidement.</p><strong>Comptes et paiements</strong><code>Leave this code.</code>';
        $this->withSession(['locale' => 'fr'])->get('/test-competition-copy')->assertOk()->assertSee($expected, false);
        $this->withSession(['locale' => 'fr'])->get('/livewire/test-competition-copy')->assertOk()->assertJsonPath('components.0.effects.html', $expected);
        $this->assertDatabaseMissing('translation_strings', ['key' => 'Leave this code.']);
    }

    public function test_very_long_sentences_fit_the_indexed_key_and_reconstruct_the_original_text(): void
    {
        $text = str_repeat('Long competition instruction ', 90).'ends here.';
        $segments = app(ContentTranslationSegments::class)->split($text);
        $this->assertSame($text, implode('', $segments));
        $this->assertGreaterThan(1, count($segments));
        foreach ($segments as $segment) {
            $this->assertLessThanOrEqual(500, mb_strlen($segment));
        }
        $this->tournament(['description' => $text]);
        foreach (TranslationString::query()->where('locale', 'en')->pluck('key') as $key) {
            $this->assertLessThanOrEqual(500, mb_strlen($key));
        }
        $catalog = app(TranslationCatalogService::class);
        foreach (array_unique(array_map('trim', $segments)) as $key) {
            if ($key !== '') {
                $catalog->saveTranslation($key, 'fr', 'Translated segment');
            }
        }
        $catalog->exportJsonFiles();
        Route::middleware('web')->get('/test-long-copy', fn () => '<p>'.$text.'</p>');
        $this->withSession(['locale' => 'fr'])->get('/test-long-copy')->assertOk()
            ->assertSee('Translated segment')->assertDontSee('Long competition instruction');
    }

    public function test_existing_whole_paragraph_translations_continue_to_render(): void
    {
        File::put(lang_path('fr.json'), json_encode(['Keep your account safe. Report your results promptly.' => 'Traduction complète existante.']));
        Route::middleware('web')->get('/test-existing-copy', fn () => '<p>Keep your account safe. Report your results promptly.</p>');
        $this->withSession(['locale' => 'fr'])->get('/test-existing-copy')->assertOk()->assertSee('<p>Traduction complète existante.</p>', false);
    }
}
