<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\TranslationAdmin;
use App\Modules\Identity\Models\User;
use App\Modules\Localization\Models\TranslationString;
use App\Modules\Localization\Services\LanguageRegistry;
use App\Modules\Wallet\Models\Wallet;
use App\Shared\Enums\UserStatus;
use App\Shared\Enums\WalletStatus;
use Database\Seeders\PlatformSystemUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
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

    public function test_language_availability_is_global_and_keeps_translations_editable(): void
    {
        $registry = app(LanguageRegistry::class);
        self::assertArrayHasKey('fr', $registry->available());
        TranslationString::query()->create(['key' => 'Hello', 'locale' => 'fr', 'text' => 'Bonjour']);
        $component = Livewire::actingAs($this->admin)->test(TranslationAdmin::class)
            ->call('setLanguageAvailability', 'fr', false)->assertHasNoErrors();
        self::assertArrayNotHasKey('fr', $registry->available());
        self::assertArrayHasKey('fr', $registry->supported());
        self::assertDatabaseHas('translation_strings', ['locale' => 'fr', 'text' => 'Bonjour']);
        $component->set('localeFilter', 'fr')->assertSet('localeFilter', 'fr')
            ->call('setLanguageAvailability', 'fr', true)->assertHasNoErrors();
        self::assertArrayHasKey('fr', $registry->available());
    }

    public function test_english_cannot_be_disabled(): void
    {
        Livewire::actingAs($this->admin)->test(TranslationAdmin::class)
            ->call('setLanguageAvailability', 'en', false)->assertStatus(422);
        self::assertArrayHasKey('en', app(LanguageRegistry::class)->available());
    }

    public function test_view_only_admin_cannot_change_language_availability(): void
    {
        $role = Role::findOrCreate('TRANSLATION_REVIEWER', 'web');
        $role->givePermissionTo('translations.view');
        $this->admin->syncRoles([$role]);
        Livewire::actingAs($this->admin)->test(TranslationAdmin::class)
            ->assertDontSee('data-player-language=', false)
            ->call('setLanguageAvailability', 'fr', false)->assertForbidden();
        self::assertArrayHasKey('fr', app(LanguageRegistry::class)->available());
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
        app()->setLocale('fr');
        $this->assertSame('Salut unique', __('Greeting source'));
        $this->assertArrayNotHasKey('Greeting source', json_decode(File::get(lang_path('fr.json')), true));
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

    public function test_language_visibility_controls_use_client_state_without_livewire_requests(): void
    {
        Livewire::actingAs($this->admin)->test(TranslationAdmin::class)
            ->assertSee('translationLanguageVisibility(', false)
            ->assertSee('@click="toggleLanguage(', false)
            ->assertSee('@click="showAllLanguages()"', false)
            ->assertDontSee('wire:click="toggleLanguage', false)
            ->assertDontSee('wire:click="showAllLanguages', false)
            ->assertDontSee('delete-translation-', false);
    }

    public function test_admin_can_add_a_language_without_creating_placeholder_rows(): void
    {
        TranslationString::query()->create(['key' => 'Hello', 'locale' => 'en', 'text' => 'Hello']);
        Livewire::actingAs($this->admin)->test(TranslationAdmin::class)
            ->set('code', 'fil')->set('english', 'Filipino')->set('native', 'Filipino')->set('flag', 'ph')
            ->call('addLanguage')->assertHasNoErrors()
            ->assertDispatched('translation-language-added', locale: 'fil')
            ->assertSee('data-translation-language="fil"', false);
        self::assertDatabaseHas('translation_languages', ['code' => 'fil', 'english' => 'Filipino', 'flag' => 'ph']);
        self::assertSame('ph', app(LanguageRegistry::class)->supported()['fil']['flag']);
        $this->actingAs($this->admin)->get('/admin/translations')->assertOk()->assertSee('fi-ph', false);
        self::assertDatabaseMissing('translation_strings', ['locale' => 'fil']);
        $this->actingAs($this->admin)->post(route('language.update'), ['locale' => 'fil'])->assertSessionHas('locale', 'fil');
        self::assertSame('fil', $this->admin->fresh()->locale);
    }

    public function test_language_creation_handles_missing_setup_and_invalid_flags_without_a_server_error(): void
    {
        Livewire::actingAs($this->admin)->test(TranslationAdmin::class)
            ->set('code', 'fil')->set('english', 'Filipino')->set('native', 'Filipino')->set('flag', '../invalid')
            ->call('addLanguage')->assertHasErrors('flag');
        self::assertDatabaseMissing('translation_languages', ['code' => 'fil']);

        Schema::drop('translation_languages');
        Livewire::actingAs($this->admin)->test(TranslationAdmin::class)
            ->assertSet('languageTableReady', false)
            ->set('code', 'fil')->set('english', 'Filipino')->set('native', 'Filipino')->set('flag', 'ph')
            ->call('addLanguage')->assertHasErrors('code')
            ->assertSee('Adding languages is unavailable until the application update is complete. No language was added.');
    }

    public function test_flag_setup_is_required_and_native_name_can_default_to_the_language_name(): void
    {
        Livewire::actingAs($this->admin)->test(TranslationAdmin::class)
            ->set('code', 'fil')->set('english', 'Filipino')->set('flag', 'ph')
            ->call('addLanguage')->assertHasNoErrors()
            ->assertSet('flag', 'un');
        self::assertDatabaseHas('translation_languages', ['code' => 'fil', 'native' => 'Filipino', 'flag' => 'ph']);
        Schema::table('translation_languages', fn ($table) => $table->dropColumn('flag'));
        Livewire::actingAs($this->admin)->test(TranslationAdmin::class)
            ->assertSet('languageTableReady', false)
            ->set('code', 'hi')->set('english', 'Hindi')
            ->call('addLanguage')->assertHasErrors('code');
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
