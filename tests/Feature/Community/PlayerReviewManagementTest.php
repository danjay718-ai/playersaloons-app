<?php

declare(strict_types=1);

namespace Tests\Feature\Community;

use App\Livewire\Admin\PlayerReviewAdmin;
use App\Livewire\Community\PlayerReviewPage;
use App\Modules\Community\Models\PlayerReview;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserStatus;
use Database\Seeders\LandingPageSeeder;
use Database\Seeders\PublicNavigationSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PlayerReviewManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, LandingPageSeeder::class, PublicNavigationSeeder::class]);
    }

    private function user(string $email, string $role): User
    {
        $user = User::query()->create(['uuid' => Str::uuid(), 'email' => $email, 'username' => Str::before($email, '@'), 'password' => 'password', 'status' => UserStatus::ACTIVE, 'email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    public function test_player_can_submit_one_star_review_for_moderation(): void
    {
        $player = $this->user('reviewer@example.com', 'PLAYER');
        Livewire::actingAs($player)->test(PlayerReviewPage::class)->set('rating', 4)->set('review', 'The tournaments are fun and easy to join.')->call('submit')->assertHasNoErrors()->assertSee('submitted for moderation');
        $this->assertDatabaseHas('player_reviews', ['user_id' => $player->id, 'rating' => 4, 'status' => 'pending']);
    }

    public function test_player_review_page_renders_dashboard_mobile_navigation(): void
    {
        $player = $this->user('navigation-reviewer@example.com', 'PLAYER');

        $this->actingAs($player)
            ->get('/reviews')
            ->assertOk()
            ->assertSee('mobile-bottom-nav', escape: false)
            ->assertSee('Review Us')
            ->assertSeeHtml('@click="rating = 1"')
            ->assertDontSee('wire:click="$set(&#039;rating&#039;', escape: false);
    }

    public function test_editing_approved_review_returns_it_to_pending(): void
    {
        $player = $this->user('editor@example.com', 'PLAYER');
        PlayerReview::query()->create(['uuid' => Str::uuid(), 'user_id' => $player->id, 'rating' => 5, 'review' => 'Original approved review.', 'status' => 'approved']);
        Livewire::actingAs($player)->test(PlayerReviewPage::class)->set('review', 'Updated review requiring approval again.')->call('submit');
        $this->assertDatabaseHas('player_reviews', ['user_id' => $player->id, 'status' => 'pending', 'review' => 'Updated review requiring approval again.']);
    }

    public function test_admin_can_approve_review_and_landing_displays_stars(): void
    {
        $player = $this->user('public-reviewer@example.com', 'PLAYER');
        $admin = $this->user('review-admin@example.com', 'ADMIN');
        $review = PlayerReview::query()->create(['uuid' => Str::uuid(), 'user_id' => $player->id, 'rating' => 5, 'review' => 'Excellent competitive platform experience.', 'status' => 'pending']);
        Livewire::actingAs($admin)->test(PlayerReviewAdmin::class)->call('moderate', $review->id, 'approved')->assertSee('Review moderation updated.');
        $this->get('/')->assertOk()->assertSee('Excellent competitive platform experience.')->assertSee('★★★★★');
    }

    public function test_rejected_review_is_not_public(): void
    {
        $player = $this->user('hidden-reviewer@example.com', 'PLAYER');
        PlayerReview::query()->create(['uuid' => Str::uuid(), 'user_id' => $player->id, 'rating' => 1, 'review' => 'This should remain hidden from public pages.', 'status' => 'rejected']);
        $this->get('/')->assertDontSee('This should remain hidden from public pages.');
    }
}
