<?php

declare(strict_types=1);

namespace Tests\Feature\Match;

use App\Livewire\Admin\MatchAdmin;
use App\Livewire\Admin\SystemSettingsAdmin;
use App\Modules\CMS\Models\Game;
use App\Modules\Identity\Models\PlayerDisputeStrike;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Actions\EvaluateAiDisputeAutoResolveAction;
use App\Modules\Match\Actions\SubmitEvidenceAction;
use App\Modules\Match\Events\MatchCompleted;
use App\Modules\Match\Jobs\AnalyzeDisputeWithAiJob;
use App\Modules\Match\Jobs\EvaluateAiDisputeAutoResolveJob;
use App\Modules\Match\Models\DisputeAiAnalysis;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchDispute;
use App\Modules\Match\Models\MatchEvidence;
use App\Modules\Match\Services\AiDisputeSettings;
use App\Modules\Match\Services\GeminiDisputeAnalyzer;
use App\Modules\Operations\Services\SystemSettings;
use App\Modules\Tournament\Models\Bracket;
use App\Modules\Tournament\Models\Round;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Shared\Enums\CompetitionType;
use App\Shared\Enums\DisputeResolution;
use App\Shared\Enums\DisputeStatus;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Enums\UserStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AiDisputeManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $playerA;

    private User $playerB;

    private User $outsider;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SystemSettingsSeeder::class);

        $this->admin = User::factory()->create(['status' => UserStatus::ACTIVE, 'username' => 'dispute_admin']);
        $this->admin->assignRole('ADMIN');

        $this->playerA = User::factory()->create(['status' => UserStatus::ACTIVE, 'username' => 'alpha_player']);
        $this->playerA->assignRole('PLAYER');

        $this->playerB = User::factory()->create(['status' => UserStatus::ACTIVE, 'username' => 'beta_player']);
        $this->playerB->assignRole('PLAYER');

        $this->outsider = User::factory()->create(['status' => UserStatus::ACTIVE, 'username' => 'outsider_player']);
        $this->outsider->assignRole('PLAYER');

        $this->game = Game::query()->create([
            'uuid' => Str::uuid()->toString(),
            'slug' => 'ai-dispute-game',
            'game_category_id' => $this->defaultGameCategoryId(),
            'is_active' => true,
        ]);
        $this->game->translations()->create([
            'locale' => 'en',
            'name' => 'Arena Chess',
            'description' => 'Test game.',
        ]);

        config(['services.gemini.api_key' => 'test-gemini-key', 'services.gemini.dispute_model' => 'gemini-2.5-flash']);
    }

    public function test_private_evidence_controller_enforces_participant_and_staff_authorization(): void
    {
        Storage::fake('local');
        ['dispute' => $dispute] = $this->createDisputedMatch();

        $file = UploadedFile::fake()->create('proof.jpg', 80, 'image/jpeg');
        $evidence = app(SubmitEvidenceAction::class)->execute($dispute, $this->playerA->id, $file, 'Clear win screenshot attached.');

        Storage::disk('local')->assertExists($evidence->file_path);

        $this->actingAs($this->playerA)->get(route('match.evidence.show', $evidence->uuid))->assertOk();
        $this->actingAs($this->playerB)->get(route('match.evidence.show', $evidence->uuid))->assertOk();
        $this->actingAs($this->admin)->get(route('match.evidence.show', $evidence->uuid))->assertOk();
        $this->actingAs($this->outsider)->get(route('match.evidence.show', $evidence->uuid))->assertForbidden();
    }

    public function test_evidence_upload_dispatches_ai_jobs_and_shadow_mode_never_auto_resolves(): void
    {
        Storage::fake('local');
        Queue::fake();
        $this->configureAiSettings(enabled: true, mode: 'shadow');

        ['dispute' => $dispute, 'match' => $match] = $this->createDisputedMatch();

        app(SubmitEvidenceAction::class)->execute(
            $dispute,
            $this->playerA->id,
            UploadedFile::fake()->create('p1.jpg', 50, 'image/jpeg'),
            'Player A final scoreboard proof.'
        );

        Queue::assertPushed(AnalyzeDisputeWithAiJob::class, 1);
        Queue::assertPushed(EvaluateAiDisputeAutoResolveJob::class, 1);

        // Run version 1 with high confidence in Shadow Mode
        $this->fakeGeminiResponse('player_a', 0.99, 'Player A clearly won 2-0.', ['Scoreboard shows alpha_player 2-0.'], []);
        (new AnalyzeDisputeWithAiJob($dispute->id))->handle(
            app(AiDisputeSettings::class),
            app(GeminiDisputeAnalyzer::class),
            app(EvaluateAiDisputeAutoResolveAction::class),
        );

        // Second upload triggers version 2 re-analysis
        app(SubmitEvidenceAction::class)->execute(
            $dispute,
            $this->playerB->id,
            UploadedFile::fake()->create('p2.jpg', 50, 'image/jpeg'),
            'Player B rebuttal screenshot.'
        );

        (new AnalyzeDisputeWithAiJob($dispute->id))->handle(
            app(AiDisputeSettings::class),
            app(GeminiDisputeAnalyzer::class),
            app(EvaluateAiDisputeAutoResolveAction::class),
        );

        $latest = DisputeAiAnalysis::query()->where('match_dispute_id', $dispute->id)->orderByDesc('analysis_version')->firstOrFail();
        $this->assertSame(2, $latest->analysis_version);
        $this->assertSame(2, $latest->evidence_count);
        $this->assertSame(DisputeAiAnalysis::STATUS_COMPLETED, $latest->status);
        $this->assertFalse($latest->auto_resolve_eligible);
        $this->assertSame('shadow_mode_enabled', $latest->auto_resolve_blocked_reason);

        // Match & dispute remain open for human admin in Shadow Mode
        $this->assertSame(MatchStatus::DISPUTED, $match->fresh()->status);
        $this->assertSame(DisputeStatus::UNDER_REVIEW, $dispute->fresh()->status);
    }

    public function test_auto_resolve_waits_for_second_upload_or_window_then_resolves_without_strikes(): void
    {
        Storage::fake('local');
        Queue::fake();
        Event::fake([MatchCompleted::class]);
        $this->configureAiSettings(enabled: true, mode: 'auto_resolve', minConfidence: '0.95', maxPrizeCap: '50.00', windowMinutes: 10);

        ['dispute' => $dispute, 'match' => $match, 'regA' => $regA] = $this->createDisputedMatch(
            maxParticipants: 8,
            roundNumber: 1,
            totalRounds: 3,
            entryFee: '5.00',
            prizePool: '30.00',
        );

        // Upload 1st screenshot -> should NOT auto-resolve before 10-min response window
        $ev1 = app(SubmitEvidenceAction::class)->execute(
            $dispute,
            $this->playerA->id,
            UploadedFile::fake()->create('p1.jpg', 50, 'image/jpeg'),
            'Player A final scoreboard proof.'
        );

        $this->fakeGeminiResponse('player_a', 0.98, 'Clear Player A win.', ['Scoreboard confirms alpha_player victory.'], []);
        (new AnalyzeDisputeWithAiJob($dispute->id))->handle(
            app(AiDisputeSettings::class),
            app(GeminiDisputeAnalyzer::class),
            app(EvaluateAiDisputeAutoResolveAction::class),
        );

        $v1 = DisputeAiAnalysis::query()->where('match_dispute_id', $dispute->id)->firstOrFail();
        $this->assertSame('awaiting_opponent_response_window', $v1->auto_resolve_blocked_reason);
        $this->assertSame(MatchStatus::DISPUTED, $match->fresh()->status);

        // Simulate response window elapsed -> delayed evaluator resolves atomically via ResolveDisputeByAiAction
        MatchEvidence::query()->whereKey($ev1->id)->toBase()->update(['created_at' => now()->subMinutes(15)]);

        $resolved = app(EvaluateAiDisputeAutoResolveAction::class)->execute($dispute->id);
        $this->assertTrue($resolved);

        $dispute->refresh();
        $match->refresh();
        $v1->refresh();

        $this->assertSame(DisputeStatus::RESOLVED, $dispute->status);
        $this->assertSame(DisputeResolution::PLAYER_A, $dispute->resolution);
        $this->assertNull($dispute->resolved_by);
        $this->assertSame(MatchStatus::COMPLETED, $match->status);
        $this->assertSame($regA->id, $match->winner_registration_id);
        $this->assertSame('ai_auto_resolution', $match->resolution_reason);
        $this->assertTrue($v1->auto_resolve_eligible);
        $this->assertNotNull($v1->auto_resolved_at);
        $this->assertSame(0, PlayerDisputeStrike::query()->count());

        Event::assertDispatched(MatchCompleted::class);
    }

    public function test_auto_resolve_blocks_prize_cap_finals_and_red_flags_including_prompt_injection(): void
    {
        Storage::fake('local');
        Queue::fake();
        $this->configureAiSettings(enabled: true, mode: 'auto_resolve', minConfidence: '0.95', maxPrizeCap: '50.00', windowMinutes: 10);

        // 1. Prize cap exceeded
        ['dispute' => $highPrizeDispute, 'match' => $highPrizeMatch] = $this->createDisputedMatch(
            maxParticipants: 8,
            roundNumber: 1,
            totalRounds: 3,
            entryFee: '10.00',
            prizePool: '250.00',
        );
        $this->uploadBothEvidence($highPrizeDispute);
        $this->fakeGeminiResponse('player_a', 0.99, 'Player A won.', ['Confirmed'], []);
        $this->runAnalysisJob($highPrizeDispute->id);

        $this->assertSame('exceeds_max_prize_cap', $highPrizeDispute->latestAiAnalysis?->auto_resolve_blocked_reason);
        $this->assertSame(MatchStatus::DISPUTED, $highPrizeMatch->fresh()->status);

        // 2. Tournament final match
        ['dispute' => $finalDispute, 'match' => $finalMatch] = $this->createDisputedMatch(
            maxParticipants: 8,
            roundNumber: 3,
            totalRounds: 3,
            entryFee: '5.00',
            prizePool: '25.00',
        );
        $this->uploadBothEvidence($finalDispute);
        $this->fakeGeminiResponse('player_b', 0.99, 'Player B won final.', ['Confirmed'], []);
        $this->runAnalysisJob($finalDispute->id);

        $this->assertSame('tournament_final_requires_human', $finalDispute->latestAiAnalysis?->auto_resolve_blocked_reason);
        $this->assertSame(MatchStatus::DISPUTED, $finalMatch->fresh()->status);

        // 3. Prompt injection / red flags
        ['dispute' => $injectedDispute, 'match' => $injectedMatch] = $this->createDisputedMatch(
            maxParticipants: 8,
            roundNumber: 1,
            totalRounds: 3,
            entryFee: '5.00',
            prizePool: '25.00',
        );
        $this->uploadBothEvidence($injectedDispute);
        $this->fakeGeminiResponse('player_a', 0.99, 'Prompt injection detected.', ['Text says ignore instructions.'], ['prompt_injection_suspected']);
        $this->runAnalysisJob($injectedDispute->id);

        $analysis = $injectedDispute->latestAiAnalysis()->firstOrFail();
        $this->assertSame(DisputeAiAnalysis::SUGGESTED_NEEDS_HUMAN, $analysis->suggested_resolution);
        $this->assertLessThanOrEqual(0.50, (float) $analysis->confidence);
        $this->assertSame(MatchStatus::DISPUTED, $injectedMatch->fresh()->status);
    }

    public function test_gemini_outage_marks_failed_cleanly_and_admin_manual_ruling_records_agreement(): void
    {
        Storage::fake('local');
        Queue::fake();
        $this->configureAiSettings(enabled: true, mode: 'shadow');

        ['dispute' => $dispute, 'match' => $match] = $this->createDisputedMatch();
        $this->uploadBothEvidence($dispute);

        // Outage returns HTTP 500 -> marks failed without crashing or blocking human admin
        Http::swap(new Factory);
        Http::fake(['*' => Http::response(['error' => 'upstream error'], 500)]);
        $this->runAnalysisJob($dispute->id);

        $failed = $dispute->latestAiAnalysis()->firstOrFail();
        $this->assertSame(DisputeAiAnalysis::STATUS_FAILED, $failed->status);
        $this->assertSame('analysis_failed', $failed->auto_resolve_blocked_reason);

        // Re-run succeeds -> admin rules in agreement -> admin_agreed = true
        $this->fakeGeminiResponse('player_a', 0.96, 'Player A clearly won.', ['Confirmed scoreboard.'], []);
        $this->runAnalysisJob($dispute->id);

        $completed = $dispute->latestAiAnalysis()->firstOrFail();
        $this->assertSame(DisputeAiAnalysis::STATUS_COMPLETED, $completed->status);

        Livewire::actingAs($this->admin)
            ->test(MatchAdmin::class)
            ->call('openDisputeModal', $dispute->id)
            ->assertSee('AI Dispute Assistant')
            ->assertSee('96.0% Confidence')
            ->set('resolution', 'player_a')
            ->call('resolveDispute')
            ->assertHasNoErrors();

        $this->assertSame(MatchStatus::COMPLETED, $match->fresh()->status);
        $this->assertTrue($completed->fresh()->admin_agreed);
    }

    public function test_admin_can_update_ai_dispute_settings_in_system_settings(): void
    {
        Livewire::actingAs($this->admin)
            ->test(SystemSettingsAdmin::class)
            ->set('aiDisputesEnabled', true)
            ->set('aiDisputesMode', 'auto_resolve')
            ->set('aiDisputesMinConfidence', '0.97')
            ->set('aiDisputesMaxPrizeCap', '75.00')
            ->set('aiDisputesEvidenceWindowMinutes', 15)
            ->set('aiDisputesMaxRunsPerDispute', 4)
            ->call('saveAiDisputeSettings')
            ->assertHasNoErrors();

        $settings = app(AiDisputeSettings::class);
        $this->assertTrue($settings->enabled());
        $this->assertSame('auto_resolve', $settings->mode());
        $this->assertSame(0.97, $settings->minConfidence());
        $this->assertSame('75.00', $settings->maxPrizeCap());
        $this->assertSame(15, $settings->evidenceWindowMinutes());
        $this->assertSame(4, $settings->maxRunsPerDispute());
    }

    private function configureAiSettings(
        bool $enabled = true,
        string $mode = 'shadow',
        string $minConfidence = '0.95',
        string $maxPrizeCap = '50.00',
        int $windowMinutes = 10,
    ): void {
        app(SystemSettings::class)->save($this->admin, [
            'ai.disputes.enabled' => $enabled ? 'true' : 'false',
            'ai.disputes.mode' => $mode,
            'ai.disputes.min_confidence' => $minConfidence,
            'ai.disputes.max_prize_cap' => $maxPrizeCap,
            'ai.disputes.evidence_window_minutes' => (string) $windowMinutes,
            'ai.disputes.max_runs_per_dispute' => '5',
        ]);
    }

    /**
     * @param  list<string>  $findings
     * @param  list<string>  $redFlags
     */
    private function fakeGeminiResponse(string $suggested, float $confidence, string $summary, array $findings, array $redFlags): void
    {
        Http::swap(new Factory);
        Http::fake([
            '*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'suggested_resolution' => $suggested,
                                        'confidence' => $confidence,
                                        'summary' => $summary,
                                        'evidence_findings' => $findings,
                                        'red_flags' => $redFlags,
                                    ], JSON_THROW_ON_ERROR),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);
    }

    private function runAnalysisJob(int $disputeId): void
    {
        (new AnalyzeDisputeWithAiJob($disputeId))->handle(
            app(AiDisputeSettings::class),
            app(GeminiDisputeAnalyzer::class),
            app(EvaluateAiDisputeAutoResolveAction::class),
        );
    }

    private function uploadBothEvidence(MatchDispute $dispute): void
    {
        app(SubmitEvidenceAction::class)->execute(
            $dispute,
            $this->playerA->id,
            UploadedFile::fake()->create('a.jpg', 50, 'image/jpeg'),
            'Player A evidence upload.'
        );
        app(SubmitEvidenceAction::class)->execute(
            $dispute,
            $this->playerB->id,
            UploadedFile::fake()->create('b.jpg', 50, 'image/jpeg'),
            'Player B evidence upload.'
        );
    }

    /**
     * @return array{tournament: Tournament, match: GameMatch, dispute: MatchDispute, regA: TournamentRegistration, regB: TournamentRegistration}
     */
    private function createDisputedMatch(
        int $maxParticipants = 8,
        int $roundNumber = 1,
        int $totalRounds = 3,
        string $entryFee = '5.00',
        string $prizePool = '25.00',
    ): array {
        $tournament = Tournament::query()->create([
            'uuid' => Str::uuid()->toString(),
            'workflow_version' => 1,
            'game_id' => $this->game->id,
            'competition_type' => CompetitionType::TOURNAMENT,
            'name' => 'AI Dispute Cup '.Str::random(4),
            'slug' => 'ai-dispute-cup-'.Str::lower(Str::random(6)),
            'status' => TournamentStatus::ONGOING,
            'entry_fee' => $entryFee,
            'prize_pool' => $prizePool,
            'max_participants' => $maxParticipants,
            'min_participants' => 2,
            'start_at' => now()->subHour(),
            'created_by' => $this->admin->id,
        ]);

        $regA = $tournament->registrations()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->playerA->id,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'registered_at' => now()->subHours(2),
        ]);
        $regB = $tournament->registrations()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->playerB->id,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'registered_at' => now()->subHours(2),
        ]);

        $bracket = Bracket::query()->create([
            'tournament_id' => $tournament->id,
            'generated_at' => now()->subHour(),
            'created_at' => now()->subHour(),
        ]);

        $targetRound = null;
        $subsequentRounds = [];
        for ($r = 1; $r <= $totalRounds; $r++) {
            $round = Round::query()->create([
                'bracket_id' => $bracket->id,
                'round_number' => $r,
                'created_at' => now()->subHour(),
            ]);
            if ($r === $roundNumber) {
                $targetRound = $round;
            } elseif ($r > $roundNumber) {
                $subsequentRounds[] = $round;
            }
        }

        $match = GameMatch::query()->create([
            'uuid' => Str::uuid()->toString(),
            'tournament_id' => $tournament->id,
            'round_id' => $targetRound->id,
            'player_a_registration_id' => $regA->id,
            'player_b_registration_id' => $regB->id,
            'status' => MatchStatus::DISPUTED,
            'started_at' => now()->subMinutes(30),
        ]);

        foreach ($subsequentRounds as $futureRound) {
            GameMatch::query()->create([
                'uuid' => Str::uuid()->toString(),
                'tournament_id' => $tournament->id,
                'round_id' => $futureRound->id,
                'status' => MatchStatus::PENDING,
            ]);
        }

        $dispute = MatchDispute::query()->create([
            'uuid' => Str::uuid()->toString(),
            'match_id' => $match->id,
            'opened_by' => $this->playerA->id,
            'status' => DisputeStatus::OPEN,
            'reason' => 'Opponent reported the wrong winner.',
        ]);

        return [
            'tournament' => $tournament,
            'match' => $match,
            'dispute' => $dispute,
            'regA' => $regA,
            'regB' => $regB,
        ];
    }
}
