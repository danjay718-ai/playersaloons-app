<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Livewire\Admin\RolePermissionAdmin;
use App\Livewire\Admin\TranslationAdmin;
use App\Modules\Identity\Models\User;
use App\Modules\Localization\Actions\ImportTranslationsAction;
use App\Modules\Localization\Actions\ResetTranslationsAction;
use App\Modules\Localization\Models\TranslationString;
use App\Modules\Localization\Services\TranslationCatalogService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class ResetTranslationsTest extends TestCase
{
    use RefreshDatabase;

    private User $super;

    private User $admin;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->super = $this->user('SUPER_ADMIN');
        $this->admin = $this->user('ADMIN');
        $this->directory = sys_get_temp_dir().'/translation-reset-'.Str::uuid();
        File::makeDirectory($this->directory);
        File::put($this->directory.'/en.json', json_encode(['Hello' => 'Hello', 'Legacy phrase' => 'Legacy phrase']));
        File::put($this->directory.'/fr.json', json_encode(['Hello' => 'Old Bonjour', 'Legacy phrase' => 'Ancienne phrase']));
        $this->app->useLangPath($this->directory);
        $this->app->forgetInstance('translation.loader');
        $this->app->forgetInstance('translator');
        app(TranslationCatalogService::class)->syncContent(['Hello', 'Legacy phrase']);
        TranslationString::query()->create(['key' => 'Hello', 'locale' => 'fr', 'text' => 'Bonjour']);
        TranslationString::query()->create(['key' => 'Hello', 'locale' => 'de', 'text' => 'Hallo']);
        $deleted = TranslationString::query()->create(['key' => 'Deleted phrase', 'locale' => 'fr', 'text' => 'Recoverable']);
        $deleted->delete();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function user(string $role): User
    {
        $user = User::query()->create([
            'uuid' => Str::uuid(), 'email' => strtolower($role).'@reset.example',
            'username' => strtolower($role), 'password' => bcrypt('password'), 'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_single_language_reset_clears_stored_and_bundled_translations_and_can_be_imported_again(): void
    {
        $catalog = app(TranslationCatalogService::class);
        $export = $catalog->englishExport();
        app()->setLocale('fr');
        self::assertSame('Bonjour', __('Hello'));
        self::assertSame('Ancienne phrase', __('Legacy phrase'));
        self::assertSame(2, app(ResetTranslationsAction::class)->execute($this->super, 'fr', 'FR'));
        self::assertSame('Hello', __('Hello'));
        self::assertSame('Legacy phrase', __('Legacy phrase'));
        self::assertDatabaseMissing('translation_strings', ['locale' => 'fr']);
        self::assertDatabaseHas('translation_strings', ['locale' => 'de', 'text' => 'Hallo']);
        self::assertSame($export, $catalog->englishExport());
        self::assertArrayHasKey('fr', $catalog->supportedLanguages());
        self::assertDatabaseHas('activity_log', ['description' => 'translations_reset', 'causer_id' => $this->super->id]);
        foreach ($export['entries'] as &$entry) {
            $entry['translation'] = 'New '.$entry['source'];
        }
        unset($entry);
        app(ImportTranslationsAction::class)->execute($this->super, 'fr', json_encode($export));
        self::assertSame('New Hello', __('Hello'));
        self::assertSame('New Legacy phrase', __('Legacy phrase'));
    }

    public function test_reset_all_preserves_english_and_all_language_definitions(): void
    {
        $before = app(TranslationCatalogService::class)->englishExport();
        Livewire::actingAs($this->super)->test(TranslationAdmin::class)
            ->assertSee('data-testid="reset-translations-button"', false)
            ->assertSee('All translations in the selected language(s) will be permanently removed. This cannot be undone.')
            ->set('resetLocale', 'all')->set('resetConfirmation', 'RESET ALL')
            ->call('resetTranslations')->assertHasNoErrors()->assertDispatched('translation-reset-completed')
            ->assertSet('resetConfirmation', '');
        self::assertSame(0, DB::table('translation_strings')->where('locale', '!=', 'en')->count());
        self::assertSame($before, app(TranslationCatalogService::class)->englishExport());
        self::assertSame(10, DB::table('translation_language_resets')->count());
    }

    public function test_reset_permission_is_hidden_by_default_and_can_be_granted_or_revoked_in_role_management(): void
    {
        Livewire::actingAs($this->admin)->test(TranslationAdmin::class)->assertDontSee('data-testid="reset-translations-button"', false);
        $role = Role::findByName('ADMIN');
        Livewire::actingAs($this->super)->test(RolePermissionAdmin::class)
            ->call('setActiveRole', $role->id)->assertSee('translations.reset')
            ->call('togglePermission', 'translations.reset')->assertHasNoErrors();
        Livewire::actingAs($this->admin->fresh())->test(TranslationAdmin::class)->assertSee('data-testid="reset-translations-button"', false);
        $role->revokePermissionTo('translations.reset');
        Livewire::actingAs($this->admin->fresh())->test(TranslationAdmin::class)
            ->assertDontSee('data-testid="reset-translations-button"', false)
            ->set('resetLocale', 'fr')->set('resetConfirmation', 'FR')->call('resetTranslations')->assertStatus(403);
        self::assertDatabaseHas('translation_strings', ['locale' => 'fr', 'text' => 'Bonjour']);
    }

    public function test_reset_permission_does_not_require_translation_management_permission(): void
    {
        $this->admin->givePermissionTo('translations.reset');
        Role::findByName('ADMIN')->revokePermissionTo('translations.manage');
        Livewire::actingAs($this->admin->fresh())->test(TranslationAdmin::class)
            ->assertSee('data-testid="reset-translations-button"', false)
            ->set('resetLocale', 'fr')->set('resetConfirmation', 'FR')->call('resetTranslations')->assertHasNoErrors();
        self::assertDatabaseMissing('translation_strings', ['locale' => 'fr']);
    }

    public function test_staff_reset_permission_changes_survive_deployment_seeding(): void
    {
        $role = Role::findByName('ADMIN');
        $role->givePermissionTo('translations.reset');
        $this->seed(RolesAndPermissionsSeeder::class);
        self::assertTrue(Role::findByName('ADMIN')->hasPermissionTo('translations.reset'));
        Role::findByName('ADMIN')->revokePermissionTo('translations.reset');
        $this->seed(RolesAndPermissionsSeeder::class);
        self::assertFalse(Role::findByName('ADMIN')->hasPermissionTo('translations.reset'));
    }

    public function test_super_admin_keeps_the_reset_button_without_an_explicit_permission_assignment(): void
    {
        Role::findByName('SUPER_ADMIN')->syncPermissions([]);
        Livewire::actingAs($this->super->fresh())->test(TranslationAdmin::class)
            ->assertSee('data-testid="reset-translations-button"', false);
        self::assertSame(2, app(ResetTranslationsAction::class)->execute($this->super->fresh(), 'fr', 'FR'));
    }

    public function test_reset_requires_confirmation_and_never_accepts_english_or_unknown_languages(): void
    {
        try {
            app(ResetTranslationsAction::class)->execute($this->super, 'all', '');
            self::fail('Reset without confirmation was accepted.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('resetConfirmation', $exception->errors());
        }
        foreach (['en', 'invalid'] as $locale) {
            try {
                app(ResetTranslationsAction::class)->execute($this->super, $locale, strtoupper($locale));
                self::fail('Invalid language reset was accepted.');
            } catch (HttpException $exception) {
                self::assertSame(422, $exception->getStatusCode());
            }
        }
        self::assertDatabaseHas('translation_strings', ['locale' => 'fr', 'text' => 'Bonjour']);
        self::assertDatabaseHas('translation_strings', ['locale' => 'en', 'key' => 'Hello']);
        self::assertSame(0, DB::table('translation_language_resets')->count());
    }

    public function test_pending_reset_migration_returns_validation_message_without_deleting_translations(): void
    {
        Schema::drop('translation_language_resets');
        Livewire::actingAs($this->super)->test(TranslationAdmin::class)
            ->assertSet('resetTableReady', false)
            ->assertSee('data-testid="reset-translations-button"', false)
            ->set('resetLocale', 'all')->set('resetConfirmation', 'RESET ALL')
            ->call('resetTranslations')->assertHasErrors('resetConfirmation')
            ->assertSee('Reset is unavailable until the application update is complete. No translations were changed.');
        self::assertDatabaseHas('translation_strings', ['locale' => 'fr', 'text' => 'Bonjour']);
        self::assertDatabaseHas('translation_strings', ['locale' => 'de', 'text' => 'Hallo']);
        self::assertDatabaseHas('translation_strings', ['locale' => 'en', 'key' => 'Hello']);
    }
}
