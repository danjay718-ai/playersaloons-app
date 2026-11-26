<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\GameCategory;
use App\Modules\CMS\Models\Platform;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Reusable active tournament finder for both Livewire Quick-Finder and future /api/v2/tournaments/find.
 */
final class FindActiveTournamentsService
{
    private const CACHE_TTL_SECONDS = 60;

    /**
     * @param  array{
     *     search?: string,
     *     category?: string,
     *     game_id?: string|int,
     *     platform_id?: string|int,
     *     budget?: string,
     *     time_window?: string,
     *     competition_type?: string
     * }  $criteria
     * @return array{
     *     exact: bool,
     *     fallback_reason: ?string,
     *     tournaments: Collection<int, Tournament>,
     *     total_matching: int
     * }
     */
    public function find(array $criteria = [], int $limit = 8): array
    {
        $limit = max(1, min(24, $limit));
        $normalized = $this->normalizeCriteria($criteria);

        $exactCandidates = $this->fetchOpenRanked($normalized);
        if ($exactCandidates->isNotEmpty() || ! $this->hasActiveFilter($normalized)) {
            return [
                'exact' => true,
                'fallback_reason' => null,
                'tournaments' => $exactCandidates->take($limit)->values(),
                'total_matching' => $exactCandidates->count(),
            ];
        }

        // Relaxed fallback chain: preserve game first, then category, then any open event.
        if ($normalized['game_id'] !== '') {
            $sameGame = $this->fetchOpenRanked(['game_id' => $normalized['game_id']]);
            if ($sameGame->isNotEmpty()) {
                return [
                    'exact' => false,
                    'fallback_reason' => 'same_game',
                    'tournaments' => $sameGame->take($limit)->values(),
                    'total_matching' => $sameGame->count(),
                ];
            }
        }

        if ($normalized['category'] !== '') {
            $sameCategory = $this->fetchOpenRanked(['category' => $normalized['category']]);
            if ($sameCategory->isNotEmpty()) {
                return [
                    'exact' => false,
                    'fallback_reason' => 'same_category',
                    'tournaments' => $sameCategory->take($limit)->values(),
                    'total_matching' => $sameCategory->count(),
                ];
            }
        }

        $closestOpen = $this->fetchOpenRanked([]);

        return [
            'exact' => $closestOpen->isEmpty(),
            'fallback_reason' => $closestOpen->isNotEmpty() ? 'closest_open' : null,
            'tournaments' => $closestOpen->take($limit)->values(),
            'total_matching' => $closestOpen->count(),
        ];
    }

