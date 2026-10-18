<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\EscrowCashflowAdmin;
use App\Livewire\Admin\SystemSettingsAdmin;
use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\Platform;
use App\Modules\Identity\Models\User;
use App\Modules\Operations\Models\SystemSetting;
use App\Modules\Tournament\Services\TournamentTimezone;
use App\Modules\Wallet\Actions\ProcessDepositAction;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Services\StripeCheckoutService;
use App\Modules\Wallet\Services\WalletService;
use App\Shared\Enums\LedgerType;
use App\Shared\Support\Currency;
use Carbon\CarbonImmutable;
use Database\Seeders\PlatformSystemUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

class CurrencyAndIncomeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, PlatformSystemUserSeeder::class, SystemSettingsSeeder::class]);
        $this->admin = $this->user('currency-admin', 'SUPER_ADMIN');
    }

    public function test_currency_defaults_to_euro_and_rejects_unsupported_settings(): void
    {
        SystemSetting::query()->where('key', 'currency.code')->delete();
        config()->set('app.currency', 'EUR');
        self::assertSame('EUR', Currency::code());
        self::assertSame('€12.50', Currency::format('12.50'));
        Livewire::actingAs($this->admin)->test(SystemSettingsAdmin::class)
            ->set('currencyCode', 'INVALID')->call('saveCurrencySettings')->assertHasErrors('currencyCode');

        $reviewer = $this->user('currency-reviewer', 'MODERATOR');
        $reviewer->givePermissionTo('system_settings.view');
        Livewire::actingAs($reviewer)->test(SystemSettingsAdmin::class)
            ->set('currencyCode', 'GBP')->call('saveCurrencySettings')->assertForbidden();
        self::assertDatabaseMissing('system_settings', ['key' => 'currency.code']);
    }

    public function test_currency_setting_applies_to_admin_player_and_both_competition_forms_without_converting_balances(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-17 10:00:00', 'UTC'));
        config()->set('features.tournament_v2.enabled', true);
        $player = $this->user('currency-player', 'PLAYER');
        $wallet = $this->wallet($player, '20.00');
        Livewire::actingAs($this->admin)->test(SystemSettingsAdmin::class)
            ->set('currencyCode', 'GBP')->call('saveCurrencySettings')->assertHasNoErrors()
            ->assertRedirect('/admin/system-settings');
        self::assertSame('GBP', Currency::code());
        self::assertSame('20.00', $wallet->fresh()->cached_balance);

        $game = Game::query()->create(['uuid' => Str::uuid(), 'slug' => 'currency-game', 'is_active' => true]);
        $platform = Platform::query()->create(['name' => 'PC', 'slug' => 'currency-pc', 'is_active' => true]);
        $game->platforms()->attach($platform);
        foreach (['tournament' => 'admin.tournaments.v2', 'head_to_head' => 'admin.h2h.v2'] as $type => $route) {
            $this->actingAs($this->admin)->get(route($route.'.create'))->assertOk()
                ->assertSee('<meta name="app-currency" content="GBP">', false);
            $this->post(route($route.'.store'), [
                'schedule_timezone' => app(TournamentTimezone::class)->value(),
                'name' => 'Currency '.$type, 'competition_type' => $type, 'game_id' => $game->id,
                'platform_ids' => [$platform->id], 'frequency' => 'daily', 'max_teams' => $type === 'tournament' ? 4 : 2,
                'entry_fee' => '5.00', 'winning_points' => 15, 'waiting_result_time' => 5,
                'full_first_percent' => 90, 'full_second_percent' => 0,
                'slots' => [['local_start_time' => '14:00', 'schedule_start_at' => '2026-10-17T14:00', 'schedule_end_at' => '2026-10-17T23:59']],
            ])->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->actingAs($player)->get('/wallet')->assertOk()->assertSee('£20.00');
        $this->get('/h2h')->assertOk()->assertSee('£5.00');
        $this->get('/tournaments/browse?frequency=daily')->assertOk()->assertSee('£5.00');
        $this->actingAs($this->admin)->get('/admin/escrow-cashflow')->assertOk()->assertSee('£20.00');
    }

    public function test_income_excludes_wallet_deposits_prizes_and_other_wallets_and_uses_dates_only(): void
    {
        $wallets = app(WalletService::class);
        $platform = User::query()->where('email', 'platform@playersaloons.com')->firstOrFail()->wallet;
        $player = $this->wallet($this->user('income-player', 'PLAYER'));
        $this->travelTo(CarbonImmutable::parse('2026-10-16 12:00:00', 'UTC'));
        $wallets->credit($platform, '4.00', LedgerType::PLATFORM_COMMISSION, Wallet::class, 'income-test');
        $this->travelTo(CarbonImmutable::parse('2026-10-17 12:00:00', 'UTC'));
        $wallets->credit($platform->fresh(), '10.00', LedgerType::PLATFORM_COMMISSION, Wallet::class, 'income-test');
        $wallets->debitAllowingLiability($platform->fresh(), '25.00', LedgerType::SPONSORED_PRIZE_RESERVE, Wallet::class, 'income-test');
        app(ProcessDepositAction::class)->execute($player, '100.00', 'stripe', 'income-deposit', '2.00');
        $wallets->credit($player->fresh(), '5.00', LedgerType::PRIZE, Wallet::class, 'income-test');
        $wallets->credit($player->fresh(), '3.00', LedgerType::PLATFORM_COMMISSION, Wallet::class, 'income-test');

        Livewire::actingAs($this->admin)->test(EscrowCashflowAdmin::class)
            ->assertViewHas('totalFeeIncome', '16.00')
            ->set('dateFrom', '2026-10-17')->set('dateTo', '2026-10-17')
            ->assertViewHas('serviceFeeIncome', fn ($value) => (float) $value === 10.0)
            ->assertViewHas('topUpFeeIncome', fn ($value) => (float) $value === 2.0)
            ->assertViewHas('totalFeeIncome', '12.00')
            ->set('typeFilter', LedgerType::DEPOSIT->value)->set('search', 'does not match')
            ->assertViewHas('totalFeeIncome', '12.00');
    }

    public function test_checkout_uses_the_selected_currency_and_snapshots_it_in_metadata(): void
    {
        config()->set('services.stripe.secret', 'sk_test_currency');
        $player = $this->user('checkout-player', 'PLAYER');
        $wallet = $this->wallet($player);
        $client = new class implements ClientInterface
        {
            public array $params = [];

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
            {
                $this->params = $params;

                return ['{"id":"cs_currency","object":"checkout.session","url":"https://checkout.stripe.test/currency"}', 200, []];
            }
        };
        $original = ApiRequestor::httpClient();
        ApiRequestor::setHttpClient($client);
        try {
            foreach (['EUR', 'GBP'] as $code) {
                SystemSetting::query()->where('key', 'currency.code')->update(['value' => $code]);
                app(StripeCheckoutService::class)->createDepositSession($player, $wallet, 10);
                self::assertSame(strtolower($code), $client->params['line_items'][0]['price_data']['currency']);
                self::assertSame($code, $client->params['metadata']['currency']);
                self::assertSame(1000, $client->params['line_items'][0]['price_data']['unit_amount']);
            }
        } finally {
            ApiRequestor::setHttpClient($original);
        }
    }

    private function user(string $name, string $role): User
    {
        $user = User::query()->create(['uuid' => Str::uuid(), 'username' => $name, 'email' => $name.'@example.com', 'password' => 'password', 'status' => 'active', 'email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    private function wallet(User $user, string $balance = '0.00'): Wallet
    {
        return Wallet::query()->create(['uuid' => Str::uuid(), 'user_id' => $user->id, 'cached_balance' => $balance, 'status' => 'active']);
    }
}
