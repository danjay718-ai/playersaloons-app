<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\TranslationAdmin;
use App\Livewire\Auth\Login;
use App\Modules\CMS\Services\LandingPageContentService;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Operations\Services\AdminDeletionService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\FreshInstallationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class FreshInstallationSeederTest extends TestCase
{
    use RefreshDatabase;

    private function productionCredentials(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config([
            'bootstrap.super_admin.email' => 'owner@example.com',
            'bootstrap.super_admin.password' => 'Owner!Secure2026Password',
            'bootstrap.admin.email' => 'operations@example.com',
            'bootstrap.admin.password' => 'Operations!Secure2026Password',
        ]);
    }

    private function seedProduction(string $class): void
    {
        $this->artisan('db:seed', ['--class' => $class, '--force' => true])->run();
    }

    public function test_default_seeder_creates_only_approved_core_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('roles', 9);
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('user_profiles', 2);
        $this->assertDatabaseCount('wallets', 3);
        $this->assertSame(0.0, (float) DB::table('wallets')->sum('cached_balance'));
        $this->assertDatabaseCount('landing_sections', 8);
        $this->assertDatabaseCount('public_navigation_items', 4);
        $this->assertDatabaseCount('policy_pages', 5);

        $seededTables = [
            'migrations', 'roles', 'permissions', 'role_has_permissions', 'model_has_roles',
            'users', 'user_profiles', 'wallets', 'landing_sections', 'landing_section_items',
            'public_navigation_items', 'policy_pages', 'system_settings',
        ];
        foreach (Schema::getTables() as $table) {
            if (! in_array($table['name'], $seededTables, true) && ! str_starts_with($table['name'], 'sqlite_')) {
                $this->assertDatabaseCount($table['name'], 0);
            }
        }
        $this->assertFalse(User::role('PLAYER')->exists());
        $this->assertFalse(DB::table('landing_section_items')->whereIn('item_key', ['review-1', 'review-2', 'review-3'])->exists());
        $stats = app(LandingPageContentService::class)->data()['stats'];
        $this->assertSame('0', collect($stats)->firstWhere('key', 'active_players')['value']);
    }

    public function test_production_seeding_requires_supplied_credentials_before_writes(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['bootstrap.super_admin.email' => null, 'bootstrap.super_admin.password' => null, 'bootstrap.admin.email' => null, 'bootstrap.admin.password' => null]);
        try {
            $this->seedProduction(FreshInstallationSeeder::class);
            $this->fail('Production must require bootstrap secrets.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('accounts.0.password', $exception->errors());
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('roles', 0);
        $this->assertDatabaseCount('landing_sections', 0);
    }

    public function test_production_bootstrap_uses_configured_credentials_and_latest_permissions(): void
    {
        $this->productionCredentials();
        $this->seedProduction(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('Owner!Secure2026Password', $owner->password));
        $this->assertTrue($owner->hasRole('SUPER_ADMIN'));
        $this->assertDatabaseMissing('users', ['email' => 'admin@playersaloons.com']);
        $role = Role::findByName('SUPER_ADMIN');
        foreach (array_unique(array_merge(array_column(AdminDeletionService::RESOURCES, 1), ['games.restore', 'games.force_delete', 'audit_logs.manage'])) as $permission) {
            $this->assertTrue($role->hasPermissionTo($permission), $permission);
        }
        Livewire::test(Login::class)
            ->set('identity', 'owner@example.com')
            ->set('password', 'Owner!Secure2026Password')
            ->call('login')
            ->assertRedirect('/admin');
        $this->actingAs($owner)->get('/admin/system-settings')->assertOk()->assertDontSee('Delete all tournament test data');
    }

    public function test_production_rejects_known_demo_passwords_and_demo_seeder(): void
    {
        $this->productionCredentials();
        config(['bootstrap.super_admin.password' => 'Admin@1234!']);
        try {
            $this->seedProduction(FreshInstallationSeeder::class);
            $this->fail('Demo credentials must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('accounts.0.password', $exception->errors());
        }
        $this->assertDatabaseCount('users', 0);
        $this->expectException(\LogicException::class);
        $this->seedProduction(DemoSeeder::class);
    }

    public function test_reseeding_preserves_staff_password_balance_and_edited_settings(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $admin->update(['password' => Hash::make('Changed!Password2026')]);
        $admin->wallet->update(['cached_balance' => '17.00']);
        DB::table('system_settings')->where('key', 'platform.commission_percentage')->update(['value' => '7.50']);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('wallets', 3);
        $this->assertTrue(Hash::check('Changed!Password2026', $admin->fresh()->password));
        $this->assertSame('17.00', $admin->fresh()->wallet->cached_balance);
        $this->assertDatabaseHas('system_settings', ['key' => 'platform.commission_percentage', 'value' => '7.50']);
    }

    public function test_core_pages_render_without_populating_empty_translation_catalog(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('username', 'superadmin')->firstOrFail();
        $this->get('/')->assertOk();
        $this->get('/policies/terms-and-conditions')->assertOk();
        Livewire::actingAs($admin)->test(TranslationAdmin::class)->assertSee('Translation Manager');
        $this->assertDatabaseCount('translation_strings', 0);
        $this->assertDatabaseHas('system_settings', ['key' => 'language_switcher.show_guest', 'value' => 'false']);
        $this->assertDatabaseHas('system_settings', ['key' => 'tournament.timezone', 'value' => config('app.tournament_timezone', 'UTC')]);
    }
}
