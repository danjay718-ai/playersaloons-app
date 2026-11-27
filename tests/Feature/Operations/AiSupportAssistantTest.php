<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Livewire\Admin\SystemSettingsAdmin;
use App\Modules\Identity\Models\User;
use App\Modules\Operations\Actions\UpdateAiProviderSettingsAction;
use App\Modules\Operations\Services\AiProviderSettings;
use App\Modules\Operations\Services\AiSupportAssistantService;
use App\Modules\Operations\Services\SystemSettings;
use App\Modules\Wallet\Models\Wallet;
use App\Shared\Enums\UserStatus;
use App\Shared\Enums\WalletStatus;
use Database\Seeders\PlatformSystemUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AiSupportAssistantTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlatformSystemUserSeeder::class);
        $this->seed(SystemSettingsSeeder::class);

        $this->admin = $this->makeUser('SUPER_ADMIN', 'support-admin@example.com');
        $this->player = $this->makeUser('PLAYER', 'support-player@example.com');

        app(UpdateAiProviderSettingsAction::class)->execute(
            $this->admin,
            AiProviderSettings::PROVIDER_GEMINI,
            [AiProviderSettings::PROVIDER_GEMINI => 'test-shared-gemini-key'],
            [AiProviderSettings::PROVIDER_GEMINI => 'gemini-3.5-flash-lite'],
            [],
            [],
        );
    }

    public function test_knowledge_base_is_strictly_isolated_by_role_and_contains_no_code_level_references(): void
    {
        $service = app(AiSupportAssistantService::class);

        $guestKb = $service->knowledgeBaseForScope(AiSupportAssistantService::SCOPE_GUEST);
        $playerKb = $service->knowledgeBaseForScope(AiSupportAssistantService::SCOPE_PLAYER);
        $adminKb = $service->knowledgeBaseForScope(AiSupportAssistantService::SCOPE_ADMIN);

        // Guest only sees guest docs
        $this->assertStringContainsString('GamersRival Platform Overview', $guestKb);
        $this->assertStringNotContainsString('Wallet, Deposits, Withdrawals & KYC (Player Guide)', $guestKb);
        $this->assertStringNotContainsString('Admin Roles, Access & Operations Overview', $guestKb);

        // Player sees guest + player docs, NEVER admin docs
        $this->assertStringContainsString('GamersRival Platform Overview', $playerKb);
        $this->assertStringContainsString('Wallet, Deposits, Withdrawals & KYC (Player Guide)', $playerKb);
        $this->assertStringNotContainsString('Admin Roles, Access & Operations Overview', $playerKb);
        $this->assertStringNotContainsString('AI Dispute Assistant', $playerKb);

        // Admin sees all three scopes
        $this->assertStringContainsString('GamersRival Platform Overview', $adminKb);
        $this->assertStringContainsString('Wallet, Deposits, Withdrawals & KYC (Player Guide)', $adminKb);
        $this->assertStringContainsString('Admin Roles, Access & Operations Overview', $adminKb);
        $this->assertStringContainsString('AI Dispute Assistant', $adminKb);

        // No code-level leaks across any knowledge base file
        foreach ([$guestKb, $playerKb, $adminKb] as $kb) {
            $this->assertStringNotContainsString('.php', $kb);
            $this->assertStringNotContainsString('App\\', $kb);
            $this->assertStringNotContainsString('broadcast_messages', $kb);
            $this->assertStringNotContainsString('dispute_ai_analyses', $kb);
        }
    }

    public function test_guest_player_and_admin_requests_use_same_configured_api_key_with_strict_server_role_scope(): void
    {
        Http::swap(new Factory);
        Http::fake([
            '*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Here is the step-by-step guide for your role.'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        // 1. Guest request
        $this->postJson(route('ai-support.ask'), [
            'message' => 'How do I create an account?',
        ])->assertOk()
            ->assertJson([
                'scope' => 'guest',
                'reply' => 'Here is the step-by-step guide for your role.',
            ]);

        Http::assertSent(function ($request): bool {
            $systemText = (string) data_get($request->data(), 'systemInstruction.parts.0.text', '');

            return $request->hasHeader('x-goog-api-key', 'test-shared-gemini-key')
                && str_contains($systemText, 'AUTHORIZED KNOWLEDGE BASE (guest)')
                && ! str_contains($systemText, 'Admin Roles, Access & Operations Overview')
                && ! str_contains($systemText, 'Wallet, Deposits, Withdrawals & KYC (Player Guide)');
        });

        // 2. Player request
        Http::swap(new Factory);
        Http::fake([
            '*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Player help response.']]]],
                ],
            ], 200),
        ]);

        $this->actingAs($this->player)
            ->postJson(route('ai-support.ask'), [
                'message' => 'How do I connect PlayStrategy?',
            ])->assertOk()
            ->assertJson([
                'scope' => 'player',
                'reply' => 'Player help response.',
            ]);

        Http::assertSent(function ($request): bool {
            $systemText = (string) data_get($request->data(), 'systemInstruction.parts.0.text', '');

            return str_contains($systemText, 'AUTHORIZED KNOWLEDGE BASE (player)')
                && str_contains($systemText, 'Wallet, Deposits, Withdrawals & KYC (Player Guide)')
                && ! str_contains($systemText, 'Admin Roles, Access & Operations Overview');
        });

        // 3. Admin request
        Http::swap(new Factory);
        Http::fake([
            '*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Admin help response.']]]],
                ],
            ], 200),
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('ai-support.ask'), [
                'message' => 'How does AI dispute escalation work?',
            ])->assertOk()
            ->assertJson([
                'scope' => 'admin',
                'reply' => 'Admin help response.',
            ]);

        Http::assertSent(function ($request): bool {
            $systemText = (string) data_get($request->data(), 'systemInstruction.parts.0.text', '');

            return str_contains($systemText, 'AUTHORIZED KNOWLEDGE BASE (admin)')
                && str_contains($systemText, 'Admin Roles, Access & Operations Overview');
        });
    }

    public function test_floating_widget_renders_on_homepage_player_dashboard_and_admin_panel_and_respects_admin_toggle(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-ai-support-root', false)
            ->assertSee('data-scope="guest"', false);

        $this->actingAs($this->player)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('data-ai-support-root', false)
            ->assertSee('data-scope="player"', false);

        $this->actingAs($this->admin)
            ->get('/admin/deposits')
            ->assertOk()
            ->assertSee('data-ai-support-root', false)
            ->assertSee('data-scope="admin"', false);

        // Admin disables AI support widget via System Settings
        Livewire::actingAs($this->admin)
            ->test(SystemSettingsAdmin::class)
            ->set('aiSupportEnabled', false)
            ->call('saveAiSupportSettings')
            ->assertHasNoErrors();

        $this->assertFalse(app(AiSupportAssistantService::class)->isAvailable());

        $this->get('/')
            ->assertOk()
            ->assertDontSee('data-ai-support-root', false);

        $this->postJson(route('ai-support.ask'), [
            'message' => 'Hello?',
        ])->assertStatus(503);

        // Re-enable cleanly
        app(SystemSettings::class)->save($this->admin, ['ai.support.enabled' => 'true']);
        $this->assertTrue(app(AiSupportAssistantService::class)->isAvailable());
    }

    public function test_visual_guide_screenshots_are_extracted_and_strictly_filtered_by_role(): void
    {
        $service = app(AiSupportAssistantService::class);

        // Guest allowed guide passes, admin guide is strictly stripped & blocked
        $guestResult = $service->extractGuides(
            "1. Click **Find Tournament**.\n\n[GUIDE:find_tournament]\n[GUIDE:admin_ai_dispute]",
            AiSupportAssistantService::SCOPE_GUEST
        );
        $this->assertStringNotContainsString('[GUIDE:', $guestResult['reply']);
        $this->assertCount(1, $guestResult['guides']);
        $this->assertSame('/images/help-guides/guest/find-tournament.png', $guestResult['guides'][0]['url']);

        // Player cannot receive admin guide screenshot either
        $playerResult = $service->extractGuides(
            "Check your wallet.\n\n[GUIDE:wallet_deposit]\n[GUIDE:admin_ai_settings]",
            AiSupportAssistantService::SCOPE_PLAYER
        );
        $this->assertCount(1, $playerResult['guides']);
        $this->assertSame('/images/help-guides/player/wallet-deposit.png', $playerResult['guides'][0]['url']);

        // Admin receives admin guide screenshot
        $adminResult = $service->extractGuides(
            "Open Match & Dispute Control.\n\n[GUIDE:admin_ai_dispute]",
            AiSupportAssistantService::SCOPE_ADMIN
        );
        $this->assertCount(1, $adminResult['guides']);
        $this->assertSame('/images/help-guides/admin/admin-ai-dispute.png', $adminResult['guides'][0]['url']);
    }

    private function makeUser(string $role, string $email): User
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
            'cached_balance' => '0.00',
            'status' => WalletStatus::ACTIVE,
        ]);

        return $user;
    }
}
