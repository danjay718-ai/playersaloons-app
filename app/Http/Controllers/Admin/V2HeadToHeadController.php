<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\Platform;
use App\Modules\Tournament\Models\Tournament;
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
        $perPage = min(50, max(5, (int) $request->query('per_page', 10)));

        abort_unless(in_array($activeTab, ['all', 'daily', 'weekly', 'monthly', 'one-time'], true), 404);
        abort_unless(in_array($statusTab, ['active', 'completed', 'cancelled', 'all'], true), 404);

        $templates = TournamentTemplate::query()
            ->where('workflow_version', 2)
            ->where('competition_type', CompetitionType::HEAD_TO_HEAD)
            ->with(['game.translations', 'scheduleSlots:id,tournament_template_id,schedule_start_at,schedule_end_at,day_of_week,day_of_month'])
            ->withCount('scheduleSlots')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->when($gameId !== '', fn ($query) => $query->where('game_id', $gameId))
            ->when($platformId !== '', fn ($query) => $query->where('settings_json->platform_id', (int) $platformId))
            ->when($activeTab !== 'all', function ($query) use ($activeTab): void {
                if ($activeTab === 'one-time') {
                    $query->where('is_recurring', false);

                    return;
                }

                $query->where('recurrence_frequency', $activeTab);
            })
            ->when($startDate !== '', fn ($query) => $query->whereHas('scheduleSlots', fn ($slots) => $slots->whereDate('schedule_start_at', '>=', $startDate)))
            ->when($endDate !== '', fn ($query) => $query->whereHas('scheduleSlots', fn ($slots) => $slots->whereDate('schedule_start_at', '<=', $endDate)));

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

        // A template can have multiple immutable occurrences. Filtering is
        // intentionally based on any matching occurrence, so grouped parents
        // remain discoverable without rewriting historical occurrence data.
        $templates->when($status !== '', fn ($query) => $query->whereHas('scheduleSlots.occurrences', fn ($occurrences) => $occurrences->where('status', $status)));
        match ($statusTab) {
            'active' => $templates->where(function ($query) use ($activeStatuses): void {
                $query->whereDoesntHave('scheduleSlots.occurrences')
                    ->orWhereHas('scheduleSlots.occurrences', fn ($occurrences) => $occurrences->whereIn('status', $activeStatuses));
            }),
            'completed' => $templates->whereHas('scheduleSlots.occurrences', fn ($occurrences) => $occurrences->where('status', TournamentStatus::COMPLETED->value)),
            'cancelled' => $templates->whereHas('scheduleSlots.occurrences', fn ($occurrences) => $occurrences->whereIn('status', [TournamentStatus::CANCELLED->value, TournamentStatus::REFUNDED->value])),
            default => null,
        };

        $templates = $templates->orderByDesc('updated_at')
            ->paginate($perPage)
            ->withQueryString();

        $games = Game::query()->where('is_active', true)->with('translations')->orderBy('slug')->get();
        $platforms = Platform::query()->where('is_active', true)->orderBy('name')->get();

        $countBase = TournamentTemplate::query()
            ->where('workflow_version', 2)
            ->where('competition_type', CompetitionType::HEAD_TO_HEAD)
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->when($gameId !== '', fn ($query) => $query->where('game_id', $gameId))
            ->when($platformId !== '', fn ($query) => $query->where('settings_json->platform_id', (int) $platformId));
        $countActive = (clone $countBase)->where(function ($query) use ($activeStatuses): void {
            $query->whereDoesntHave('scheduleSlots.occurrences')
                ->orWhereHas('scheduleSlots.occurrences', fn ($occurrences) => $occurrences->whereIn('status', $activeStatuses));
        })->count();
        $countCompleted = (clone $countBase)->whereHas('scheduleSlots.occurrences', fn ($occurrences) => $occurrences->where('status', TournamentStatus::COMPLETED->value))->count();
        $countCancelled = (clone $countBase)->whereHas('scheduleSlots.occurrences', fn ($occurrences) => $occurrences->whereIn('status', [TournamentStatus::CANCELLED->value, TournamentStatus::REFUNDED->value]))->count();
        $countAll = (clone $countBase)->count();

        return view('admin.head-to-head.index', compact(
            'templates', 'search', 'gameId', 'activeTab', 'statusTab', 'status', 'platformId', 'startDate', 'endDate', 'perPage',
            'games', 'platforms', 'countActive', 'countCompleted', 'countCancelled', 'countAll',
        ));
    }
}
