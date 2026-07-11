<?php

declare(strict_types=1);

namespace Tests\Feature\Community;

use App\Livewire\Admin\ContactInquiryAdmin;
use App\Livewire\Admin\AdminDashboard;
use App\Livewire\Community\ContactPage;
use App\Modules\Community\Models\ContactInquiry;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserStatus;
use App\Shared\Enums\WalletStatus;
use App\Modules\Wallet\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ContactInquiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
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

    public function test_guest_can_submit_contact_inquiry(): void
    {
        Livewire::test(ContactPage::class)
            ->set('name', 'Guest Player')
            ->set('email', 'guest@example.com')
            ->set('category', 'tournament')
            ->set('subject', 'Tournament schedule question')
            ->set('message', 'Can you help me understand when the next daily tournament starts?')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSee('Your message was sent');

        $this->assertDatabaseHas('contact_inquiries', [
            'name' => 'Guest Player',
            'email' => 'guest@example.com',
            'category' => 'tournament',
            'status' => 'new',
            'user_id' => null,
        ]);
    }

    public function test_player_contact_inquiry_links_account_and_notifies_staff(): void
    {
        $player = $this->makeUser('PLAYER', 'player@example.com');
        $support = $this->makeUser('SUPPORT_AGENT', 'support@example.com');

        Livewire::actingAs($player)
            ->test(ContactPage::class)
            ->set('category', 'wallet')
            ->set('subject', 'Wallet balance question')
            ->set('message', 'My wallet balance looks different after the last tournament registration.')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('contact_inquiries', [
            'user_id' => $player->id,
            'email' => 'player@example.com',
            'category' => 'wallet',
            'subject' => 'Wallet balance question',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $support->id,
            'type' => 'contact_inquiry',
            'title' => 'New contact inquiry',
        ]);
    }

    public function test_admin_can_review_and_resolve_contact_inquiry(): void
    {
        $admin = $this->makeUser('ADMIN', 'admin@example.com');

        $inquiry = ContactInquiry::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Guest Player',
            'email' => 'guest@example.com',
            'category' => 'general',
            'subject' => 'Need help',
            'message' => 'I need help with my account setup.',
            'status' => 'new',
        ]);

        Livewire::actingAs($admin)
            ->test(ContactInquiryAdmin::class)
            ->call('selectInquiry', $inquiry->id)
            ->assertSet('selectedId', $inquiry->id)
            ->set('adminNotes', 'Resolved through support response.')
            ->call('markResolved')
            ->assertHasNoErrors()
            ->assertSet('status', 'resolved')
            ->assertSee('Inquiry marked resolved.');

        $inquiry->refresh();

        $this->assertSame('resolved', $inquiry->status);
        $this->assertSame('Resolved through support response.', $inquiry->admin_notes);
        $this->assertSame($admin->id, $inquiry->resolved_by);
        $this->assertNotNull($inquiry->resolved_at);
    }

    public function test_admin_archive_switches_to_archived_filter(): void
    {
        $admin = $this->makeUser('ADMIN', 'archive-admin@example.com');

        $inquiry = ContactInquiry::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Guest Player',
            'email' => 'guest@example.com',
            'category' => 'general',
            'subject' => 'Old request',
            'message' => 'This can be archived.',
            'status' => 'in_review',
        ]);

        Livewire::actingAs($admin)
            ->test(ContactInquiryAdmin::class)
            ->call('selectInquiry', $inquiry->id)
            ->call('archive')
            ->assertHasNoErrors()
            ->assertSet('status', 'archived')
            ->assertSee('Inquiry archived.');

        $this->assertDatabaseHas('contact_inquiries', [
            'id' => $inquiry->id,
            'status' => 'archived',
        ]);
    }

    public function test_admin_inbox_shows_status_counters_badges_and_reply_shortcut(): void
    {
        $admin = $this->makeUser('ADMIN', 'inbox-admin@example.com');

        foreach (['new', 'new', 'in_review', 'resolved', 'archived'] as $index => $status) {
            ContactInquiry::query()->create([
                'uuid' => Str::uuid()->toString(),
                'name' => 'Player '.$index,
                'email' => $index === 0 ? 'reply@example.com' : 'player'.$index.'@example.com',
                'category' => $index === 0 ? 'wallet' : 'general',
                'subject' => $index === 0 ? 'Deposit question' : 'Question '.$index,
                'message' => 'Please help with this support request.',
                'status' => $status,
            ]);
        }

        Livewire::actingAs($admin)
            ->test(ContactInquiryAdmin::class)
            ->assertSeeInOrder(['New', '2', 'In review', '1', 'Resolved', '1', 'Archived', '1'])
            ->assertSee('Wallet or payment')
            ->call('selectInquiry', 1)
            ->assertSee('Reply by email')
            ->assertSeeHtml('href="mailto:reply@example.com?subject=Re%3A%20Deposit%20question"');
    }

    public function test_admin_dashboard_summarizes_open_contact_inquiries(): void
    {
        $admin = $this->makeUser('ADMIN', 'dashboard-admin@example.com');

        foreach (['new', 'in_review', 'resolved'] as $index => $status) {
            ContactInquiry::query()->create([
                'uuid' => Str::uuid()->toString(),
                'name' => 'Dashboard Player '.$index,
                'email' => 'dashboard'.$index.'@example.com',
                'category' => 'general',
                'subject' => 'Dashboard question '.$index,
                'message' => 'Please review this inquiry.',
                'status' => $status,
            ]);
        }

        Livewire::actingAs($admin)
            ->test(AdminDashboard::class)
            ->assertSee('Contact Inquiries')
            ->assertSee('New and In Review')
            ->assertSee('Support messages need attention')
            ->assertViewHas('stats', fn (array $stats): bool => $stats['open_contact_inquiries'] === 2);
    }

    public function test_player_cannot_access_contact_inquiry_admin(): void
    {
        $player = $this->makeUser('PLAYER', 'blocked@example.com');

        $this->actingAs($player)
            ->get('/admin/contact-inquiries')
            ->assertStatus(403);
    }
}
