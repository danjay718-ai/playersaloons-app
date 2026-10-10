<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Modules\Identity\Models\User;
use App\Modules\Localization\Models\TranslationString;
use App\Modules\Localization\Services\InterfaceTranslationCatalog;
use App\Modules\Localization\Services\TranslationCatalogService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InterfaceTranslationCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_extraction_captures_labels_attributes_php_and_browser_copy_without_executing_player_data(): void
    {
        $source = <<<'BLADE'
<h2>Your Active Tournaments</h2>
<button title="Open Submit Result">Find Tournaments</button>
<input placeholder="Search players...">
<x-player.metric-card label="Prize Earnings" :value="$privateTotal" />
@php($tabs = [['label' => 'My Active', 'class' => 'bg-zinc-900 text-white']])
<span x-text="editing ? 'Cancel' : 'Edit Profile'"></span>
<p>{{ __('Welcome, :name', ['name' => $user->private_name]) }}</p>
<p>{{ __('recently') }} {{ __('$:amount Stake', ['amount' => $privateAmount]) }}</p>
<span translate="no">Private copy</span>
<script>function showMessage() { return 'Loading channel...'; }</script>
BLADE;
        $phrases = app(InterfaceTranslationCatalog::class)->extract($source, true);
        foreach (['Your Active Tournaments', 'Find Tournaments', 'Open Submit Result', 'Search players...', 'Prize Earnings', 'My Active', 'Cancel', 'Edit Profile', 'Welcome, :name', 'Loading channel...', 'recently', '$:amount Stake'] as $phrase) {
            self::assertContains($phrase, $phrases);
        }
        foreach (['Private copy', 'bg-zinc-900 text-white', 'private_name', 'function showMessage()', 'player.metric-card'] as $technical) {
            self::assertNotContains($technical, $phrases);
        }
    }

    public function test_sync_english_discovers_current_player_ui_and_exports_it_without_other_language_placeholders(): void
    {
        $catalog = app(TranslationCatalogService::class);
        $catalog->syncFromJsonFiles();
        foreach (['Your Active Tournaments', 'Find Tournaments', 'Active Events', 'Prize Earnings', 'Edit Profile', 'Strong password', 'Open Match Room', 'Wallet', 'Registration open', 'My Tournaments'] as $phrase) {
            self::assertDatabaseHas('translation_strings', ['key' => $phrase, 'locale' => 'en', 'text' => $phrase]);
        }
        $firstCount = TranslationString::query()->count();
        $catalog->saveTranslation('Your Active Tournaments', 'fr', 'Vos tournois actifs');
        $catalog->syncFromJsonFiles();
        self::assertSame($firstCount + 1, TranslationString::query()->count());
        self::assertDatabaseHas('translation_strings', ['key' => 'Your Active Tournaments', 'locale' => 'fr', 'text' => 'Vos tournois actifs']);
        self::assertSame(0, TranslationString::query()->where('locale', '!=', 'en')->whereNull('text')->count());
        $keys = array_column($catalog->englishExport()['entries'], 'key');
        self::assertContains('Your Active Tournaments', $keys);
        self::assertContains('Edit Profile', $keys);
        self::assertContains('Upload failed. Please try again.', $keys);
    }

    public function test_browser_button_labels_receive_a_localized_dictionary(): void
    {
        $catalog = app(TranslationCatalogService::class);
        $catalog->syncFromJsonFiles();
        $catalog->saveTranslation('Install', 'fr', 'Installer');
        $catalog->saveTranslation('Upload failed. Please try again.', 'fr', 'Échec du téléversement. Réessayez.');
        app()->setLocale('fr');
        $html = view('components.localization.interface-messages')->render();
        preg_match('#<script[^>]*>(.*?)</script>#s', $html, $match);
        $messages = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Installer', $messages['Install']);
        self::assertSame('Échec du téléversement. Réessayez.', $messages['Upload failed. Please try again.']);
    }

    public function test_player_dashboard_buttons_cards_and_counts_use_importable_ui_keys(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $player = User::factory()->create(['locale' => 'fr', 'username' => 'private_player_name']);
        $player->assignRole('PLAYER');
        $catalog = app(TranslationCatalogService::class);
        $catalog->syncFromJsonFiles();
        foreach ([
            'Your Active Tournaments' => 'Vos tournois actifs',
            'Find Tournaments' => 'Trouver des tournois',
            'Active Events' => 'Événements actifs',
            'Prize Earnings' => 'Gains de prix',
            'Welcome, :name' => 'Bienvenue, :name',
            'LEVEL :value1' => 'NIVEAU :value1',
            ':value1 to next level' => ':value1 au niveau suivant',
        ] as $key => $text) {
            $catalog->saveTranslation($key, 'fr', $text);
        }
        $this->actingAs($player)->get('/dashboard')->assertOk()
            ->assertSee('Vos tournois actifs')->assertSee('Trouver des tournois')
            ->assertSee('Événements actifs')->assertSee('Gains de prix')
            ->assertSee('Bienvenue, private_player_name')->assertSee('NIVEAU 1');
        self::assertDatabaseMissing('translation_strings', ['key' => 'private_player_name']);
    }
}
