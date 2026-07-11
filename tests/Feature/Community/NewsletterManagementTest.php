<?php

declare(strict_types=1);

namespace Tests\Feature\Community;

use App\Livewire\Admin\NewsletterAdmin;
use App\Mail\NewsletterCampaignMail;
use App\Modules\Community\Models\NewsletterCampaign;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class NewsletterManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeUser(string $email, bool $subscribed = false, string $role = 'PLAYER'): User
    {
        $user = User::query()->create([
            'uuid' => Str::uuid()->toString(),
            'email' => $email,
            'username' => Str::before($email, '@'),
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'status' => UserStatus::ACTIVE,
            'newsletter_subscribed' => $subscribed,
            'newsletter_subscribed_at' => $subscribed ? now() : null,
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_admin_can_filter_subscriber_audience(): void
    {
        $admin = $this->makeUser('admin@example.com', false, 'ADMIN');
        $this->makeUser('subscribed@example.com', true);
        $this->makeUser('excluded@example.com');

        Livewire::actingAs($admin)
            ->test(NewsletterAdmin::class)
            ->assertSee('subscribed@example.com')
            ->assertDontSee('excluded@example.com')
            ->set('search', 'subscribed')
            ->assertSee('subscribed@example.com');
    }

    public function test_admin_campaign_sends_only_to_current_subscribers_and_records_result(): void
    {
        Mail::fake();

        $admin = $this->makeUser('campaign-admin@example.com', false, 'ADMIN');
        $subscriber = $this->makeUser('recipient@example.com', true);
        $excluded = $this->makeUser('not-subscribed@example.com');

        Livewire::actingAs($admin)
            ->test(NewsletterAdmin::class)
            ->set('subject', 'Tournament weekend')
            ->set('content', 'New events are now open for registration.')
            ->call('sendCampaign')
            ->assertHasNoErrors()
            ->assertSee('Campaign sent to 1 subscriber(s).');

        Mail::assertSent(NewsletterCampaignMail::class, fn (NewsletterCampaignMail $mail): bool => $mail->hasTo($subscriber->email));
        Mail::assertNotSent(NewsletterCampaignMail::class, fn (NewsletterCampaignMail $mail): bool => $mail->hasTo($excluded->email));

        $campaign = NewsletterCampaign::query()->firstOrFail();
        $this->assertSame('sent', $campaign->status);
        $this->assertSame(1, $campaign->recipient_count);
        $this->assertSame(1, $campaign->sent_count);
        $this->assertSame(0, $campaign->failed_count);
        $this->assertSame($admin->id, $campaign->created_by);
    }

    public function test_signed_unsubscribe_link_removes_user_from_audience(): void
    {
        $subscriber = $this->makeUser('leave@example.com', true);
        $url = URL::signedRoute('newsletter.unsubscribe', ['user' => $subscriber->uuid]);

        $this->get($url)
            ->assertOk()
            ->assertSee('You have been unsubscribed');

        $subscriber->refresh();
        $this->assertFalse($subscriber->newsletter_subscribed);
        $this->assertNull($subscriber->newsletter_subscribed_at);
    }

    public function test_unsubscribe_route_rejects_invalid_signature(): void
    {
        $subscriber = $this->makeUser('protected@example.com', true);

        $this->get(route('newsletter.unsubscribe', ['user' => $subscriber->uuid]))
            ->assertForbidden();

        $this->assertTrue($subscriber->fresh()?->newsletter_subscribed);
    }

    public function test_non_admin_staff_cannot_manage_newsletter_campaigns(): void
    {
        $support = $this->makeUser('support@example.com', false, 'SUPPORT_AGENT');

        $this->actingAs($support)
            ->get('/admin/newsletters')
            ->assertForbidden();
    }
}
