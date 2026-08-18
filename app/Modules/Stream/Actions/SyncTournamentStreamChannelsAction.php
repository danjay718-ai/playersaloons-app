<?php

declare(strict_types=1);

namespace App\Modules\Stream\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Stream\Models\StreamChannel;
use App\Modules\Tournament\Models\Tournament;

final class SyncTournamentStreamChannelsAction
{
    /**
     * Synchronize the supported provider URLs for one competition.
     *
     * Keeping this outside Livewire lets recurring generation use the same
     * idempotent write path and prevents provider-specific persistence logic
     * from being duplicated across UI and scheduler callers.
     *
     * @param  array<string, string|null>  $providerUrls
     */
    public function execute(Tournament $tournament, array $providerUrls, ?User $actor = null): void
    {
        foreach (['youtube', 'twitch', 'facebook'] as $provider) {
            $this->syncProvider($tournament, $provider, $providerUrls[$provider] ?? null, $actor);
        }
    }

    private function syncProvider(Tournament $tournament, string $provider, ?string $url, ?User $actor): void
    {
        $url = is_string($url) && trim($url) !== '' ? trim($url) : null;
        $existing = StreamChannel::query()
            ->where('tournament_id', $tournament->getKey())
            ->whereNull('user_id')
            ->where('provider', $provider)
            ->first();

        if ($url === null) {
            if ($existing !== null) {
                $properties = [
                    'provider' => $existing->provider,
                    'source_url' => $existing->source_url,
                    'tournament_id' => $tournament->getKey(),
                ];
                $existing->delete();

                activity()->causedBy($actor)->performedOn($tournament)
                    ->withProperties($properties)->log('tournament_stream_removed');
            }

            return;
        }

        $streamChannel = $existing ?? new StreamChannel([
            'tournament_id' => $tournament->getKey(),
            'provider' => $provider,
        ]);
        $streamChannel->fill([
            'source_url' => $url,
            'title' => $tournament->name,
            'is_public' => true,
        ]);
        $changes = $streamChannel->getDirty();

        if ($changes === []) {
            return;
        }

        $isNew = ! $streamChannel->exists;
        $streamChannel->save();

        activity()->causedBy($actor)->performedOn($tournament)
            ->withProperties([
                'provider' => $provider,
                'stream_channel_id' => $streamChannel->getKey(),
                'changes' => $changes,
            ])->log($isNew ? 'tournament_stream_created' : 'tournament_stream_updated');
    }
}
