<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\SystemSettingsAdmin;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_settings_page_contains_grouped_navigation_and_all_existing_forms(): void
    {
        $admin = User::factory()->withRole('SUPER_ADMIN')->create();
        $this->actingAs($admin)->get('/admin/system-settings?section=payments')
            ->assertOk()->assertSee('Settings navigation')->assertSee('Workspace')
            ->assertSee('Finance')->assertSee('Operations')->assertSee('Copy URL')
            ->assertSee('data-settings-panel="payments"', false)
            ->assertSee('data-settings-panel="maintenance"', false);
    }

    public function test_all_admin_entry_pages_load_for_a_super_admin(): void
    {
        $admin = User::factory()->withRole('SUPER_ADMIN')->create();
        $this->actingAs($admin);
        $pages = collect(Route::getRoutes())->filter(fn ($route) => in_array('GET', $route->methods(), true)
            && str_starts_with($route->uri(), 'admin') && ! str_contains($route->uri(), '{'))
            ->map(fn ($route) => '/'.$route->uri())->values();
        $pages = $pages->merge(collect(['landing', 'games', 'platforms', 'navigation', 'about', 'footer', 'cookies'])
            ->map(fn ($section) => '/admin/cms/'.$section));

        foreach ($pages as $page) {
            $response = $this->get($page);
            $this->assertContains($response->status(), [200, 302], $page);
            $this->assertNotSame(url('/login'), $response->headers->get('Location'), $page);
        }
    }

    #[DataProvider('settings')]
    public function test_each_settings_form_saves_and_rejects_invalid_values(string $action, string $field, mixed $valid, mixed $invalid, string $key, string $expected): void
    {
        $admin = User::factory()->withRole('SUPER_ADMIN')->create();
        $form = Livewire::actingAs($admin)->test(SystemSettingsAdmin::class);
        $form->set($field, $valid)->call($action)->assertHasNoErrors();
        $this->assertDatabaseHas('system_settings', ['key' => $key, 'value' => $expected, 'updated_by' => $admin->id]);
        if ($invalid !== null) {
            $form->set($field, $invalid)->call($action)->assertHasErrors($field);
            $this->assertDatabaseHas('system_settings', ['key' => $key, 'value' => $expected]);
        }
    }

    #[DataProvider('settings')]
    public function test_view_only_staff_cannot_save_any_settings(string $action, mixed ...$values): void
    {
        $viewer = User::factory()->withRole('MODERATOR')->create();
        $viewer->givePermissionTo('system_settings.view');
        Livewire::actingAs($viewer)->test(SystemSettingsAdmin::class)
            ->assertSee('View-only access')->call($action)->assertForbidden();
    }

    public static function settings(): array
    {
        return [
            ['saveCurrencySettings', 'currencyCode', 'GBP', 'PHP', 'currency.code', 'GBP'],
            ['saveAppTimezoneSettings', 'tournamentTimezone', 'Asia/Singapore', 'Invalid/Zone', 'tournament.timezone', 'Asia/Singapore'],
            ['saveTournamentSettings', 'defaultWaitingResultTime', 45, 0, 'tournament.waiting_result_time_default', '45'],
            ['saveAuthenticationSettings', 'loginMaxAttempts', 7, 1, 'auth.login_max_attempts', '7'],
            ['savePlatformCommissionSettings', 'platformCommissionPercentage', '12.50', '101', 'platform.commission_percentage', '12.50'],
            ['saveDepositFeeSettings', 'depositFeeFixed', '2.50', '-1', 'deposit_fee.fixed', '2.50'],
            ['saveReferralSettings', 'referrerReward', '6.50', '-1', 'referral.referrer_reward', '6.50'],
            ['saveLanguageSwitcherSettings', 'showLanguageSwitcherGuest', true, null, 'language_switcher.show_guest', 'true'],
            ['saveDisputeNotificationSettings', 'disputeNotificationEmail', 'disputes@example.com', 'invalid', 'notifications.dispute_email', 'disputes@example.com'],
        ];
    }
}
