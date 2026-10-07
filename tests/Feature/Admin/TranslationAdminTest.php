<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\TranslationAdmin;
use App\Modules\Identity\Models\User;
use App\Modules\Localization\Models\TranslationString;
use App\Modules\Wallet\Models\Wallet;
use App\Shared\Enums\UserStatus;
use App\Shared\Enums\WalletStatus;
use Database\Seeders\PlatformSystemUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class TranslationAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $temporaryLangPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryLangPath = sys_get_temp_dir().'/translations-'.Str::uuid();
        File::makeDirectory($this->temporaryLangPath);
        foreach (File::glob(lang_path('*.json')) as $file) {
            File::copy($file, $this->temporaryLangPath.'/'.basename($file));
        }
        $this->app->useLangPath($this->temporaryLangPath);

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlatformSystemUserSeeder::class);
        $this->seed(SystemSettingsSeeder::class);

        $this->admin = $this->createUserWithRole('ADMIN', 'translations-admin@example.com');
    }

    public function test_translation_manager_starts_empty_and_syncs_only_when_requested(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/translations')
            ->assertOk()
            ->assertSee('Translation Manager');

        $this->assertDatabaseCount('translation_strings', 0);

        Livewire::actingAs($this->admin)
            ->test(TranslationAdmin::class)
            ->call('syncFromJson')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('translation_strings', [
            'key' => 'Dashboard',
            'locale' => 'en',
            'text' => 'Dashboard',
        ]);
    }

    public function test_translation_manager_can_filter_missing_locale_rows(): void
    {
        TranslationString::query()->create([
            'key' => 'Needs Translation',
            'locale' => 'en',
            'text' => 'Needs Translation',
        ]);
        TranslationString::query()->create([
            'key' => 'Needs Translation',
            'locale' => 'fr',
            'text' => null,
        ]);

        Livewire::actingAs($this->admin)
            ->test(TranslationAdmin::class)
            ->set('localeFilter', 'fr')
            ->set('missingOnly', true)
            ->assertSee('Needs Translation');
    }

    public function test_language_tabs_show_only_english_and_the_selected_translation(): void
    {
        foreach (['en' => 'Greeting source', 'fr' => 'Bonjour unique', 'es' => 'Hola unique'] as $locale => $text) {
            TranslationString::query()->create(['key' => 'Greeting source', 'locale' => $locale, 'text' => $text]);
        }

        Livewire::actingAs($this->admin)->test(TranslationAdmin::class)
            ->set('localeFilter', 'fr')
            ->assertSee('Greeting source')
            ->assertSee('Bonjour unique')
            ->assertDontSee('Hola unique')
            ->set('localeFilter', 'es')
            ->assertSee('Hola unique')
            ->assertDontSee('Bonjour unique');
    }

    public function test_saving_selected_language_preserves_other_translations(): void
    {
        foreach (['en' => 'Greeting source', 'fr' => 'Bonjour unique', 'es' => 'Hola unique'] as $locale => $text) {
            TranslationString::query()->create(['key' => 'Greeting source', 'locale' => $locale, 'text' => $text]);
        }

        Livewire::actingAs($this->admin)->test(TranslationAdmin::class)
            ->set('localeFilter', 'fr')
            ->call('editKey', 'Greeting source')
            ->set('values.fr', 'Salut unique')
            ->call('saveKey')
            ->assertHasNoErrors();

        foreach (['en' => 'Greeting source', 'fr' => 'Salut unique', 'es' => 'Hola unique'] as $locale => $text) {
            $this->assertDatabaseHas('translation_strings', ['key' => 'Greeting source', 'locale' => $locale, 'text' => $text]);
        }
        $this->assertSame('Salut unique', json_decode(File::get(lang_path('fr.json')), true)['Greeting source']);
    }

    public function test_missing_filter_includes_absent_rows_and_searches_selected_language(): void
    {
        foreach (['Translated source', 'Absent source', 'Empty source'] as $key) {
            TranslationString::query()->create(['key' => $key, 'locale' => 'en', 'text' => $key]);
        }
        TranslationString::query()->create(['key' => 'Translated source', 'locale' => 'fr', 'text' => 'Bonjour unique']);
        TranslationString::query()->create(['key' => 'Empty source', 'locale' => 'fr', 'text' => '']);

        Livewire::actingAs($this->admin)->test(TranslationAdmin::class)
            ->set('localeFilter', 'fr')
            ->set('search', 'Bonjour unique')
            ->assertSee('Translated source')
            ->assertDontSee('Absent source')
            ->set('search', '')
            ->set('missingOnly', true)
            ->assertSee('Absent source')
            ->assertSee('Empty source')
            ->assertDontSee('Translated source');
    }

    public function test_english_source_stays_english_when_admin_interface_is_french(): void
    {
        TranslationString::query()->create(['key' => 'Greeting source', 'locale' => 'en', 'text' => 'Greeting source']);
        File::put(lang_path('fr.json'), json_encode(['Greeting source' => 'Bonjour unique']));
        $this->app->forgetInstance('translation.loader');
        $this->app->forgetInstance('translator');
        $this->admin->update(['locale' => 'fr']);

        $this->actingAs($this->admin)->get('/admin/translations')->assertOk()
            ->assertSee('<p translate="no" class="font-semibold text-slate-200">Greeting source</p>', false);
    }

    public function test_unsupported_language_is_rejected(): void
    {
        Livewire::actingAs($this->admin)->test(TranslationAdmin::class)
            ->set('localeFilter', 'invalid')->assertStatus(422);
    }

    public function test_languages_can_be_hidden_and_restored_without_removing_translations(): void
    {
        TranslationString::query()->create(['key' => 'Greeting source', 'locale' => 'fr', 'text' => 'Bonjour unique']);
        Livewire::actingAs($this->admin)->test(TranslationAdmin::class)
            ->assertSee('data-translation-language="fr"', false)
            ->call('toggleLanguage', 'fr')
            ->assertSet('hiddenLanguages', ['fr'])
            ->assertDontSee('data-translation-language="fr"', false)
            ->assertSee('data-translation-language="en"', false)
            ->call('toggleLanguage', 'fr')
            ->assertSee('data-translation-language="fr"', false);
        $this->assertDatabaseHas('translation_strings', ['key' => 'Greeting source', 'locale' => 'fr', 'text' => 'Bonjour unique']);
        $this->assertArrayHasKey('fr', config('localization.supported'));
    }

    public function test_hiding_selected_language_closes_editor_and_returns_to_english(): void
    {
        TranslationString::query()->create(['key' => 'Greeting source', 'locale' => 'en', 'text' => 'Greeting source']);
        Livewire::actingAs($this->admin)->test(TranslationAdmin::class)
            ->set('localeFilter', 'fr')
            ->call('editKey', 'Greeting source')
            ->assertSet('showEditModal', true)
            ->call('toggleLanguage', 'fr')
            ->assertSet('localeFilter', 'en')
            ->assertSet('showEditModal', false)
            ->assertSet('editingKey', '')
            ->assertSet('values', []);
    }

    public function test_hidden_languages_are_remembered_for_the_admin_session_and_can_all_be_restored(): void
    {
        Livewire::actingAs($this->admin)->test(TranslationAdmin::class)
            ->call('toggleLanguage', 'fr')
            ->call('toggleLanguage', 'es');
        Livewire::actingAs($this->admin)->test(TranslationAdmin::class)
            ->assertSet('hiddenLanguages', ['fr', 'es'])
            ->assertDontSee('data-translation-language="fr"', false)
            ->call('showAllLanguages')
            ->assertSet('hiddenLanguages', [])
            ->assertSee('data-translation-language="fr"', false)
            ->assertSee('data-translation-language="es"', false);
        Livewire::actingAs($this->admin)->test(TranslationAdmin::class)->assertSet('hiddenLanguages', []);
    }

    public function test_language_visibility_preferences_are_isolated_between_admins(): void
    {
        Livewire::actingAs($this->admin)->test(TranslationAdmin::class)->call('toggleLanguage', 'fr');
        $otherAdmin = $this->createUserWithRole('ADMIN', 'other-translations-admin@example.com');
        Livewire::actingAs($otherAdmin)->test(TranslationAdmin::class)
            ->assertSet('hiddenLanguages', [])
            ->assertSee('data-translation-language="fr"', false);
    }

    public function test_invalid_languages_and_hiding_english_are_rejected(): void
    {
        Livewire::actingAs($this->admin)->test(TranslationAdmin::class)->call('toggleLanguage', 'invalid')->assertStatus(422);
        Livewire::actingAs($this->admin)->test(TranslationAdmin::class)->call('toggleLanguage', 'en')->assertStatus(422);
        Livewire::actingAs($this->admin)->test(TranslationAdmin::class)
            ->call('toggleLanguage', 'fr')
            ->set('localeFilter', 'fr')->assertStatus(422);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryLangPath);
        parent::tearDown();
    }

    private function createUserWithRole(string $role, string $email): User
    {
        /** @var User $user */
        $user = User::query()->create([
            'uuid' => Str::uuid()->toString(),
            'email' => $email,
            'username' => explode('@', $email)[0],
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'status' => UserStatus::ACTIVE,
        ]);

        $user->assignRole($role);

        Wallet::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'cached_balance' => '100.00',
            'status' => WalletStatus::ACTIVE,
        ]);

        return $user;
    }
}
