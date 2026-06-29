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
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class TranslationAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlatformSystemUserSeeder::class);
        $this->seed(SystemSettingsSeeder::class);

        $this->admin = $this->createUserWithRole('ADMIN', 'translations-admin@example.com');
    }

    public function test_admin_can_open_translation_manager_and_sync_json_keys(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/translations')
            ->assertOk()
            ->assertSee('Translation Manager');

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
