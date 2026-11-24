<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Community\Services\NotificationService;
use App\Modules\Identity\Models\ExternalProviderAccount;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Events\BroadcastMatchUpdated;
use App\Modules\Match\Exceptions\PlayStrategyException;
use App\Modules\Match\Exceptions\PlayStrategyRateLimitException;
use App\Modules\Match\Exceptions\PlayStrategyUnavailableException;
use App\Modules\Match\Exceptions\PlayStrategyUncertainOutcomeException;
use App\Modules\Match\Jobs\ReconcilePlayStrategyMatchGameJob;
use App\Modules\Match\Models\ExternalMatchGame;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchAttempt;
use App\Modules\Match\Services\PlayStrategyClient;
use App\Shared\Enums\MatchStatus;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreatePlayStrategyMatchGameAction
{
    public function __construct(
        private readonly PlayStrategyClient $client,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Allow at most one PlayStrategy creation operation per (match_id, attempt_number).
     * External HTTP calls ALWAYS run outside any database transaction.
     */
    public function execute(int $matchId, ?int $attemptNumber = null): ?ExternalMatchGame
    {
        if (! config('features.playstrategy.enabled')) {
            return null;
        }

        $prepared = DB::transaction(function () use ($matchId, $attemptNumber): ?array {
            /** @var GameMatch|null $match */
            $match = GameMatch::query()
                ->with([
                    'tournament.game',
                    'playerARegistration.user.playStrategyAccount',
                    'playerBRegistration.user.playStrategyAccount',
                ])
                ->lockForUpdate()
                ->find($matchId);

            if ($match === null || ! $match->isProviderManaged()) {
                return null;
            }

            if (! in_array($match->status, [MatchStatus::READY, MatchStatus::IN_PROGRESS], true)) {
                return null;
            }

            $targetAttempt = $attemptNumber ?? max(1, (int) ($match->active_attempt_number ?? 1));
            if ($targetAttempt !== max(1, (int) ($match->active_attempt_number ?? 1))) {
                return null;
            }

            $attempt = MatchAttempt::query()->firstOrCreate(
                ['match_id' => $match->id, 'attempt_number' => $targetAttempt],
                ['uuid' => Str::uuid()->toString(), 'status' => 'open'],
            );

            $existing = ExternalMatchGame::query()
                ->where('match_id', $match->id)
                ->where('attempt_number', $targetAttempt)
                ->lockForUpdate()
                ->first();

            // Strictly one creation operation per match attempt: never blindly recreate.
            if ($existing !== null) {
                return null;
            }

            $game = $match->tournament->game;
            $settings = $game->playStrategySettings();
            $accountA = $match->playerARegistration?->user?->playStrategyAccount;
            $accountB = $match->playerBRegistration?->user?->playStrategyAccount;

            if (! $accountA instanceof ExternalProviderAccount || ! $accountB instanceof ExternalProviderAccount) {
                $record = ExternalMatchGame::query()->create([
                    'uuid' => Str::uuid()->toString(),
                    'match_id' => $match->id,
                    'match_attempt_id' => $attempt->id,
                    'attempt_number' => $targetAttempt,
                    'provider' => ExternalProviderAccount::PROVIDER_PLAYSTRATEGY,
                    'provider_variant' => $settings['variant'],
                    'expected_settings_json' => $settings,
                    'player_a_external_user_id' => $accountA?->external_user_id,
                    'player_b_external_user_id' => $accountB?->external_user_id,
                    'status' => ExternalMatchGame::STATUS_NEEDS_ADMIN_REVIEW,
                    'last_error' => 'One or both participants do not have a verified PlayStrategy OAuth account linked.',
                ]);

                return ['record' => $record, 'skip_remote' => true, 'match_uuid' => $match->uuid];
            }

            if ($accountA->isTokenExpired() || $accountB->isTokenExpired()) {
                $record = ExternalMatchGame::query()->create([
                    'uuid' => Str::uuid()->toString(),
                    'match_id' => $match->id,
                    'match_attempt_id' => $attempt->id,
                    'attempt_number' => $targetAttempt,
                    'provider' => ExternalProviderAccount::PROVIDER_PLAYSTRATEGY,
                    'provider_variant' => $settings['variant'],
                    'expected_settings_json' => $settings,
                    'player_a_external_user_id' => $accountA->external_user_id,
                    'player_b_external_user_id' => $accountB->external_user_id,
                    'status' => ExternalMatchGame::STATUS_NEEDS_ADMIN_REVIEW,
                    'last_error' => 'A participant PlayStrategy OAuth token has expired. Admin review or player reconnection is required.',
                ]);

                return ['record' => $record, 'skip_remote' => true, 'match_uuid' => $match->uuid];
            }

            $record = ExternalMatchGame::query()->create([
                'uuid' => Str::uuid()->toString(),
                'match_id' => $match->id,
                'match_attempt_id' => $attempt->id,
                'attempt_number' => $targetAttempt,
                'provider' => ExternalProviderAccount::PROVIDER_PLAYSTRATEGY,
                'provider_variant' => $settings['variant'],
                'expected_settings_json' => $settings,
                'player_a_external_user_id' => $accountA->external_user_id,
                'player_b_external_user_id' => $accountB->external_user_id,
                'status' => ExternalMatchGame::STATUS_CREATING,
                'creation_started_at' => now(),
            ]);

            return [
                'record' => $record,
                'skip_remote' => false,
                'match_uuid' => $match->uuid,
                'tournament_name' => $match->tournament->name,
                'player_a_token' => $accountA->access_token,
                'player_b_token' => $accountB->access_token,
                'player_b_username' => $accountB->external_username,
                'settings' => $settings,
            ];
        }, 3);

        if ($prepared === null) {
            return null;
        }

        /** @var ExternalMatchGame $record */
        $record = $prepared['record'];

        if ($prepared['skip_remote']) {
            $this->notifyAdminsForReview($record);

            return $record;
        }

        try {
            $created = $this->client->createChallengeGame(
                $prepared['player_a_token'],
                $prepared['player_b_username'],
                $prepared['player_b_token'],
                $prepared['settings'],
                "Your GamersRival match ({$prepared['tournament_name']}) is ready: {game}",
            );

            try {
                $record->update([
                    'external_game_id' => $created['id'],
                    'play_url' => $created['url'],
                    'status' => ExternalMatchGame::STATUS_CREATED,
                    'provider_status' => (string) ($created['payload']['status'] ?? 'started'),
                    'next_poll_at' => now()->addSeconds(20),
                    'last_error' => null,
                    'payload_json' => $created['payload'],
                ]);
            } catch (UniqueConstraintViolationException) {
                $record->update([
                    'status' => ExternalMatchGame::STATUS_NEEDS_ADMIN_REVIEW,
                    'last_error' => "Duplicate external PlayStrategy game ID ({$created['id']}) detected. Admin review required.",
                ]);
                $this->notifyAdminsForReview($record);

                return $record->fresh();
            }

            try {
                broadcast(new BroadcastMatchUpdated($prepared['match_uuid'], 'playstrategy_game_created'));
            } catch (\Throwable) {
                // Realtime update is best-effort; match room polls as fallback.
            }

            return $record->fresh();
        } catch (PlayStrategyUncertainOutcomeException $e) {
            // Do NOT blindly retry game creation! Reconcile first.
            $record->update([
                'status' => ExternalMatchGame::STATUS_RECONCILING,
                'last_error' => $e->getMessage(),
                'next_poll_at' => now()->addSeconds(5),
            ]);

            ReconcilePlayStrategyMatchGameJob::dispatch($record->id)->delay(now()->addSeconds(5));

            return $record->fresh();
        } catch (PlayStrategyRateLimitException $e) {
            // Reconcile first after rate-limit cooldown rather than blindly double-creating.
            $record->update([
                'status' => ExternalMatchGame::STATUS_RECONCILING,
                'last_error' => $e->getMessage(),
                'next_poll_at' => now()->addSeconds($e->retryAfterSeconds),
            ]);

            ReconcilePlayStrategyMatchGameJob::dispatch($record->id)->delay(now()->addSeconds($e->retryAfterSeconds));

            return $record->fresh();
        } catch (PlayStrategyUnavailableException|PlayStrategyException $e) {
            $record->update([
                'status' => ExternalMatchGame::STATUS_NEEDS_ADMIN_REVIEW,
                'last_error' => $e->getMessage(),
            ]);

            $this->notifyAdminsForReview($record);

            return $record->fresh();
        }
    }

    private function notifyAdminsForReview(ExternalMatchGame $record): void
    {
        $match = $record->match()->with('tournament')->first();
        if ($match === null) {
            return;
        }

        User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'TOURNAMENT_ORGANIZER']))
            ->with('notificationPreference')
            ->select(['id', 'uuid', 'email', 'username'])
            ->chunkById(100, function ($admins) use ($match, $record): void {
                foreach ($admins as $admin) {
                    $this->notifications->send(
                        $admin,
                        'playstrategy_review_required',
                        'PlayStrategy Match Needs Admin Review',
                        "Match #{$match->id} (Attempt #{$record->attempt_number}) requires PlayStrategy review: {$record->last_error}",
                        "/matches/{$match->uuid}",
                    );
                }
            });
    }
}
