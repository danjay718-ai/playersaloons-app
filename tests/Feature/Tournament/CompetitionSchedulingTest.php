<?php

declare(strict_types=1);

namespace Tests\Feature\Tournament;

use App\Modules\CMS\Models\Game;
use App\Modules\Identity\Models\User;
use App\Modules\Stream\Models\StreamChannel;
use App\Modules\Tournament\Actions\CreateRecurringCompetitionAction;
use App\Modules\Tournament\Actions\CreateTournamentAction;
use App\Modules\Tournament\Actions\GenerateRecurringTournamentAction;
use App\Modules\Tournament\Actions\PublishTournamentAction;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentTemplate;
use App\Modules\Tournament\Services\RecurrenceSchedule;
use App\Modules\Tournament\Services\TournamentLifecycleReconciler;
use App\Shared\Enums\CompetitionType;
use App\Shared\Enums\RecurrenceFrequency;
use App\Shared\Enums\TournamentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CompetitionSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-08-18 12:00:00 UTC');

        $this->creator = User::query()->create([
            'uuid' => Str::uuid()->toString(),
            'email' => 'scheduler-owner@example.com',
            'username' => 'scheduler-owner',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->game = Game::query()->create([
            'uuid' => Str::uuid()->toString(),
            'slug' => 'schedule-test-game',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_head_to_head_competitions_enforce_a_solo_two_player_shape(): void
    {
        $competition = app(CreateTournamentAction::class)->execute([
            ...$this->baseData(),
            'competition_type' => CompetitionType::HEAD_TO_HEAD,
            'min_participants' => 8,
            'max_participants' => 64,
            'team_size' => 5,
        ], $this->creator);

        $this->assertSame(CompetitionType::HEAD_TO_HEAD, $competition->competition_type);
        $this->assertSame(2, $competition->min_participants);
        $this->assertSame(2, $competition->max_participants);
        $this->assertSame(1, $competition->team_size);
    }

    public function test_creation_normalizes_blank_optional_prizes_to_null(): void
    {
        $competition = app(CreateTournamentAction::class)->execute([
            ...$this->baseData(),
            'prize_1st' => '100.00',
            'prize_2nd' => '',
            'prize_3rd' => '',
        ], $this->creator);

        $this->assertSame('100.00', $competition->prize_1st);
        $this->assertNull($competition->prize_2nd);
        $this->assertNull($competition->prize_3rd);
        $this->assertDatabaseHas('tournaments', [
            'id' => $competition->id,
            'prize_2nd' => null,
            'prize_3rd' => null,
        ]);
    }

    public function test_reconciler_catches_up_due_states_and_cancels_an_opted_in_underfilled_competition(): void
    {
        $competition = app(CreateTournamentAction::class)->execute([
            ...$this->baseData(),
            'registration_open_at' => now()->subHours(4),
            'registration_close_at' => now()->subHours(3),
            'checkin_open_at' => now()->subHours(2),
            'checkin_close_at' => now()->subHour(),
            'start_at' => now(),
            'is_auto_cancel_underfilled' => true,
        ], $this->creator);
        app(PublishTournamentAction::class)->execute($competition);

        $result = app(TournamentLifecycleReconciler::class)->reconcile($competition->id);

        $this->assertSame(TournamentStatus::CANCELLED, $result->status);
        $this->assertNotNull($result->cancelled_at);
        $this->assertDatabaseHas('tournament_cancellations', ['tournament_id' => $result->id]);
    }

    public function test_recurring_creation_persists_a_template_and_publishes_exactly_one_occurrence(): void
    {
        $startAt = CarbonImmutable::now()->addDays(2);

        $occurrence = app(CreateRecurringCompetitionAction::class)->execute([
            ...$this->baseData(),
            'frequency' => RecurrenceFrequency::DAILY->value,
            'timezone' => 'Asia/Singapore',
            'registration_open_at' => $startAt->subDay(),
            'registration_close_at' => $startAt->subHour(),
            'checkin_open_at' => $startAt->subMinutes(30),
            'checkin_close_at' => $startAt->subMinute(),
            'start_at' => $startAt,
            'is_auto_cancel_underfilled' => true,
            'youtube_stream_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ], $this->creator);

        $template = TournamentTemplate::query()->sole();

        $this->assertTrue($template->is_recurring);
        $this->assertSame(RecurrenceFrequency::DAILY, $template->recurrence_frequency);
        $this->assertSame('Asia/Singapore', $template->timezone);
        $this->assertSame(TournamentStatus::PUBLISHED, $occurrence->status);
        $this->assertSame($template->id, $occurrence->template_id);
        $this->assertSame(1, Tournament::query()->where('template_id', $template->id)->count());
        $this->assertTrue($template->next_run_at->equalTo($startAt->addDay()));
        $this->assertDatabaseHas('stream_channels', [
            'tournament_id' => $occurrence->id,
            'provider' => 'youtube',
        ]);

        $nextOccurrence = app(GenerateRecurringTournamentAction::class)->execute(
            $template,
            $this->creator,
            $startAt->addDay()->addMinute(),
        );

        $this->assertNotNull($nextOccurrence);
        $this->assertSame(2, Tournament::query()->where('template_id', $template->id)->count());
        $this->assertSame(2, StreamChannel::query()->where('provider', 'youtube')->count());
    }

    public function test_monthly_recurrence_clamps_to_the_last_valid_day(): void
    {
        $next = app(RecurrenceSchedule::class)->next(
            CarbonImmutable::parse('2027-01-31 18:30:00', 'Asia/Singapore'),
            RecurrenceFrequency::MONTHLY,
            'Asia/Singapore',
            ['day_of_month' => 31],
        );

        $this->assertSame('2027-02-28 18:30:00', $next->format('Y-m-d H:i:s'));
    }

    /** @return array<string, mixed> */
    private function baseData(): array
    {
        return [
            'name' => 'Scheduled Competition',
            'game_id' => $this->game->id,
            'competition_type' => CompetitionType::TOURNAMENT,
            'min_participants' => 2,
            'max_participants' => 8,
            'team_size' => 1,
            'entry_fee' => 0,
            'prize_pool' => 0,
            'description' => 'A scheduled competition used by the automated test suite.',
            'rules' => 'Standard test rules apply.',
            'frequency' => 'one-time',
            'registration_open_at' => now()->addHour(),
            'registration_close_at' => now()->addHours(2),
            'checkin_open_at' => now()->addHours(3),
            'checkin_close_at' => now()->addHours(4),
            'start_at' => now()->addHours(5),
            'waiting_result_time' => 30,
        ];
    }
}
