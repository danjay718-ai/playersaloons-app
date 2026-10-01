<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\Platform;
use App\Modules\Tournament\Services\AdminV2ScheduleQuery;
use App\Shared\Enums\CompetitionType;
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

        $filters = [
            'search' => $search, 'game_id' => $gameId, 'platform_id' => $platformId,
            'tab' => $activeTab, 'status' => $status, 'start_time' => $startTime,
            'start_date' => $startDate, 'end_date' => $endDate,
        ];
        $queries = [];
        foreach (['active', 'completed', 'cancelled', 'all'] as $tab) {
            $queries[$tab] = app(AdminV2ScheduleQuery::class)->query(CompetitionType::HEAD_TO_HEAD, $tab, $filters);
        }
        $templates = (clone $queries[$statusTab])->orderByDesc('updated_at')->paginate($perPage)->withQueryString();
        $countActive = $queries['active']->count();
        $countCompleted = $queries['completed']->count();
        $countCancelled = $queries['cancelled']->count();
        $countAll = $queries['all']->count();
        $games = Game::query()->where('is_active', true)->with('translations')->orderBy('slug')->get();
        $platforms = Platform::query()->where('is_active', true)->orderBy('name')->get();

        return view('admin.head-to-head.index', compact(
            'templates', 'search', 'gameId', 'activeTab', 'statusTab', 'status', 'platformId', 'startDate', 'endDate', 'startTime', 'perPage',
            'games', 'platforms', 'countActive', 'countCompleted', 'countCancelled', 'countAll',
        ));
    }
}
