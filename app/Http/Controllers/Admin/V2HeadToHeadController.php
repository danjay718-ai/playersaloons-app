<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\Platform;
use App\Modules\Tournament\Models\TournamentTemplate;
use App\Shared\Enums\CompetitionType;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Separate admin read model for platform-managed, scheduled 1v1 matches. */
final class V2HeadToHeadController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(config('features.tournament_v2.enabled'), 404);
        abort_unless($request->user()?->can('tournaments.view'), 403);

        $search = trim((string) $request->query('search', ''));
        $gameId = (string) $request->query('game_id', '');
        $activeTab = (string) $request->query('tab', 'all');
        $statusTab = (string) $request->query('status_tab', 'active');
        $status = (string) $request->query('status', '');
        $platformId = (string) $request->query('platform_id', '');
        $startDate = (string) $request->query('start_date', '');
        $endDate = (string) $request->query('end_date', '');
        $startTime = (string) $request->query('start_time', '');
        $perPage = min(50, max(5, (int) $request->query('per_page', 10)));

        abort_unless(in_array($activeTab, ['all', 'daily', 'weekly', 'monthly', 'one-time'], true), 404);
        abort_unless(in_array($statusTab, ['active', 'completed', 'cancelled', 'all'], true), 404);

        $activeStatuses = [
            TournamentStatus::DRAFT->value,
            TournamentStatus::PUBLISHED->value,
            TournamentStatus::REGISTRATION_OPEN->value,
            TournamentStatus::REGISTRATION_CLOSED->value,
            TournamentStatus::CHECKIN_OPEN->value,
            TournamentStatus::CHECKIN_CLOSED->value,
            TournamentStatus::BRACKET_GENERATED->value,
            TournamentStatus::ONGOING->value,
        ];

        $matchesOccurrenceFilters = function ($occurrences) use ($status, $statusTab, $activeStatuses, $activeTab, $startDate, $endDate, $startTime): void {
            if ($status !== '') {
                $occurrences->where('status', $status);
            } elseif ($statusTab === 'active') {
                $occurrences->whereIn('status', $activeStatuses);
            } elseif ($statusTab === 'completed') {
                $occurrences->where('status', TournamentStatus::COMPLETED->value);
            } elseif ($statusTab === 'cancelled') {
                $occurrences->whereIn('status', [TournamentStatus::CANCELLED->value, TournamentStatus::REFUNDED->value]);
            }

            if ($activeTab === 'daily' && $startTime !== '') {
                $occurrences->whereTime('start_at', $startTime.':00');
            } elseif ($activeTab !== 'daily') {
                if ($startDate !== '') {
                    $occurrences->whereDate('start_at', '>=', $startDate);
                }
                if ($endDate !== '') {
                    $occurrences->whereDate('start_at', '<=', $endDate);
                }
            }
        };

        $templates = TournamentTemplate::query()
            ->whereHas('game', fn ($games) => $games->availableInCatalog())
            ->where('workflow_version', 2)
            ->where('competition_type', CompetitionType::HEAD_TO_HEAD)
            ->with(['game.translations', 'scheduleSlots.occurrences' => fn ($query) => $query->withCount('registrations')->orderByDesc('start_at')])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->when($gameId !== '', fn ($query) => $query->where('game_id', $gameId))
            ->when($platformId !== '', fn ($query) => $query->where(fn ($platforms) => $platforms->whereJsonContains('settings_json->platform_ids', (int) $platformId)->orWhere('settings_json->platform_id', (int) $platformId)))
            ->when($activeTab !== 'all', function ($query) use ($activeTab): void {
                if ($activeTab === 'one-time') {
                    $query->where('is_recurring', false);

                    return;
                }

                $query->where('recurrence_frequency', $activeTab);
            });

        // Match all occurrence-level filters against the same occurrence. The
        // view then chooses its displayed item from that same matching set.
        match ($statusTab) {
            'active' => $templates->where(function ($query) use ($matchesOccurrenceFilters, $status, $startTime, $startDate, $endDate): void {
                $allowsUnmaterialized = $status === '' && $startTime === '' && $startDate === '' && $endDate === '';
                if ($allowsUnmaterialized) {
                    $query->whereDoesntHave('scheduleSlots.occurrences', fn ($occurrences) => $occurrences->withTrashed())
                        ->orWhereHas('scheduleSlots.occurrences', $matchesOccurrenceFilters);

                    return;
                }
                $query->whereHas('scheduleSlots.occurrences', $matchesOccurrenceFilters);
            }),
            'completed', 'cancelled' => $templates->whereHas('scheduleSlots.occurrences', $matchesOccurrenceFilters),
            default => $templates->when($status !== '' || $startTime !== '' || $startDate !== '' || $endDate !== '', fn ($query) => $query->whereHas('scheduleSlots.occurrences', $matchesOccurrenceFilters)),
        };

        $templates = $templates->orderByDesc('updated_at')
            ->paginate($perPage)
            ->withQueryString();

        $games = Game::query()->where('is_active', true)->with('translations')->orderBy('slug')->get();
        $platforms = Platform::query()->where('is_active', true)->orderBy('name')->get();

        $countBase = TournamentTemplate::query()
            ->whereHas('game', fn ($games) => $games->availableInCatalog())
            ->where('workflow_version', 2)
            ->where('competition_type', CompetitionType::HEAD_TO_HEAD)
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->when($gameId !== '', fn ($query) => $query->where('game_id', $gameId))
            ->when($platformId !== '', fn ($query) => $query->where(fn ($platforms) => $platforms->whereJsonContains('settings_json->platform_ids', (int) $platformId)->orWhere('settings_json->platform_id', (int) $platformId)));
        $countActive = (clone $countBase)->where(function ($query) use ($activeStatuses): void {
            $query->whereDoesntHave('scheduleSlots.occurrences', fn ($occurrences) => $occurrences->withTrashed())
                ->orWhereHas('scheduleSlots.occurrences', fn ($occurrences) => $occurrences->whereIn('status', $activeStatuses));
        })->count();
        $countCompleted = (clone $countBase)->whereHas('scheduleSlots.occurrences', fn ($occurrences) => $occurrences->where('status', TournamentStatus::COMPLETED->value))->count();
        $countCancelled = (clone $countBase)->whereHas('scheduleSlots.occurrences', fn ($occurrences) => $occurrences->whereIn('status', [TournamentStatus::CANCELLED->value, TournamentStatus::REFUNDED->value]))->count();
        $countAll = (clone $countBase)->count();

        return view('admin.head-to-head.index', compact(
            'templates', 'search', 'gameId', 'activeTab', 'statusTab', 'status', 'platformId', 'startDate', 'endDate', 'startTime', 'perPage',
            'games', 'platforms', 'countActive', 'countCompleted', 'countCancelled', 'countAll',
        ));
    }
}
