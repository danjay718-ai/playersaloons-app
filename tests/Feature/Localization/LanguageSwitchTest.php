<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Modules\Identity\Models\User;
use App\Modules\Localization\Services\LanguageRegistry;
use App\Modules\Operations\Models\SystemSetting;
use App\Shared\Enums\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LanguageSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_language_is_hidden_rejected_and_falls_back_for_existing_users(): void
    {
        SystemSetting::query()->create(['key' => 'language.disabled.fr', 'value' => '1']);
        $user = User::factory()->create(['locale' => 'fr']);
        Route::middleware('web')->get('/test-language-availability', fn () => response(app()->getLocale()));
        $this->actingAs($user)->withSession(['locale' => 'fr'])
            ->get('/test-language-availability')->assertOk()->assertContent('en')->assertSessionHas('locale', 'en');
        $this->post(route('language.update'), ['locale' => 'fr'])->assertSessionHasErrors('locale');
        self::assertSame('fr', $user->refresh()->locale);
        $html = Blade::render('<x-localization.language-switcher variant="player" />');
        self::assertStringNotContainsString('value="fr"', $html);
        self::assertStringContainsString('value="en"', $html);
        self::assertArrayHasKey('fr', app(LanguageRegistry::class)->supported());
    }

    public function test_guest_and_disabled_configured_default_fall_back_to_english(): void
    {
        SystemSetting::query()->create(['key' => 'language.disabled.fr', 'value' => '1']);
        config(['localization.default' => 'fr']);
        Route::middleware('web')->get('/test-guest-language-availability', fn () => response(app()->getLocale()));
        $this->withSession(['locale' => 'fr'])->get('/test-guest-language-availability')
            ->assertOk()->assertContent('en')->assertSessionHas('locale', 'en');
    }

    public function test_guest_locale_from_session_translates_rendered_html(): void
    {
        $this->withSession(['locale' => 'es'])
            ->get('/login')
            ->assertOk()
            ->assertSee('Iniciar sesión');
    }

    public function test_language_update_persists_for_authenticated_user(): void
    {
        $user = User::query()->create([
            'uuid' => Str::uuid()->toString(),
            'email' => 'locale-user@example.com',
            'username' => 'locale_user',
            'password' => bcrypt('Password@1234!'),
            'email_verified_at' => now(),
            'status' => UserStatus::ACTIVE,
        ]);

        $this->actingAs($user)
            ->from('/dashboard')
            ->post(route('language.update'), ['locale' => 'fr'])
            ->assertRedirect('/dashboard')
            ->assertSessionHas('locale', 'fr');

        $this->assertSame('fr', $user->refresh()->locale);
    }
}
