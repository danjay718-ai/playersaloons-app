<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentTemplate;
use App\Shared\Enums\RegistrationStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read model for the V2 discovery UI.
 *
 * V2 keeps immutable Tournament occurrences for money, brackets, and audit
 * history. This service deliberately groups those occurrences by their
 * template only at the presentation boundary; it never changes V1 queries.
 */
final class V2TournamentDiscoveryService
{
    /**
     * @param array{search?: string,game_id?: string,frequency?: string,competition_type?: string,platform_id?: string,team_format?: string,start_date?: string} $filters
     */
    public function paginate(string $tab, array $filters, int $perPage = 12, bool $featuredOnly = false): LengthAwarePaginator
    {
        $occurrences = $this->occurrenceQuery($tab, $filters, $featuredOnly);

        $templates = TournamentTemplate::query()
            ->with(['game.translations'])
            ->where('workflow_version', 2)
            ->whereHas('scheduleSlots.occurrences', fn (Builder $query) => $this->applyOccurrenceConstraints($query, $tab, $filters, $featuredOnly))
            ->orderBy('name')
            ->paginate($perPage);

        $byTemplate = $occurrences
            ->whereIn('template_id', $templates->getCollection()->pluck('id'))
            ->with(['game.translations', 'platform', 'scheduleSlot'])
            ->withCount(['registrations' => fn (Builder $query) => $query->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value])])
            ->orderBy('start_at')
            ->get()
            ->groupBy('template_id');

        $templates->getCollection()->transform(function (TournamentTemplate $template) use ($byTemplate): TournamentTemplate {
            // Presentation-only attribute. It intentionally is not persisted.
            $template->setAttribute('discovery_occurrences', $byTemplate->get($template->id, collect()));

            return $template;
        });

        return $templates;
    }

    /** @param array<string, string> $filters */
    private function occurrenceQuery(string $tab, array $filters, bool $featuredOnly): Builder
    {
        return $this->applyOccurrenceConstraints(
            Tournament::query()->where('workflow_version', 2)->whereNotNull('template_id'),
            $tab,
            $filters, $featuredOnly,
        );
    }

    /** @param array<string, string> $filters */
    private function applyOccurrenceConstraints(Builder $query, string $tab, array $filters, bool $featuredOnly = false): Builder
    {
        $statuses = match ($tab) {
            'ongoing' => ['REGISTRATION_CLOSED', 'CHECKIN_OPEN', 'CHECKIN_CLOSED', 'BRACKET_GENERATED', 'ONGOING'],
            'past' => ['COMPLETED'],
            default => ['REGISTRATION_OPEN'],
        };

        $query->whereIn('status', $statuses);
        if ($featuredOnly) {
            $query->where('is_featured', true);
        }

        // A current-period card must never expose an elapsed registration slot.
        if ($tab === 'upcoming') {
            $query->where('start_at', '>', now());
        }

        if (($filters['search'] ?? '') !== '') {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }
        if (($filters['start_date'] ?? '') !== '') {
            $query->whereDate('start_at', '>=', $filters['start_date']);
        }
        foreach (['game_id', 'frequency', 'competition_type', 'platform_id'] as $column) {
            if (($filters[$column] ?? '') !== '') {
                $query->where($column, $filters[$column]);
            }
        }
        if (($filters['team_format'] ?? '') === 'solo') {
            $query->where('team_size', 1);
        } elseif (($filters['team_format'] ?? '') === 'team') {
            $query->where('team_size', '>', 1);
        }

        return $query;
    }
}
