<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreV2TournamentTemplateRequest;
use App\Modules\CMS\Models\Game;
use App\Modules\Tournament\Actions\CreateV2TournamentTemplateAction;
use App\Modules\Tournament\Actions\MaterializeV2OccurrenceAction;
use App\Modules\Tournament\Services\TournamentTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class V2TournamentTemplateController extends Controller
{
    public function create(TournamentTimezone $timezone): View
    {
        abort_unless(config('features.tournament_v2.enabled'), 404);
        abort_unless(request()->user()?->can('tournaments.create') || request()->user()?->can('tournaments.manage'), 403);

        $games = Game::query()->where('is_active', true)
            ->with(['translations', 'platforms:id,name', 'tournamentDefaults'])
            ->orderBy('slug')->get();

        return view('admin.tournaments.v2-create', [
            'games' => $games,
            'timezone' => $timezone->value(),
        ]);
    }

    public function store(StoreV2TournamentTemplateRequest $request, CreateV2TournamentTemplateAction $create, MaterializeV2OccurrenceAction $materialize, TournamentTimezone $timezone): RedirectResponse
    {
        abort_unless(config('features.tournament_v2.enabled'), 404);
        $data = $request->validated();
        $multiplier = match ($data['round_duration_unit'] ?? null) {
            'minutes' => 60,
            'hours' => 3600,
            'days' => 86400,
            default => null,
        };
        $banner = $request->file('banner')?->store('tournaments/templates', 'public');
        $data['slots'] = collect($data['slots'])->map(function (array $slot, int $index) use ($request, $timezone, $data): array {
            $overrideBanner = $request->file("slots.{$index}.banner")?->store('tournaments/templates/slots', 'public');
            $roundMultiplier = match ($slot['round_duration_unit'] ?? null) {
                'minutes' => 60, 'hours' => 3600, 'days' => 86400, default => null,
            };
            $overrides = array_filter([
                'name' => $slot['name'] ?? null,
                'platform_id' => $slot['platform_id'] ?? null,
                'max_teams' => $slot['max_teams'] ?? null,
                'entry_fee' => $slot['entry_fee'] ?? null,
                'winning_points' => $slot['winning_points'] ?? null,
                'waiting_result_time' => $slot['waiting_result_time'] ?? null,
                'round_duration_seconds' => $roundMultiplier === null ? null : (int) $slot['round_duration_value'] * $roundMultiplier,
                'full_first_bps' => isset($slot['full_first_percent']) ? (int) round((float) $slot['full_first_percent'] * 100) : null,
                'full_second_bps' => isset($slot['full_second_percent']) ? (int) round((float) $slot['full_second_percent'] * 100) : null,
                'description' => $slot['description'] ?? null,
                'rules' => $slot['rules'] ?? null,
                'banner_url' => $overrideBanner,
            ], static fn ($value) => $value !== null && $value !== '');

            $start = CarbonImmutable::parse($slot['schedule_start_at'], $timezone->value());

            return [
                'label' => $slot['label'] ?? null,
                'local_start_time' => $start->format('H:i'),
                'schedule_start_at' => $start,
                'schedule_end_at' => CarbonImmutable::parse($slot['schedule_end_at'], $timezone->value()),
                'day_of_week' => $data['frequency'] === 'weekly' ? (int) $slot['day_of_week'] : null,
                'day_of_month' => $data['frequency'] === 'monthly' ? (int) $slot['day_of_month'] : null,
                'overrides' => $overrides ?: null,
            ];
        })->all();
        $template = $create->execute([
            ...$data,
            'created_by' => $request->user()->id,
            'timezone' => $timezone->value(),
            'banner_url' => $banner,
            'round_duration_seconds' => $multiplier === null ? null : (int) $data['round_duration_value'] * $multiplier,
            'full_first_bps' => (int) round((float) $data['full_first_percent'] * 100),
            'full_second_bps' => (int) round((float) $data['full_second_percent'] * 100),
        ]);
        foreach ($template->scheduleSlots as $slot) {
            $materialize->execute($slot, $request->user());
        }

        return redirect()->route('admin.tournaments')->with('success', 'Tournament V2 schedule created. Occurrences will be materialized safely from its schedule slots.');
    }
}