    /**
     * Returns plain scalar arrays so cached options stay safe when cache.serializable_classes is false.
     *
     * @return array{
     *     categories: list<array{id: int, slug: string, name: string}>,
     *     games: list<array{id: int, slug: string, name: string, category_slug: ?string}>,
     *     platforms: list<array{id: int, name: string}>
     * }
     */
    public function filterOptions(?string $categorySlug = null): array
    {
        $categorySlug = trim((string) $categorySlug);
        $locale = app()->getLocale();

        /** @var list<array{id: int, slug: string, name: string}> $categories */
        $categories = $this->remember('find_tournament.categories.v2', static fn (): array => GameCategory::query()
            ->active()
            ->ordered()
            ->get(['id', 'slug', 'name'])
            ->map(static fn (GameCategory $cat): array => [
                'id' => (int) $cat->id,
                'slug' => (string) $cat->slug,
                'name' => (string) $cat->name,
            ])
            ->values()
            ->all());

        /** @var list<array{id: int, slug: string, name: string, category_slug: ?string}> $allGames */
        $allGames = $this->remember('find_tournament.games.v2.'.$locale, static fn (): array => Game::query()
            ->availableInCatalog()
            ->with(['translations', 'category:id,slug'])
            ->orderBy('slug')
            ->get(['id', 'slug', 'game_category_id'])
            ->map(static fn (Game $game): array => [
                'id' => (int) $game->id,
                'slug' => (string) $game->slug,
                'name' => $game->localizedName($locale),
                'category_slug' => $game->category->slug,
            ])
            ->values()
            ->all());

        $games = $categorySlug === ''
            ? $allGames
            : array_values(array_filter(
                $allGames,
                static fn (array $game): bool => $game['category_slug'] === $categorySlug
            ));

        /** @var list<array{id: int, name: string}> $platforms */
        $platforms = $this->remember('find_tournament.platforms.v2', static fn (): array => Platform::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Platform $platform): array => [
                'id' => (int) $platform->id,
                'name' => (string) $platform->name,
            ])
            ->values()
            ->all());

        return [
            'categories' => $categories,
            'games' => $games,
            'platforms' => $platforms,
        ];
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return array{
     *     search: string,
     *     category: string,
     *     game_id: string,
     *     platform_id: string,
     *     budget: string,
     *     time_window: string,
     *     competition_type: string
     * }
     */
    private function normalizeCriteria(array $criteria): array
    {
        return [
            'search' => trim((string) ($criteria['search'] ?? '')),
            'category' => trim((string) ($criteria['category'] ?? '')),
            'game_id' => trim((string) ($criteria['game_id'] ?? '')),
            'platform_id' => trim((string) ($criteria['platform_id'] ?? '')),
            'budget' => trim((string) ($criteria['budget'] ?? '')),
            'time_window' => trim((string) ($criteria['time_window'] ?? '')),
            'competition_type' => trim((string) ($criteria['competition_type'] ?? '')),
        ];
    }

    /**
     * @param  array<string, string>  $criteria
     */
    private function hasActiveFilter(array $criteria): bool
    {
        foreach ($criteria as $value) {
            if ($value !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $criteria
     * @return Collection<int, Tournament>
     */
    private function fetchOpenRanked(array $criteria): Collection
    {
        $now = now();
        $usesV2 = (bool) config('features.tournament_v2.enabled');

        $eligibleGameIds = Game::query()
            ->availableInCatalog()
            ->when(
                ($criteria['category'] ?? '') !== '',
                fn (Builder $games): Builder => $games->inCategorySlug($criteria['category'])
            )
            ->select('id');

        $query = Tournament::query()
            ->where('status', TournamentStatus::REGISTRATION_OPEN->value)
            ->where('start_at', '>', $now)
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('join_closes_at')->orWhere('join_closes_at', '>', $now);
            })
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('registration_close_at')->orWhere('registration_close_at', '>', $now);
            })
            ->whereIn('game_id', $eligibleGameIds)
            ->with(['game.translations', 'game.category', 'platform'])
            ->withCount(['registrations' => function (Builder $registrations): void {
                $registrations->whereNotIn('status', [
                    RegistrationStatus::CANCELLED->value,
                    RegistrationStatus::REFUNDED->value,
                ]);
            }]);

        if ($usesV2) {
            $query->where('workflow_version', 2)->whereNotNull('template_id');
        } else {
            $query->where('workflow_version', 1);
        }

        if (($criteria['search'] ?? '') !== '') {
            $term = '%'.$criteria['search'].'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('name', 'like', $term)
                    ->orWhereHas('game', function (Builder $games) use ($term): void {
                        $games->where('slug', 'like', $term)
                            ->orWhereHas('translations', fn (Builder $translations) => $translations->where('name', 'like', $term));
                    });
            });
        }

        if (($criteria['game_id'] ?? '') !== '' && ctype_digit($criteria['game_id'])) {
            $query->where('game_id', (int) $criteria['game_id']);
        }

        if (($criteria['platform_id'] ?? '') !== '' && ctype_digit($criteria['platform_id'])) {
            $query->forPlatform((int) $criteria['platform_id']);
        }

        if (($criteria['competition_type'] ?? '') !== '' && in_array($criteria['competition_type'], ['tournament', 'head_to_head'], true)) {
            $query->where('competition_type', $criteria['competition_type']);
        }

        match ($criteria['budget'] ?? '') {
            'free' => $query->where('entry_fee', '<=', 0),
            'up_to_5' => $query->where('entry_fee', '>', 0)->where('entry_fee', '<=', 5),
            'up_to_20' => $query->where('entry_fee', '>', 0)->where('entry_fee', '<=', 20),
            'high' => $query->where('entry_fee', '>', 20),
            default => null,
        };

        match ($criteria['time_window'] ?? '') {
            'soon' => $query->where('start_at', '<=', $now->copy()->addHours(2)),
            'today' => $query->where('start_at', '<=', $now->copy()->endOfDay()),
            'week' => $query->where('start_at', '<=', $now->copy()->addDays(7)->endOfDay()),
            default => null,
        };

        $candidates = $query
            ->orderBy('start_at')
            ->limit(100)
            ->get()
            ->filter(function (Tournament $tournament): bool {
                $joined = (int) ($tournament->getAttribute('registrations_count') ?? 0);
                $max = max(1, (int) $tournament->max_participants);

                return $joined < $max;
            });

        return $candidates
            ->sort(function (Tournament $a, Tournament $b): int {
                $ratioA = ((int) ($a->getAttribute('registrations_count') ?? 0)) / max(1, (int) $a->max_participants);
                $ratioB = ((int) ($b->getAttribute('registrations_count') ?? 0)) / max(1, (int) $b->max_participants);

                if (abs($ratioA - $ratioB) > 0.0001) {
                    return $ratioB <=> $ratioA;
                }

                $startA = $a->start_at?->getTimestamp() ?? PHP_INT_MAX;
                $startB = $b->start_at?->getTimestamp() ?? PHP_INT_MAX;

                if ($startA !== $startB) {
                    return $startA <=> $startB;
                }

                return ((int) $b->is_featured) <=> ((int) $a->is_featured);
            })
            ->values();
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function remember(string $key, callable $callback): mixed
    {
        if (app()->runningUnitTests()) {
            return $callback();
        }

        return Cache::remember($key, self::CACHE_TTL_SECONDS, $callback);
    }
}
