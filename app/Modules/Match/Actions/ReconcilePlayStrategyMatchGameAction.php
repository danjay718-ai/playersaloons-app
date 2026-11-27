<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Community\Services\NotificationService;
use App\Modules\Identity\Models\ExternalProviderAccount;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Exceptions\PlayStrategyRateLimitException;
use App\Modules\Match\Exceptions\PlayStrategyUnavailableException;
use App\Modules\Match\Jobs\ReconcilePlayStrategyMatchGameJob;
use App\Modules\Match\Models\ExternalMatchGame;
use App\Modules\Match\Services\PlayStrategyClient;
use Illuminate\Support\Facades\DB;

final class ReconcilePlayStrategyMatchGameAction
{
    private const MAX_RECONCILE_POLLS = 3;

    public function __construct(
        private readonly PlayStrategyClient $client,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Reconcile an uncertain PlayStrategy game creation before ever creating another.
     * If reconciliation cannot unambiguously identify the created game, require admin review.
     */
    public function execute(int $externalMatchGameId): ?ExternalMatchGame
    {
        $record = ExternalMatchGame::query()
            ->with([
                'match.tournament',
                'match.playerARegistration.user.playStrategyAccount',
                'match.playerBRegistration.user.playStrategyAccount',
            ])
            ->find($externalMatchGameId);

        if ($record === null || $record->status !== ExternalMatchGame::STATUS_RECONCILING) {
            return $record;
        }

        if ($record->external_game_id !== null) {
            $record->update([
                'status' => ExternalMatchGame::STATUS_CREATED,
                'next_poll_at' => now()->addSeconds(20),
            ]);

            return $record->fresh();
        }

        $accountA = $record->match->playerARegistration?->user?->playStrategyAccount;
        $accountB = $record->match->playerBRegistration?->user?->playStrategyAccount;

        if (! $accountA instanceof ExternalProviderAccount || ! $accountB instanceof ExternalProviderAccount) {
            return $this->requireAdminReview($record, 'Participant PlayStrategy account is missing during reconciliation.');
        }

        $sinceMs = (($record->creation_started_at ?? $record->created_at ?? now())->copy()->subSeconds(60)->timestamp) * 1000;

        try {
            $candidates = $this->client->findRecentGamesBetween(
                $accountA->external_username,
                $accountB->external_username,
                $sinceMs,
                $accountA->access_token,
            );
        } catch (PlayStrategyRateLimitException $e) {
            return $this->scheduleRetryOrReview($record, $e->retryAfterSeconds, $e->getMessage());
        } catch (PlayStrategyUnavailableException $e) {
            $backoff = min(120, 15 * (2 ** min(4, $record->poll_attempts)));

            return $this->scheduleRetryOrReview($record, $backoff, $e->getMessage());
        }

        $matching = [];
        $expectedPlayers = [
            strtolower((string) $record->player_a_external_user_id),
            strtolower((string) $record->player_b_external_user_id),
        ];
        sort($expectedPlayers);

        foreach ($candidates as $candidate) {
            $candidateId = trim((string) ($candidate['id'] ?? ''));
            if ($candidateId === '') {
                continue;
            }

            $rawVariant = $candidate['variant'] ?? '';
            $variant = is_array($rawVariant)
                ? strtolower(trim((string) ($rawVariant['key'] ?? '')))
                : strtolower(trim((string) $rawVariant));
            $normalizeVariant = static fn (string $v): string => match ($v) {
                'chess' => 'standard',
                'draughts' => 'international',
                default => $v,
            };
            if ($variant !== '' && $normalizeVariant($variant) !== $normalizeVariant(strtolower($record->provider_variant))) {
                continue;
            }

            $players = $this->extractPlayerIds($candidate);
            if ($players !== $expectedPlayers) {
                continue;
            }

            $alreadyMapped = ExternalMatchGame::query()
                ->where('provider', $record->provider)
                ->where('external_game_id', $candidateId)
                ->whereKeyNot($record->id)
                ->exists();

            if ($alreadyMapped) {
                continue;
            }

            $matching[] = $candidate;
        }

        if (count($matching) === 1) {
            $matched = $matching[0];
            $gameId = (string) $matched['id'];
            $playUrl = trim((string) ($matched['url'] ?? ($this->client->baseUrl().'/'.$gameId)));

            DB::transaction(function () use ($record, $gameId, $playUrl, $matched): void {
                $locked = ExternalMatchGame::query()->lockForUpdate()->findOrFail($record->id);
                if ($locked->status !== ExternalMatchGame::STATUS_RECONCILING) {
                    return;
                }

                $locked->update([
                    'external_game_id' => $gameId,
                    'play_url' => $playUrl,
                    'status' => ExternalMatchGame::STATUS_CREATED,
                    'provider_status' => (string) ($matched['status'] ?? 'started'),
                    'last_polled_at' => now(),
                    'next_poll_at' => now()->addSeconds(20),
                    'last_error' => null,
                    'payload_json' => $matched,
                ]);
            });

            return $record->fresh();
        }

        $reason = count($matching) === 0
            ? 'Reconciliation could not find a created PlayStrategy game after timeout. Admin review required before creating another.'
            : 'Reconciliation found multiple ambiguous PlayStrategy games for these participants. Admin review required.';

        return $this->requireAdminReview($record, $reason);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function extractPlayerIds(array $payload): array
    {
        $players = is_array($payload['players'] ?? null) ? $payload['players'] : [];
        $ids = [];

        foreach (['p1', 'p2', 'white', 'black'] as $side) {
            $node = $players[$side] ?? null;
            if (! is_array($node)) {
                continue;
            }
            $id = strtolower(trim((string) ($node['user']['id'] ?? $node['user']['name'] ?? $node['userId'] ?? '')));
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    private function scheduleRetryOrReview(ExternalMatchGame $record, int $delaySeconds, string $error): ExternalMatchGame
    {
        $attempts = $record->poll_attempts + 1;
        if ($attempts >= self::MAX_RECONCILE_POLLS) {
            return $this->requireAdminReview($record, "PlayStrategy reconciliation failed after {$attempts} attempts: {$error}");
        }

        $record->update([
            'poll_attempts' => $attempts,
            'last_polled_at' => now(),
            'next_poll_at' => now()->addSeconds($delaySeconds),
            'last_error' => $error,
        ]);

        ReconcilePlayStrategyMatchGameJob::dispatch($record->id)->delay(now()->addSeconds($delaySeconds));

        return $record->fresh() ?? $record;
    }

    private function requireAdminReview(ExternalMatchGame $record, string $reason): ExternalMatchGame
    {
        $record->update([
            'status' => ExternalMatchGame::STATUS_NEEDS_ADMIN_REVIEW,
            'last_polled_at' => now(),
            'next_poll_at' => null,
            'last_error' => $reason,
        ]);

        $match = $record->match;
        User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'TOURNAMENT_ORGANIZER']))
            ->with('notificationPreference')
            ->select(['id', 'uuid', 'email', 'username'])
            ->chunkById(100, function ($admins) use ($match, $record, $reason): void {
                foreach ($admins as $admin) {
                    $this->notifications->send(
                        $admin,
                        'playstrategy_review_required',
                        'PlayStrategy Reconciliation Needs Admin Review',
                        "Match #{$match->id} (Attempt #{$record->attempt_number}): {$reason}",
                        "/matches/{$match->uuid}",
                    );
                }
            });

        return $record->fresh() ?? $record;
    }
}
