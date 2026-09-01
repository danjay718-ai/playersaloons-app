<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreV2TournamentScheduleSlotRequest;
use App\Modules\Tournament\Actions\AddV2TournamentScheduleSlotAction;
use App\Modules\Tournament\Actions\MaterializeV2OccurrenceAction;
use App\Modules\Tournament\Models\TournamentTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
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

        $template->load([
            'game.translations',
            'scheduleSlots.occurrences' => fn ($query) => $query
                ->with(['platform'])
                ->withCount('registrations')
                ->orderByDesc('start_at'),
        ]);

        return view('admin.tournaments.v2-template-slots', compact('template'));
    }

    public function create(TournamentTemplate $template): View
    {
        $this->guard($template);
        abort_unless(request()->user()?->can('tournaments.manage'), 403);
        $now = CarbonImmutable::now($template->timezone)->addMinute()->second(0);

        return view('admin.tournaments.v2-create-slot', compact('template', 'now'));
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
}
