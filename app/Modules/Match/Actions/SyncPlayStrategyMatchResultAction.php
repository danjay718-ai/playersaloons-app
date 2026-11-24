<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Match\Events\MatchCompleted;
use App\Modules\Match\Events\MatchDisputed;
use App\Modules\Match\Events\MatchRematchCreated;
use App\Modules\Match\Exceptions\PlayStrategyException;
use App\Modules\Match\Exceptions\PlayStrategyRateLimitException;
use App\Modules\Match\Exceptions\PlayStrategyUnavailableException;
use App\Modules\Match\Models\ExternalMatchGame;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchAttempt;
use App\Modules\Match\Models\MatchDispute;
use App\Modules\Match\Services\PlayStrategyClient;
use App\Modules\Match\StateMachines\MatchStateMachine;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\DisputeStatus;
use App\Shared\Enums\MatchStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SyncPlayStrategyMatchResultAction
{
    private const ONGOING_STATUSES = ['created', 'started'];

    private const DECISIVE_STATUSES = ['mate', 'resign', 'outoftime', 'timeout', 'variantend'];

    private const DRAW_STATUSES = ['draw', 'stalemate', 'insufficientmaterialclaim', 'threefoldrepetition', 'fiftymove'];

    private const ABORTED_OR_AMBIGUOUS_STATUSES = ['aborted', 'nostart', 'cheat', 'unknownfinish'];

    public function __construct(
        private readonly PlayStrategyClient $client,
        private readonly MatchStateMachine $stateMachine,
    ) {}

    /**
     * Poll PlayStrategy for the active external game, validate every invariant,
     * and atomically advance or escalate the match.
     *
     * Missing provider data or API outages NEVER automatically award a winner.
     */
    public function execute(int $externalMatchGameId): ?ExternalMatchGame
    {
        $record = ExternalMatchGame::query()
            ->with([
                'match.tournament.game',
                'match.playerARegistration.user.playStrategyAccount',
            ])
            ->find($externalMatchGameId);

        if ($record === null
            || $record->external_game_id === null
            || $record->status !== ExternalMatchGame::STATUS_CREATED) {
            return $record;
        }

        // Ignore stale attempt polling immediately.
        if ((int) $record->match->active_attempt_number !== (int) $record->attempt_number
            || in_array($record->match->status, [MatchStatus::COMPLETED, MatchStatus::FORFEITED], true)) {
            $record->update(['next_poll_at' => null]);

            return $record->fresh();
        }

        $bearerToken = $record->match->playerARegistration?->user?->playStrategyAccount?->access_token;

        try {
            $payload = $this->client->fetchGame($record->external_game_id, $bearerToken);
        } catch (PlayStrategyRateLimitException $e) {
            return $this->scheduleBackoff($record, $e->retryAfterSeconds, $e->getMessage());
        } catch (PlayStrategyUnavailableException|PlayStrategyException $e) {
            $backoff = min(300, 15 * (2 ** min(5, $record->poll_attempts)));

            return $this->scheduleBackoff($record, $backoff, $e->getMessage());
        }

        $validationError = $this->validatePayload($record, $payload);
        if ($validationError !== null) {
            return $this->escalateToAdminReview($record, $payload, $validationError);
        }

        $providerStatus = strtolower(trim((string) ($payload['status'] ?? '')));
        if ($providerStatus === '' || in_array($providerStatus, self::ONGOING_STATUSES, true)) {
            $record->update([
                'provider_status' => $providerStatus ?: 'started',
                'last_polled_at' => now(),
                'next_poll_at' => now()->addSeconds(20),
                'last_error' => null,
                'payload_json' => $payload,
            ]);

            return $record->fresh();
        }

        if (in_array($providerStatus, self::DRAW_STATUSES, true)) {
            return $this->applyDraw($record, $payload, $providerStatus);
        }

        if (in_array($providerStatus, self::ABORTED_OR_AMBIGUOUS_STATUSES, true)) {
            return $this->escalateToAdminReview(
                $record,
                $payload,
                "PlayStrategy game ended with status '{$providerStatus}'. Admin review is required.",
            );
        }

        if (! in_array($providerStatus, self::DECISIVE_STATUSES, true)) {
            return $this->escalateToAdminReview(
                $record,
                $payload,
                "PlayStrategy returned unrecognized final status '{$providerStatus}'. Admin review is required.",
            );
        }

        $winnerExternalId = $this->resolveWinnerExternalId($payload);
        if ($winnerExternalId === null) {
            return $this->escalateToAdminReview(
                $record,
                $payload,
                "PlayStrategy reported final status '{$providerStatus}' without an identifiable winner. Admin review is required.",
            );
        }

        return $this->applyDecisiveCompletion($record, $payload, $providerStatus, $winnerExternalId);
    }

    /**
     * Validate game ID, participants, variant, and rated settings before accepting any result.
     *
     * @param  array<string, mixed>  $payload
     */
    private function validatePayload(ExternalMatchGame $record, array $payload): ?string
    {
        $returnedId = trim((string) ($payload['id'] ?? ''));
        if ($returnedId === '' || $returnedId !== $record->external_game_id) {
            return "PlayStrategy returned mismatched game ID ('{$returnedId}' vs '{$record->external_game_id}').";
        }

        $rawVariant = $payload['variant'] ?? '';
        $returnedVariant = is_array($rawVariant)
            ? strtolower(trim((string) ($rawVariant['key'] ?? '')))
            : strtolower(trim((string) $rawVariant));
        $expectedVariant = strtolower(trim($record->provider_variant));
        $normalizeVariant = static fn (string $v): string => match ($v) {
            'chess' => 'standard',
            'draughts' => 'international',
            default => $v,
        };
        if ($returnedVariant !== '' && $normalizeVariant($returnedVariant) !== $normalizeVariant($expectedVariant)) {
            return "PlayStrategy game variant mismatch ('{$returnedVariant}' vs '{$record->provider_variant}').";
        }

        $expectedSettings = is_array($record->expected_settings_json) ? $record->expected_settings_json : [];
        if (array_key_exists('rated', $expectedSettings) && array_key_exists('rated', $payload)) {
            if ((bool) $payload['rated'] !== (bool) $expectedSettings['rated']) {
                return 'PlayStrategy game rated setting did not match tournament configuration.';
            }
        }

        $participants = $this->participantMap($payload);
        $expectedA = strtolower((string) $record->player_a_external_user_id);
        $expectedB = strtolower((string) $record->player_b_external_user_id);

        if ($expectedA === '' || $expectedB === '') {
            return 'Expected PlayStrategy participants are missing on this match record.';
        }

        $actualIds = array_values($participants);
        sort($actualIds);
        $expectedIds = [$expectedA, $expectedB];
        sort($expectedIds);

        if ($actualIds !== $expectedIds) {
            return 'PlayStrategy game participants do not match the assigned tournament players.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string> side => normalized PlayStrategy user ID
     */
    private function participantMap(array $payload): array
    {
        $players = is_array($payload['players'] ?? null) ? $payload['players'] : [];
        $map = [];

        foreach (['p1', 'p2', 'white', 'black'] as $side) {
            $node = $players[$side] ?? null;
            if (! is_array($node)) {
                continue;
            }
            $id = strtolower(trim((string) ($node['user']['id'] ?? $node['user']['name'] ?? $node['userId'] ?? '')));
            if ($id !== '') {
                $map[$side] = $id;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveWinnerExternalId(array $payload): ?string
    {
        $winnerSide = strtolower(trim((string) ($payload['winner'] ?? '')));
        if ($winnerSide === '') {
            return null;
        }

        $participants = $this->participantMap($payload);

        return $participants[$winnerSide] ?? null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyDecisiveCompletion(
        ExternalMatchGame $record,
        array $payload,
        string $providerStatus,
        string $winnerExternalId,
    ): ExternalMatchGame {
        DB::transaction(function () use ($record, $payload, $providerStatus, $winnerExternalId): void {
            // Existing lock order: Tournament → GameMatch → MatchAttempt → ExternalMatchGame.
            $tournamentId = GameMatch::query()->whereKey($record->match_id)->value('tournament_id');
            Tournament::query()->lockForUpdate()->findOrFail($tournamentId);

            $match = GameMatch::query()->lockForUpdate()->findOrFail($record->match_id);
            $lockedRecord = ExternalMatchGame::query()->lockForUpdate()->findOrFail($record->id);

            if ($lockedRecord->status !== ExternalMatchGame::STATUS_CREATED
                || in_array($match->status, [MatchStatus::COMPLETED, MatchStatus::FORFEITED], true)
                || (int) $match->active_attempt_number !== (int) $lockedRecord->attempt_number) {
                $lockedRecord->update(['next_poll_at' => null]);

                return;
            }

            $attempt = MatchAttempt::query()
                ->where('match_id', $match->id)
                ->where('attempt_number', $lockedRecord->attempt_number)
                ->lockForUpdate()
                ->first();

            if ($attempt !== null && $attempt->status !== 'open') {
                $lockedRecord->update(['next_poll_at' => null]);

                return;
            }

            $winnerRegistrationId = match ($winnerExternalId) {
                strtolower((string) $lockedRecord->player_a_external_user_id) => (int) $match->player_a_registration_id,
                strtolower((string) $lockedRecord->player_b_external_user_id) => (int) $match->player_b_registration_id,
                default => 0,
            };

            if ($winnerRegistrationId <= 0) {
                return;
            }

            $lockedRecord->update([
                'status' => ExternalMatchGame::STATUS_COMPLETED,
                'provider_status' => $providerStatus,
                'provider_winner_side' => strtolower((string) ($payload['winner'] ?? '')),
                'last_polled_at' => now(),
                'next_poll_at' => null,
                'last_error' => null,
                'payload_json' => $payload,
            ]);

            $attempt?->update([
                'status' => 'resolved',
                'resolution' => 'playstrategy_verified',
                'resolved_at' => now(),
            ]);

            $match->forceFill([
                'winner_registration_id' => $winnerRegistrationId,
                'stalled_deadline_at' => null,
                'round_deadline_at' => null,
                'final_resolution_eligible_at' => null,
                'resolution_reason' => 'playstrategy_verified',
            ]);

            if ($match->status === MatchStatus::READY) {
                $this->stateMachine->transition($match, MatchStatus::IN_PROGRESS);
            }

            $this->stateMachine->transition($match, MatchStatus::COMPLETED);

            MatchCompleted::dispatch((int) $match->id, (int) $match->tournament_id, $winnerRegistrationId);
        }, 3);

        return $record->fresh() ?? $record;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyDraw(
        ExternalMatchGame $record,
        array $payload,
        string $providerStatus,
    ): ExternalMatchGame {
        DB::transaction(function () use ($record, $payload, $providerStatus): void {
            $tournamentId = GameMatch::query()->whereKey($record->match_id)->value('tournament_id');
            Tournament::query()->lockForUpdate()->findOrFail($tournamentId);

            $match = GameMatch::query()->lockForUpdate()->findOrFail($record->match_id);
            $lockedRecord = ExternalMatchGame::query()->lockForUpdate()->findOrFail($record->id);

            if ($lockedRecord->status !== ExternalMatchGame::STATUS_CREATED
                || in_array($match->status, [MatchStatus::COMPLETED, MatchStatus::FORFEITED], true)
                || (int) $match->active_attempt_number !== (int) $lockedRecord->attempt_number) {
                $lockedRecord->update(['next_poll_at' => null]);

                return;
            }

            $attempt = MatchAttempt::query()
                ->where('match_id', $match->id)
                ->where('attempt_number', $lockedRecord->attempt_number)
                ->lockForUpdate()
                ->first();

            $lockedRecord->update([
                'status' => ExternalMatchGame::STATUS_DRAW,
                'provider_status' => $providerStatus,
                'last_polled_at' => now(),
                'next_poll_at' => null,
                'last_error' => null,
                'payload_json' => $payload,
            ]);

            $attempt?->update([
                'status' => 'rematch',
                'resolution' => 'playstrategy_draw',
                'resolved_at' => now(),
            ]);

            $nextAttempt = $lockedRecord->attempt_number + 1;
            $match->forceFill([
                'status' => MatchStatus::IN_PROGRESS,
                'active_attempt_number' => $nextAttempt,
                'result_submitted_at' => null,
                'resolution_reason' => 'playstrategy_draw_rematch',
            ])->save();

            MatchAttempt::query()->firstOrCreate(
                ['match_id' => $match->id, 'attempt_number' => $nextAttempt],
                ['uuid' => Str::uuid()->toString(), 'status' => 'open'],
            );

            // Listener dispatches CreatePlayStrategyMatchGameJob::afterCommit() for the new attempt.
            MatchRematchCreated::dispatch($match->id, $match->id, $match->uuid, $match->uuid);
        }, 3);

        return $record->fresh() ?? $record;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function escalateToAdminReview(
        ExternalMatchGame $record,
        array $payload,
        string $reason,
    ): ExternalMatchGame {
        DB::transaction(function () use ($record, $payload, $reason): void {
            $tournamentId = GameMatch::query()->whereKey($record->match_id)->value('tournament_id');
            Tournament::query()->lockForUpdate()->findOrFail($tournamentId);

            $match = GameMatch::query()->with('playerARegistration')->lockForUpdate()->findOrFail($record->match_id);
            $lockedRecord = ExternalMatchGame::query()->lockForUpdate()->findOrFail($record->id);

            if (in_array($match->status, [MatchStatus::COMPLETED, MatchStatus::FORFEITED], true)) {
                $lockedRecord->update(['next_poll_at' => null]);

                return;
            }

            $lockedRecord->update([
                'status' => ExternalMatchGame::STATUS_NEEDS_ADMIN_REVIEW,
                'provider_status' => strtolower(trim((string) ($payload['status'] ?? ''))) ?: null,
                'last_polled_at' => now(),
                'next_poll_at' => null,
                'last_error' => $reason,
                'payload_json' => $payload,
            ]);

            $match->attempts()
                ->where('attempt_number', $lockedRecord->attempt_number)
                ->where('status', 'open')
                ->update([
                    'status' => 'disputed',
                    'resolution' => 'playstrategy_admin_review',
                    'resolved_at' => now(),
                    'updated_at' => now(),
                ]);

            $dispute = MatchDispute::query()->firstOrCreate(
                ['match_id' => $match->id, 'status' => DisputeStatus::OPEN],
                [
                    'uuid' => Str::uuid()->toString(),
                    'opened_by' => $match->playerARegistration?->user_id,
                    'reason' => $reason,
                ],
            );

            $match->resolution_reason = 'playstrategy_admin_review';
            if ($match->status !== MatchStatus::DISPUTED && $this->stateMachine->can($match->status, MatchStatus::DISPUTED)) {
                $this->stateMachine->transition($match, MatchStatus::DISPUTED);
            } else {
                $match->status = MatchStatus::DISPUTED;
                $match->save();
            }

            MatchDisputed::dispatch($match->id, $dispute->id, (int) $dispute->opened_by);
        }, 3);

        return $record->fresh() ?? $record;
    }

    private function scheduleBackoff(ExternalMatchGame $record, int $delaySeconds, string $error): ExternalMatchGame
    {
        $record->update([
            'poll_attempts' => $record->poll_attempts + 1,
            'last_polled_at' => now(),
            'next_poll_at' => now()->addSeconds(max(10, $delaySeconds)),
            'last_error' => $error,
        ]);

        return $record->fresh() ?? $record;
    }
}
