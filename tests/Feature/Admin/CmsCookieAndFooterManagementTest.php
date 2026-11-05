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
            ->set('footerCompanyDetails', "Updated Company Ltd\nCompany number: 12345678\nRegistered office: London")
            ->set('footerDisclaimer', 'All trademarks are property of their respective owners.')
            ->set('footerDiscord', 'https://discord.gg/custom')
            ->call('saveFooterSettings')
            ->assertHasNoErrors()
            ->assertSee('Footer content settings saved successfully.');

        $this->assertSame('Global Esports Arena', SystemSetting::query()->where('key', 'footer.tagline')->value('value'));
        $this->assertSame('2026 GamersRival Global Inc.', SystemSetting::query()->where('key', 'footer.copyright')->value('value'));
        $this->assertSame('https://discord.gg/custom', SystemSetting::query()->where('key', 'footer.discord')->value('value'));
        $this->assertSame("Updated Company Ltd\nCompany number: 12345678\nRegistered office: London", SystemSetting::query()->where('key', 'footer.company_details')->value('value'));

        Livewire::actingAs($this->admin)->test(CmsAdmin::class, ['section' => 'footer'])
            ->assertSet('footerCompanyDetails', "Updated Company Ltd\nCompany number: 12345678\nRegistered office: London");
    }

    public function test_company_details_appear_separately_in_every_footer_variant(): void
    {
        $this->actingAs($this->admin);

        foreach (['gr-footer', 'guest-footer', 'public-footer'] as $footer) {
            $this->view('components.layouts.partials.'.$footer)
                ->assertSee('Company Details')
                ->assertSee("Skillplay Ltd\nCompany number: 15920047\nIncorporated on: 27 August 2024\nRegistered office:\n167–169 Great Portland Street, 5th Floor\nLondon, W1W 5PF, England")
                ->assertSee('whitespace-pre-line');
        }
    }

    public function test_company_details_are_escaped_and_can_be_hidden(): void
    {
        SystemSetting::query()->where('key', 'footer.company_details')->update(['value' => '<script>alert(1)</script>']);

        $this->blade('<x-footer-company-details />')
            ->assertSee('<script>alert(1)</script>')
            ->assertDontSee('<script>', false);

        Livewire::actingAs($this->admin)->test(CmsAdmin::class, ['section' => 'footer'])
            ->set('footerCompanyDetails', '')
            ->call('saveFooterSettings')
            ->assertHasNoErrors();

        $this->blade('<x-footer-company-details />')->assertDontSee('Company Details');
    }

    public function test_company_details_reject_overlong_content(): void
    {
        Livewire::actingAs($this->admin)->test(CmsAdmin::class, ['section' => 'footer'])
            ->set('footerCompanyDetails', str_repeat('a', 1001))
            ->call('saveFooterSettings')
            ->assertHasErrors(['footerCompanyDetails' => 'max']);
    }

    public function test_company_details_migration_preserves_existing_content(): void
    {
        SystemSetting::query()->where('key', 'footer.company_details')->update(['value' => 'Custom company details']);
        $migration = require database_path('migrations/2026_11_05_000000_add_footer_company_details.php');
        $migration->up();

        $this->assertSame('Custom company details', SystemSetting::query()->where('key', 'footer.company_details')->value('value'));
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
