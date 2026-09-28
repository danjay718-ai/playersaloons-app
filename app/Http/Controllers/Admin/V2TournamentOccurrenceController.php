<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Tournament\Actions\CancelTournamentAction;
use App\Modules\Tournament\Actions\CloseRegistrationAction;
use App\Modules\Tournament\Actions\OpenRegistrationAction;
use App\Modules\Tournament\Actions\PublishTournamentAction;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Services\SponsoredPrizeFundingService;
use App\Modules\Tournament\StateMachines\TournamentStateMachine;
use App\Modules\Tournament\Support\CompetitionPlatforms;
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

    public function archived(): View
    {
        abort_unless(config('features.tournament_v2.enabled'), 404);
        abort_unless(request()->user()?->can('tournaments.view'), 403);
        $tournaments = Tournament::withTrashed()
            ->with(['game.translations'])
            ->where('workflow_version', 2)
            ->where('competition_type', CompetitionType::TOURNAMENT)
            ->whereNotNull('deleted_at')
            ->orderByDesc('deleted_at')
            ->paginate(25);

        return view('admin.tournaments.v2-archived', compact('tournaments'));
    }

    public function restore(int $id): RedirectResponse
    {
        abort_unless(request()->user()?->can('tournaments.manage'), 403);
        $tournament = Tournament::withTrashed()->where('workflow_version', 2)
            ->where('competition_type', CompetitionType::TOURNAMENT)->findOrFail($id);
        abort_unless($tournament->trashed(), 422, 'This tournament is not archived.');
        $tournament->restore();
        activity()->causedBy(request()->user())->performedOn($tournament)->log('tournament_v2_restored');

        return redirect()->route('admin.tournaments.v2.archived')->with('success', 'Tournament restored to normal lists.');
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

    public function update(Request $request, Tournament $tournament, SponsoredPrizeFundingService $sponsoredPrizes): RedirectResponse
    {
        $this->authorizeEdit($tournament);
        $hasRegistrations = $tournament->registrations()->exists();
        if (! $request->exists('platform_ids') && $request->filled('platform_id')) {
            $request->merge(['platform_ids' => [$request->input('platform_id')]]);
        }
        if (! $hasRegistrations
            && $tournament->competition_type === CompetitionType::TOURNAMENT
            && (float) $request->input('entry_fee', $tournament->entry_fee) === 0.0
            && ! $request->exists('free_prize_1st')) {
            $request->merge([
                'free_prize_1st' => (string) ($tournament->prize_1st ?? '0.00'),
                'free_prize_2nd' => (string) ($tournament->prize_2nd ?? '0.00'),
            ]);
        }
        $rules = [
            'description' => ['nullable', 'string', 'max:10000'],
            'rules' => ['nullable', 'string', 'max:20000'],
            'banner' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'is_featured' => ['nullable', 'boolean'],
        ];
        if (! $hasRegistrations) {
            $rules += [
                'name' => ['required', 'string', 'max:191'],
                ...CompetitionPlatforms::rules((int) $tournament->game_id),
                'max_teams' => ['required', 'integer', 'min:2', 'max:128'],
                'entry_fee' => ['required', 'regex:/^\d+(?:\.\d{1,2})?$/'],
                'start_at' => ['required', 'date'],
                'end_date' => ['required', 'date'],
                'waiting_result_time' => ['required', 'integer', 'min:1', 'max:120'],
                'winning_points' => ['required', 'integer', 'min:0', 'max:100000'],
            ];
            $freeTournament = $tournament->competition_type === CompetitionType::TOURNAMENT
                && (float) $request->input('entry_fee', $tournament->entry_fee) === 0.0;
            $rules['free_prize_1st'] = [$freeTournament ? 'required' : 'prohibited', 'nullable', 'numeric', 'min:0'];
            $rules['free_prize_2nd'] = [$freeTournament ? 'nullable' : 'prohibited', 'numeric', 'min:0'];
        }
        $data = $request->validate($rules);
        if (! $hasRegistrations) {
            abort_if((int) $data['max_teams'] % 2 !== 0, 422, 'Maximum teams must be an even number.');
            abort_if($tournament->competition_type === CompetitionType::HEAD_TO_HEAD && (int) $data['max_teams'] !== 2, 422, 'Head-to-Head occurrences always have two players.');
            $platformIds = CompetitionPlatforms::ids($data);
            $start = CarbonImmutable::parse($data['start_at'], $tournament->timezone)->utc();
            $end = CarbonImmutable::parse($data['end_date'], $tournament->timezone)->addDay()->startOfDay()->utc();
            abort_if($end->lessThanOrEqualTo($start), 422, 'End Date must be after Start Date and Time.');
            $sponsoredPrizes->configure(
                $tournament,
                (string) $data['entry_fee'],
                isset($data['free_prize_1st']) ? (string) $data['free_prize_1st'] : null,
                isset($data['free_prize_2nd']) ? (string) $data['free_prize_2nd'] : null,
            );
            $tournament->fill([
                'name' => $data['name'], 'platform_id' => $platformIds[0], 'platform_ids' => $platformIds,
                'max_participants' => $data['max_teams'],
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
        $this->authorizeManage($tournament);
        abort_unless(in_array($tournament->status, [
            TournamentStatus::DRAFT, TournamentStatus::PUBLISHED, TournamentStatus::REGISTRATION_OPEN,
            TournamentStatus::REGISTRATION_CLOSED, TournamentStatus::CHECKIN_OPEN,
            TournamentStatus::CHECKIN_CLOSED, TournamentStatus::BRACKET_GENERATED,
        ], true), 422, 'This V2 status cannot be cancelled.');

        $cancel->execute($tournament, request()->user(), 'admin_slot_cancelled', 'Cancelled by an administrator from schedule management.');

        $redirect = request()->input('return_to') === 'list'
            ? redirect()->route('admin.tournaments')
            : redirect()->route('admin.tournaments.v2.templates.slots', $tournament->template_id);

        return $redirect->with('success', 'Tournament cancelled. Any paid entries were refunded and remain in transaction history.');
    }

    public function feature(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->authorizeManage($tournament);
        $data = $request->validate(['is_featured' => ['required', 'boolean']]);
        $tournament->forceFill(['is_featured' => (bool) $data['is_featured']])->save();
        activity()->causedBy($request->user())->performedOn($tournament)
            ->withProperties(['is_featured' => (bool) $data['is_featured']])->log('tournament_v2_featured_changed');

        return redirect()->route('admin.tournaments')->with('success', $data['is_featured'] ? 'Tournament featured.' : 'Tournament unfeatured.');
    }

    public function changeStatus(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->authorizeManage($tournament);
        abort_if($tournament->start_at?->lessThanOrEqualTo(now()), 422, 'V2 status controls are locked after the scheduled start.');
        $data = $request->validate(['status' => ['required', 'in:DRAFT,PUBLISHED,REGISTRATION_OPEN,REGISTRATION_CLOSED']]);
        $target = TournamentStatus::from($data['status']);
        abort_if($target === TournamentStatus::DRAFT && $tournament->registrations()->exists(), 422, 'A tournament with registrations cannot be returned to draft.');
        $machine = app(TournamentStateMachine::class);
        abort_unless($machine->can($tournament->status, $target), 422, 'That status transition is not allowed by the V2 lifecycle.');

        match ($target) {
            TournamentStatus::PUBLISHED => app(PublishTournamentAction::class)->execute($tournament),
            TournamentStatus::REGISTRATION_OPEN => app(OpenRegistrationAction::class)->execute($tournament),
            TournamentStatus::REGISTRATION_CLOSED => app(CloseRegistrationAction::class)->execute($tournament),
            default => $machine->transition($tournament, $target, ['triggered_by' => 'admin_v2_schedule', 'user_id' => $request->user()?->id]),
        };
        activity()->causedBy($request->user())->performedOn($tournament)
            ->withProperties(['status' => $data['status']])->log('tournament_v2_status_changed');

        return redirect()->route('admin.tournaments')->with('success', 'Tournament status updated.');
    }

    public function archive(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->authorizeManage($tournament);
        abort_unless($request->user()?->can('tournaments.delete'), 403);
        abort_unless(in_array($tournament->status, [TournamentStatus::CANCELLED, TournamentStatus::REFUNDED, TournamentStatus::COMPLETED], true), 422, 'Only a terminal tournament can be archived.');
        $tournament->delete();
        activity()->causedBy($request->user())->performedOn($tournament)->withProperties(['recoverable' => true])->log('tournament_v2_archived');

        return redirect()->route('admin.tournaments')->with('success', 'Tournament archived from normal lists; its history remains stored.');
    }

    private function authorizeEdit(Tournament $tournament): void
    {
        abort_unless(config('features.tournament_v2.enabled') && (int) $tournament->workflow_version === 2, 404);
        abort_unless(request()->user()?->can('tournaments.manage') || (int) $tournament->created_by === (int) request()->user()?->id, 403);
        abort_if($tournament->start_at?->lessThanOrEqualTo(now()), 403, 'Tournament configuration is locked after Start Date and Time.');
    }

    private function authorizeManage(Tournament $tournament): void
    {
        abort_unless(config('features.tournament_v2.enabled') && (int) $tournament->workflow_version === 2
            && $tournament->competition_type === CompetitionType::TOURNAMENT, 404);
        abort_unless(request()->user()?->can('tournaments.manage') || (int) $tournament->created_by === (int) request()->user()?->id, 403);
    }
}
