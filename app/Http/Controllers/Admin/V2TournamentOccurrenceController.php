<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Tournament\Models\Tournament;
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
                'max_teams' => ['required', 'integer', 'in:4,8,16,32,64'],
                'entry_fee' => ['required', 'regex:/^\d+(?:\.\d{1,2})?$/'],
                'start_at' => ['required', 'date'],
                'end_date' => ['required', 'date'],
                'waiting_result_time' => ['required', 'integer', 'min:1', 'max:120'],
                'winning_points' => ['required', 'integer', 'min:0', 'max:100000'],
            ];
        }
        $data = $request->validate($rules);
        if (! $hasRegistrations) {
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
        $tournament->fill([
            'description' => $data['description'] ?? null,
            'rules' => $data['rules'] ?? null,
            'is_featured' => $request->boolean('is_featured'),
        ]);
        if ($path = $request->file('banner')?->store('tournaments/occurrences', 'public')) {
            $tournament->banner_url = '/storage/'.$path;
        }
        $tournament->save();

        return redirect()->route('admin.tournaments')->with('success', 'Tournament V2 occurrence updated.');
    }

    private function authorizeEdit(Tournament $tournament): void
    {
        abort_unless(config('features.tournament_v2.enabled') && (int) $tournament->workflow_version === 2, 404);
        abort_unless(request()->user()?->can('tournaments.manage') || (int) $tournament->created_by === (int) request()->user()?->id, 403);
        abort_if($tournament->start_at?->lessThanOrEqualTo(now()), 403, 'Tournament configuration is locked after Start Date and Time.');
    }
}
