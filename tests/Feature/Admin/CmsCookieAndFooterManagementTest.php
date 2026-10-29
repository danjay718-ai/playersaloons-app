<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\CmsAdmin;
use App\Modules\CMS\Models\LandingSection;
use App\Modules\Identity\Models\User;
use App\Modules\Operations\Models\SystemSetting;
use App\Shared\Enums\UserStatus;
use Database\Seeders\LandingPageSeeder;
use Database\Seeders\PlatformSystemUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CmsCookieAndFooterManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlatformSystemUserSeeder::class);
        $this->seed(SystemSettingsSeeder::class);
        $this->seed(LandingPageSeeder::class);

        /** @var User $admin */
        $admin = User::query()->create([
            'uuid' => Str::uuid()->toString(),
            'email' => 'admin@gamersrival.com',
            'username' => 'superadmin',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'status' => UserStatus::ACTIVE,
        ]);
        $admin->assignRole('ADMIN');

        $this->admin = $admin;
    }

    public function test_admin_can_view_cookie_management_tab_and_save_settings(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CmsAdmin::class, ['section' => 'cookies'])
            ->assertSet('tab', 'cookies')
            ->assertSee('Cookie')
            ->assertSee('Privacy Banner Management')
            ->set('cookieTitle', 'Custom Privacy Gateway')
            ->set('cookieMessage', 'We care about your privacy and telemetry.')
            ->set('cookiePolicyUrl', '/policies/privacy-policy')
            ->set('cookieConsentEnabled', true)
            ->set('cookieAnalyticsEnabled', true)
            ->set('cookieMarketingEnabled', true)
            ->call('saveCookieSettings')
            ->assertHasNoErrors()
            ->assertSee('Cookie management settings saved successfully.');

        $this->assertSame('Custom Privacy Gateway', SystemSetting::query()->where('key', 'cookie.title')->value('value'));
        $this->assertSame('We care about your privacy and telemetry.', SystemSetting::query()->where('key', 'cookie.message')->value('value'));
        $this->assertSame('/policies/privacy-policy', SystemSetting::query()->where('key', 'cookie.policy_url')->value('value'));
        $this->assertSame('1', SystemSetting::query()->where('key', 'cookie.consent_enabled')->value('value'));
    }

    public function test_admin_can_view_footer_management_tab_and_save_settings(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CmsAdmin::class, ['section' => 'footer'])
            ->assertSet('tab', 'footer')
            ->assertSee('Footer Content')
            ->assertSee('Layout Management')
            ->set('footerBrandTagline', 'Global Esports Arena')
            ->set('footerDescription', 'Custom arena description for all competitors.')
            ->set('footerCopyright', '2026 GamersRival Global Inc.')
            ->set('footerDisclaimer', 'All trademarks are property of their respective owners.')
            ->set('footerDiscord', 'https://discord.gg/custom')
            ->call('saveFooterSettings')
            ->assertHasNoErrors()
            ->assertSee('Footer content settings saved successfully.');

        $this->assertSame('Global Esports Arena', SystemSetting::query()->where('key', 'footer.tagline')->value('value'));
        $this->assertSame('2026 GamersRival Global Inc.', SystemSetting::query()->where('key', 'footer.copyright')->value('value'));
        $this->assertSame('https://discord.gg/custom', SystemSetting::query()->where('key', 'footer.discord')->value('value'));
    }

    public function test_footer_copyright_falls_back_to_section_then_default_when_settings_are_missing(): void
    {
        SystemSetting::query()->where('key', 'footer.copyright')->delete();
        $footer = LandingSection::query()->where('key', 'footer')->firstOrFail();
        $footer->update(['body' => 'Custom section copyright']);

        Livewire::actingAs($this->admin)->test(CmsAdmin::class, ['section' => 'footer'])
            ->assertSet('footerCopyright', 'Custom section copyright');

        $footer->delete();
        Livewire::actingAs($this->admin)->test(CmsAdmin::class, ['section' => 'footer'])
            ->assertSet('footerCopyright', 'All rights reserved. Operated by GamersRival Systems.');
    }

    public function test_cookie_consent_banner_appears_on_guest_landing(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'cookie.consent_enabled'],
            ['value' => '1']
        );
        SystemSetting::query()->updateOrCreate(
            ['key' => 'cookie.title'],
            ['value' => 'Arena Privacy Checkpoint']
        );

        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('Arena Privacy Checkpoint');
        $response->assertSee('Accept All');
        $response->assertSee('Essential Only');
    }

    public function test_cookie_consent_banner_is_hidden_when_disabled(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'cookie.consent_enabled'],
            ['value' => '0']
        );

        $response = $this->get('/');
        $response->assertOk();
        $response->assertDontSee('role="dialog"', false);
    }

    public function test_homepage_renders_faq_accordion(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('Frequently Asked Questions');
        $response->assertSee('How do tournament payouts work?');
        $response->assertSee('How do I submit match results?');
    }
}
