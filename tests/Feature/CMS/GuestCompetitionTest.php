<?php

declare(strict_types=1);

namespace Tests\Feature\CMS;

use App\Livewire\Landing\GuestPulse;
use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Services\GuestCompetitionService;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Tournament\Models\Bracket;
use App\Modules\Tournament\Models\Round;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentTemplate;
use App\Modules\Wallet\Models\PrizeDistribution;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Support\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class GuestCompetitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_board_excludes_private_inactive_elapsed_and_deleted_tournaments(): void
    {
        config(['features.tournament_v2.enabled' => false]);
        $open = $this->tournament('Public registration');
        $live = $this->tournament('Live final', TournamentStatus::ONGOING);
        $this->tournament('Private draft', TournamentStatus::DRAFT);
        $this->tournament('Elapsed slot')->update(['start_at' => now()->subHour()]);
        $this->tournament('Inactive game')->game->update(['is_active' => false]);
        $this->tournament('Archived event')->delete();

        $service = app(GuestCompetitionService::class);
        self::assertSame([$live->id, $open->id], $service->activeTournaments()->pluck('id')->all());
        $text = collect($service->activity())->pluck('text')->implode(' ');
        self::assertStringNotContainsString('Private draft', $text);
        self::assertStringNotContainsString('Inactive game', $text);
        self::assertStringNotContainsString('Archived event', $text);
    }

    public function test_public_activity_is_capped_and_contains_only_confirmed_public_match_wins(): void
    {
        config(['features.tournament_v2.enabled' => false]);
        $tournament = $this->tournament('Public final', TournamentStatus::ONGOING);
        $player = User::factory()->create(['username' => 'PublicChampion', 'email' => 'private@example.com']);
        $registration = $tournament->registrations()->create(['uuid' => Str::uuid(), 'user_id' => $player->id, 'status' => 'confirmed', 'payment_status' => 'free', 'registered_at' => now()]);
        $bracket = Bracket::query()->create(['tournament_id' => $tournament->id]);
        $round = Round::query()->create(['bracket_id' => $bracket->id, 'round_number' => 1]);
        $match = GameMatch::query()->create(['uuid' => Str::uuid(), 'tournament_id' => $tournament->id, 'round_id' => $round->id, 'winner_registration_id' => $registration->id, 'status' => MatchStatus::COMPLETED, 'completed_at' => now()]);

        Livewire::test(GuestPulse::class, ['placement' => 'activity'])->assertSee('PublicChampion won a match in Public final')->assertDontSee('private@example.com')->assertSee('wire:poll.30s.visible', false);
        $match->update(['status' => MatchStatus::DISPUTED]);
        self::assertFalse(collect(app(GuestCompetitionService::class)->activity())->contains('key', 'match-'.$match->id));
        for ($i = 0; $i < 10; $i++) {
            $copy = $match->replicate();
            $copy->uuid = Str::uuid()->toString();
            $copy->status = MatchStatus::COMPLETED;
            $copy->save();
        }
        $this->tournament('Registration only');
        self::assertCount(8, collect(app(GuestCompetitionService::class)->activity()));
        self::assertStringNotContainsString('Registration only', collect(app(GuestCompetitionService::class)->activity())->pluck('text')->implode(' '));
    }

    public function test_empty_pulse_provides_plain_fallback_and_v2_hides_legacy_events(): void
    {
        config(['features.tournament_v2.enabled' => true]);
        $this->tournament('Legacy event');
        Livewire::test(GuestPulse::class)->assertDontSee('Legacy event')->assertViewHas('summary', fn (array $summary) => $summary['total'] === 0)->assertSee('New competitions are on the way.');
        Livewire::test(GuestPulse::class, ['placement' => 'activity'])->assertSee('The next win could be yours. Confirmed player wins appear here.');
    }

    public function test_win_ticker_only_attaches_a_settled_champion_prize_to_the_final(): void
    {
        config(['features.tournament_v2.enabled' => false]);
        $tournament = $this->tournament('FC challenge', TournamentStatus::COMPLETED);
        $player = User::factory()->create(['username' => 'PlayerChampion']);
        $wallet = $player->wallet()->create(['uuid' => Str::uuid(), 'cached_balance' => '999.00', 'status' => 'active']);
        $registration = $tournament->registrations()->create(['uuid' => Str::uuid(), 'user_id' => $player->id, 'status' => 'confirmed', 'payment_status' => 'free', 'registered_at' => now()]);
        $bracket = Bracket::query()->create(['tournament_id' => $tournament->id]);
        $semi = Round::query()->create(['bracket_id' => $bracket->id, 'round_number' => 1]);
        $final = Round::query()->create(['bracket_id' => $bracket->id, 'round_number' => 2]);
        foreach ([$semi, $final] as $round) {
            GameMatch::query()->create(['uuid' => Str::uuid(), 'tournament_id' => $tournament->id, 'round_id' => $round->id, 'winner_registration_id' => $registration->id, 'status' => MatchStatus::COMPLETED, 'completed_at' => now()]);
        }
        $prize = ['uuid' => Str::uuid(), 'tournament_id' => $tournament->id, 'wallet_id' => $wallet->id, 'rank' => 1, 'amount' => '85.00', 'distribution_reference_uuid' => Str::uuid(), 'created_at' => now()];
        PrizeDistribution::query()->create($prize + ['status' => 'pending']);
        self::assertSame([null, null], collect(app(GuestCompetitionService::class)->activity())->pluck('reward')->all());
        PrizeDistribution::query()->create(array_replace($prize, ['uuid' => Str::uuid(), 'distribution_reference_uuid' => Str::uuid(), 'status' => 'completed']));
        self::assertSame(['+'.Currency::format('85.00'), null], collect(app(GuestCompetitionService::class)->activity())->pluck('reward')->all());
        Livewire::test(GuestPulse::class, ['placement' => 'activity'])->assertSee('PlayerChampion')->assertSee('FC challenge')->assertSee('+'.Currency::format('85.00'))->assertDontSee('999.00');
        $player->delete();
        self::assertCount(0, collect(app(GuestCompetitionService::class)->activity()));
    }

    public function test_hero_slider_uses_only_upcoming_public_registrations_in_start_order(): void
    {
        config(['features.tournament_v2.enabled' => false]);
        $later = $this->tournament('Later open');
        $earlier = $this->tournament('Earlier open');
        $earlier->update(['start_at' => now()->addHour()]);
        $this->tournament('Live only', TournamentStatus::ONGOING);
        $this->tournament('Elapsed open')->update(['start_at' => now()->subHour()]);
        $this->tournament('Hidden open')->game->update(['is_active' => false]);
        $this->tournament('Duel only')->update(['competition_type' => 'head_to_head']);
        self::assertSame([$earlier->id, $later->id], app(GuestCompetitionService::class)->registrationTournaments()->pluck('id')->all());
    }

    public function test_public_layouts_share_the_same_footer_markup(): void
    {
        $footers = [];
        foreach (['/', '/about', '/contact', '/tournaments', '/policies', '/login', '/register'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            preg_match('/<footer class="guest-footer">.*?<\/footer>/s', $html, $matches);
            self::assertNotEmpty($matches, $url);
            $footers[] = $matches[0];
        }
        self::assertCount(1, array_unique($footers));
    }

    public function test_v2_board_shows_discoverable_occurrences_and_excludes_head_to_head(): void
    {
        config(['features.tournament_v2.enabled' => true]);
        $tournament = $this->tournament('V2 public occurrence');
        $template = TournamentTemplate::query()->create([
            'uuid' => Str::uuid(), 'game_id' => $tournament->game_id, 'name' => 'Recurring series',
            'workflow_version' => 2, 'max_participants' => 8, 'min_participants' => 2,
        ]);
        $tournament->update(['workflow_version' => 2, 'template_id' => $template->id]);
        self::assertSame([$tournament->id], app(GuestCompetitionService::class)->activeTournaments()->pluck('id')->all());
        $tournament->update(['competition_type' => 'head_to_head']);
        self::assertCount(0, app(GuestCompetitionService::class)->activeTournaments());
    }

    public function test_open_counts_include_all_occurrences_and_refresh_per_game(): void
    {
        config(['features.tournament_v2.enabled' => false]);
        $first = $this->tournament('Open event');
        $first->game->translations()->create(['locale' => 'en', 'name' => 'Mobile Legends']);
        for ($i = 0; $i < 12; $i++) {
            $copy = $first->replicate();
            $copy->uuid = Str::uuid()->toString();
            $copy->slug = Str::uuid()->toString();
            $copy->save();
        }
        $this->tournament('Ongoing event', TournamentStatus::ONGOING);
        $this->tournament('Draft event', TournamentStatus::DRAFT);
        $this->tournament('Elapsed registration')->update(['start_at' => now()->subHour()]);
        $this->tournament('Hidden game')->game->update(['is_active' => false]);
        $h2h = $this->tournament('Head to head');
        $h2h->update(['competition_type' => 'head_to_head']);

        $pulse = Livewire::test(GuestPulse::class)
            ->assertViewHas('summary', fn (array $summary) => $summary['total'] === 13 && $summary['games'][0]['count'] === 13)
            ->assertSee('13 open for Mobile Legends');
        $first->update(['status' => TournamentStatus::REGISTRATION_CLOSED]);
        $pulse->call('$refresh')->assertViewHas('summary', fn (array $summary) => $summary['total'] === 12 && $summary['games'][0]['count'] === 12);
        config(['features.tournament_v2.enabled' => true]);
        self::assertSame(0, app(GuestCompetitionService::class)->openSummary()['total']);
    }

    public function test_account_pages_keep_accessible_forms_and_visible_install_entry_points(): void
    {
        $this->get('/login')->assertOk()->assertSee('auth-shell-login')->assertSee('autocomplete="username"', false)
            ->assertSee('type="password"', false)->assertSee('data-pwa-install-desktop', false)->assertSee('data-pwa-install-mobile', false);
        $this->get('/register')->assertOk()->assertSee('auth-shell-register')->assertSee('name="accepted_policies"', false)
            ->assertSee('name="age_confirmed"', false)->assertSee('name="newsletter_subscribed"', false)
            ->assertSee('data-pwa-install-desktop', false)->assertSee('data-pwa-install-mobile', false);
    }

    private function tournament(string $name, TournamentStatus $status = TournamentStatus::REGISTRATION_OPEN): Tournament
    {
        $game = Game::query()->create(['uuid' => Str::uuid(), 'slug' => Str::uuid(), 'is_active' => true]);

        return Tournament::query()->create(['uuid' => Str::uuid(), 'name' => $name, 'slug' => Str::uuid(), 'game_id' => $game->id, 'created_by' => User::factory()->create()->id, 'workflow_version' => 1, 'competition_type' => 'tournament', 'status' => $status, 'entry_fee' => 0, 'max_participants' => 8, 'min_participants' => 2, 'start_at' => now()->addDay()]);
    }
}
