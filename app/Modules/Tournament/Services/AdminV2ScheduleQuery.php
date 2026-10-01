<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use App\Modules\CMS\Models\Game;
use App\Modules\Tournament\Models\TournamentTemplate;
use App\Shared\Enums\CompetitionType;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

final class AdminV2ScheduleQuery
{
    /** @param array<string, string> $filters */
    public function query(CompetitionType $type, string $statusTab, array $filters): Builder
    {
        $occurrences = function ($query) use ($type, $statusTab, $filters): void {
            $query->where('workflow_version', 2)->where('competition_type', $type);
            $statuses = $this->statuses($statusTab);
            if ($statuses !== null) {
                $query->whereIn('status', $statuses);
            }
            if (($filters['status'] ?? '') !== '') {
                $query->where('status', $filters['status']);
            }
            if (($filters['platform_id'] ?? '') !== '') {
                $query->forPlatform((int) $filters['platform_id']);
            }
            if (($filters['tab'] ?? 'all') === 'daily') {
                if (($filters['start_time'] ?? '') !== '') {
                    $query->whereTime('start_at', $filters['start_time'].':00');
                }
            } else {
                if (($filters['start_date'] ?? '') !== '') {
                    $query->whereDate('start_at', '>=', $filters['start_date']);
                }
                if (($filters['end_date'] ?? '') !== '') {
                    $query->whereDate('start_at', '<=', $filters['end_date']);
                }
            }
        };

        $query = TournamentTemplate::query()
            ->whereIn('game_id', Game::availableInCatalog()->select('id'))
            ->where('workflow_version', 2)
            ->where('competition_type', $type)
            ->when(($filters['search'] ?? '') !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$filters['search'].'%'))
            ->when(($filters['game_id'] ?? '') !== '', fn (Builder $query) => $query->where('game_id', $filters['game_id']));

        $tab = $filters['tab'] ?? 'all';
        if ($tab === 'one-time') {
            $query->where('is_recurring', false);
        } elseif ($tab !== 'all') {
            $query->where('recurrence_frequency', $tab);
        }

        $allowsUnmaterialized = in_array($statusTab, ['active', 'all'], true)
            && collect(['status', 'platform_id', 'start_time', 'start_date', 'end_date'])
                ->every(fn (string $key): bool => ($filters[$key] ?? '') === '');

        return $query->where(function (Builder $query) use ($occurrences, $allowsUnmaterialized): void {
            $query->whereHas('scheduleSlots.occurrences', $occurrences);
            if ($allowsUnmaterialized) {
                // Archived occurrences must not turn into empty active schedules.
                $query->orWhereDoesntHave('scheduleSlots.occurrences', fn (Builder $query) => $query->withoutGlobalScope(SoftDeletingScope::class));
            }
        })->with([
            'game.translations',
            'scheduleSlots' => fn ($query) => $query->where(function (Builder $query) use ($occurrences, $allowsUnmaterialized): void {
                $query->whereHas('occurrences', $occurrences);
                if ($allowsUnmaterialized) {
                    $query->orWhereDoesntHave('occurrences', fn (Builder $query) => $query->withoutGlobalScope(SoftDeletingScope::class));
                }
            }),
            'scheduleSlots.occurrences' => fn ($query) => $occurrences($query->withCount('registrations')->orderByDesc('start_at')),
        ]);
    }

    /** @return array<int, string>|null */
    private function statuses(string $tab): ?array
    {
        return match ($tab) {
            'active' => array_map(fn (TournamentStatus $status): string => $status->value, [
                TournamentStatus::DRAFT, TournamentStatus::PUBLISHED, TournamentStatus::REGISTRATION_OPEN,
                TournamentStatus::REGISTRATION_CLOSED, TournamentStatus::CHECKIN_OPEN, TournamentStatus::CHECKIN_CLOSED,
                TournamentStatus::BRACKET_GENERATED, TournamentStatus::ONGOING,
            ]),
            'completed' => [TournamentStatus::COMPLETED->value],
            'cancelled' => [TournamentStatus::CANCELLED->value, TournamentStatus::REFUNDED->value],
            default => null,
        };
    }
}
