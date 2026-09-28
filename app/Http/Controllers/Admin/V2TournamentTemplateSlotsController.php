<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreV2TournamentScheduleSlotRequest;
use App\Modules\Tournament\Actions\AddV2TournamentScheduleSlotAction;
use App\Modules\Tournament\Actions\MaterializeV2OccurrenceAction;
use App\Modules\Tournament\Models\TournamentScheduleSlot;
use App\Modules\Tournament\Models\TournamentTemplate;
use App\Shared\Enums\CompetitionType;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Administrative read model for immutable V2 occurrences. */
final class V2TournamentTemplateSlotsController extends Controller
{
    private function guard(TournamentTemplate $template): void
    {
        abort_unless(config('features.tournament_v2.enabled'), 404);
        abort_unless(request()->user()?->can('tournaments.view'), 403);
        abort_unless((int) $template->workflow_version === 2, 404);
    }

    public function show(TournamentTemplate $template): View
    {
        $this->guard($template);
        $now = CarbonImmutable::now($template->timezone)->addMinute()->second(0);

        $template->load([
            'game.translations',
            'scheduleSlots.occurrences' => fn ($query) => $query
                ->with(['platform'])
                ->withCount('registrations')
                ->orderByDesc('start_at'),
        ]);
        $frequency = $template->recurrence_frequency?->value ?? 'one_time';

        // Keep the schedule useful throughout the day: upcoming slots stay at
        // the top in chronological order, while elapsed slots remain visible
        // underneath so admins can see what will recur next.
        $template->setRelation('scheduleSlots', $template->scheduleSlots
            ->sortBy(function ($slot) use ($now, $template, $frequency): string {
                if ($frequency === 'daily' && $slot->local_start_time !== null) {
                    [$hour, $minute] = array_map('intval', explode(':', $slot->local_start_time));
                    $slotSeconds = ($hour * 3600) + ($minute * 60);
                    $nowSeconds = ($now->hour * 3600) + ($now->minute * 60);

                    $isPast = $slotSeconds < $nowSeconds;
                    $sortSeconds = $isPast ? 86400 - $slotSeconds : $slotSeconds;

                    return sprintf('%d-%06d-%010d', $isPast ? 1 : 0, $sortSeconds, $slot->id);
                }

                $start = $slot->occurrences->sortBy('start_at')->first()?->start_at?->timezone($template->timezone)
                    ?? $slot->schedule_start_at?->timezone($template->timezone);
                $timestamp = $start?->timestamp ?? PHP_INT_MAX;

                return sprintf('%d-%012d-%010d', ($start !== null && $start->greaterThanOrEqualTo($now)) ? 0 : 1, $timestamp, $slot->id);
            })
            ->values());

        return view('admin.tournaments.v2-template-slots', compact('template', 'now'));
    }

    public function create(TournamentTemplate $template): RedirectResponse
    {
        $this->guard($template);
        abort_unless(request()->user()?->can('tournaments.manage'), 403);

        // Retained route for bookmarks. Slot creation now happens in the
        // schedule-management modal so admins do not leave the slot list.
        return redirect()->route('admin.tournaments.v2.templates.slots', $template)
            ->with('open_add_slot', true);
    }

    public function store(StoreV2TournamentScheduleSlotRequest $request, TournamentTemplate $template, AddV2TournamentScheduleSlotAction $add, MaterializeV2OccurrenceAction $materialize): RedirectResponse
    {
        $this->guard($template);
        $data = $request->validated();
        $start = CarbonImmutable::parse($data['schedule_start_at'], $template->timezone);
        $data['local_start_time'] = $start->format('H:i');
        $slot = $add->execute($template, $data);
        $materialize->execute($slot, $request->user());

        return redirect()->route('admin.tournaments.v2.templates.slots', $template)->with('success', 'Slot added. It inherits the parent defaults unless an override was supplied.');
    }

    public function update(Request $request, TournamentTemplate $template, TournamentScheduleSlot $slot): RedirectResponse
    {
        $this->guard($template);
        abort_unless($request->user()?->can('tournaments.manage'), 403);
        abort_unless((int) $slot->tournament_template_id === (int) $template->id, 404);
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:191'],
            'entry_fee' => ['nullable', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'max_teams' => ['nullable', 'integer', 'min:2', 'max:128'],
            'free_prize_1st' => ['nullable', 'numeric', 'min:0'],
            'free_prize_2nd' => ['nullable', 'numeric', 'min:0'],
            'is_featured' => ['nullable', 'boolean'],
        ]);
        if (filled($data['max_teams'] ?? null) && (int) $data['max_teams'] % 2 !== 0) {
            return back()->withErrors(['max_teams' => 'Maximum teams must be an even number.']);
        }
        if ($template->competition_type === CompetitionType::HEAD_TO_HEAD && filled($data['max_teams'] ?? null) && (int) $data['max_teams'] !== 2) {
            return back()->withErrors(['max_teams' => 'A Head-to-Head slot always has two players.']);
        }
        $effectiveFee = (float) (($data['entry_fee'] ?? null) !== null && $data['entry_fee'] !== '' ? $data['entry_fee'] : $template->entry_fee);
        $hasSponsored = filled($data['free_prize_1st'] ?? null) || filled($data['free_prize_2nd'] ?? null);
        if (($template->competition_type === CompetitionType::HEAD_TO_HEAD || $effectiveFee > 0) && $hasSponsored) {
            return back()->withErrors(['free_prize_1st' => 'Sponsored prizes are available only for free tournaments.']);
        }
        if ($template->competition_type !== CompetitionType::HEAD_TO_HEAD && $effectiveFee === 0.0
            && (float) $template->entry_fee > 0 && ! filled($data['free_prize_1st'] ?? null)) {
            return back()->withErrors(['free_prize_1st' => 'First Prize is required for a free slot.']);
        }

        $overrides = $slot->overrides_json ?? [];
        foreach (['name', 'entry_fee', 'max_teams', 'free_prize_1st', 'free_prize_2nd'] as $key) {
            if (filled($data[$key] ?? null)) {
                $overrides[$key] = $data[$key];
            } else {
                unset($overrides[$key]);
            }
        }
        $overrides['is_featured'] = $request->boolean('is_featured');
        $slot->update(['overrides_json' => $overrides]);

        return back()->with('success', 'Future slot overrides updated. Existing occurrences were not changed.');
    }
}
