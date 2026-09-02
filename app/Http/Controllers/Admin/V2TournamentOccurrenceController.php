<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Tournament\Actions\CancelTournamentAction;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\CompetitionType;
use App\Shared\Enums\TournamentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class V2TournamentOccurrenceController extends Controller
{
    public function show(Tournament $tournament): View
    {
        abort_unless(config('features.tournament_v2.enabled') && (int) $tournament->workflow_version === 2, 404);
        abort_unless(request()->user()?->can('tournaments.view'), 403);
        $tournament->load(['game.translations', 'platform', 'registrations.user', 'cancellation']);

        return view('admin.tournaments.v2-show-occurrence', compact('tournament'));
    }

    public function edit(Tournament $tournament): View
    {
        $this->authorizeEdit($tournament);
        $tournament->load(['game.translations', 'game.platforms:id,name']);

        return view('admin.tournaments.v2-edit-occurrence', [
            'tournament' => $tournament,
            'hasRegistrations' => $tournament->registrations()->exists(),
        ]);
    }

    public function update(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->authorizeEdit($tournament);
        $hasRegistrations = $tournament->registrations()->exists();
        $rules = [
            'description' => ['nullable', 'string', 'max:10000'],
            'rules' => ['nullable', 'string', 'max:20000'],
            'banner' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'is_featured' => ['nullable', 'boolean'],
        ];
        if (! $hasRegistrations) {
            $rules += [
                'name' => ['required', 'string', 'max:191'],
                'platform_id' => ['required', 'integer', 'exists:platforms,id'],
                'max_teams' => ['required', 'integer', 'min:2', 'max:128'],
                'entry_fee' => ['required', 'regex:/^\d+(?:\.\d{1,2})?$/'],
                'start_at' => ['required', 'date'],
                'end_date' => ['required', 'date'],
                'waiting_result_time' => ['required', 'integer', 'min:1', 'max:120'],
                'winning_points' => ['required', 'integer', 'min:0', 'max:100000'],
            ];
        }
        $data = $request->validate($rules);
        if (! $hasRegistrations) {
            abort_if((int) $data['max_teams'] % 2 !== 0, 422, 'Maximum teams must be an even number.');
            abort_if($tournament->competition_type === CompetitionType::HEAD_TO_HEAD && (int) $data['max_teams'] !== 2, 422, 'Head-to-Head occurrences always have two players.');
            abort_unless($tournament->game->platforms()->whereKey($data['platform_id'])->exists(), 422, 'Platform is not configured for this game.');
            $start = CarbonImmutable::parse($data['start_at'], $tournament->timezone)->utc();
            $end = CarbonImmutable::parse($data['end_date'], $tournament->timezone)->addDay()->startOfDay()->utc();
            abort_if($end->lessThanOrEqualTo($start), 422, 'End Date must be after Start Date and Time.');
            $tournament->fill([
                'name' => $data['name'], 'platform_id' => $data['platform_id'],
                'max_participants' => $data['max_teams'], 'entry_fee' => $data['entry_fee'],
                'start_at' => $start, 'end_at' => $end, 'join_closes_at' => $start,
                'registration_close_at' => $start, 'waiting_result_time' => $data['waiting_result_time'],
                'winning_points' => $data['winning_points'], 'winner_bonus_xp' => $data['winning_points'],
            ]);
        }
        $presentation = [];
        foreach (['description', 'rules'] as $field) {
            if (array_key_exists($field, $data)) {
                $presentation[$field] = $data[$field];
            }
        }
        if ($request->has('is_featured')) {
            $presentation['is_featured'] = $request->boolean('is_featured');
        }
        $tournament->fill($presentation);
        if ($path = $request->file('banner')?->store('tournaments/occurrences', 'public')) {
            $tournament->banner_url = '/storage/'.$path;
        }
        $tournament->save();

        return redirect()->route('admin.tournaments.v2.templates.slots', $tournament->template_id)->with('success', 'Schedule occurrence updated.');
    }

    public function cancel(Tournament $tournament, CancelTournamentAction $cancel): RedirectResponse
    {
        $this->authorizeEdit($tournament);
        abort_unless($tournament->status === TournamentStatus::REGISTRATION_OPEN, 422, 'Only an open registration slot can be cancelled.');

        $cancel->execute($tournament, request()->user(), 'admin_slot_cancelled', 'Cancelled by an administrator from schedule management.');

        return redirect()->route('admin.tournaments.v2.templates.slots', $tournament->template_id)
            ->with('success', 'Slot cancelled. Any paid confirmed entries were refunded and remain in transaction history.');
    }

    private function authorizeEdit(Tournament $tournament): void
    {
        abort_unless(config('features.tournament_v2.enabled') && (int) $tournament->workflow_version === 2, 404);
        abort_unless(request()->user()?->can('tournaments.manage') || (int) $tournament->created_by === (int) request()->user()?->id, 403);
        abort_if($tournament->start_at?->lessThanOrEqualTo(now()), 403, 'Tournament configuration is locked after Start Date and Time.');
    }
}
