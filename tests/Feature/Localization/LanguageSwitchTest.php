<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LanguageSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_locale_from_session_translates_rendered_html(): void
    {
        $this->withSession(['locale' => 'es'])
            ->get('/login')
            ->assertOk()
            ->assertSee('Iniciar sesion');
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
