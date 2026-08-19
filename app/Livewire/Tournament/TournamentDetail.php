<?php

declare(strict_types=1);

namespace App\Livewire\Tournament;

use App\Livewire\Concerns\HandlesUserFacingErrors;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Stream\Support\StreamEmbedService;
use App\Modules\Team\Models\Team;
use App\Modules\Tournament\Actions\CancelRegistrationAction;
use App\Modules\Tournament\Actions\CheckinParticipantAction;
use App\Modules\Tournament\Actions\RegisterForTournamentAction;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Shared\Enums\CheckinStatus;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

class TournamentDetail extends Component
{
    use HandlesUserFacingErrors;

    public string $uuid;

    public string $layout = 'components.layouts.dashboard';

    #[Url]
    public string $activeTab = 'overview';

    /** @var array<string, bool> */
    public array $loadedSections = [];

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;

        $user = Auth::user();
        if ($user && $user->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'TOURNAMENT_ORGANIZER'])) {
            $this->layout = 'components.layouts.admin';
        } elseif ($user) {
            $this->layout = 'components.layouts.dashboard';
        } else {
            // Guest visitors use app layout
            $this->layout = 'components.layouts.app';
        }
    }

    private function getTournamentQuery()
    {
        if (Auth::check() && Auth::user()->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'TOURNAMENT_ORGANIZER'])) {
            return Tournament::query();
        }

        return Tournament::query()->where('status', '!=', TournamentStatus::DRAFT->value);
    }

    public function loadSection(string $tab): void
    {
        $section = match ($tab) {
            'participants', 'team-lobby' => 'participants',
            'fixtures', 'bracket' => 'bracket',
            'activity' => 'activity',
            default => null,
        };

        if ($section !== null) {
            $this->loadedSections[$section] = true;
        }
    }

    public function register(RegisterForTournamentAction $action)
    {
        if (! Auth::check()) {
            return redirect()->to('/login');
        }

        if (! Auth::user()->hasRole('PLAYER')) {
            session()->flash('error', 'Only players can join tournaments.');

            return;
        }

        $tournament = $this->getTournamentQuery()->where('uuid', $this->uuid)->firstOrFail();
        $user = Auth::user();

        try {
            $team = ($tournament->team_size ?? 1) > 1
                ? Team::query()->where('captain_user_id', $user->id)->where('status', 'active')->first()
                : null;

            // For team tournaments: if no team found, register as solo (team lobby mode)
            // The team parameter is null for solo/team-lobby registration
            $action->execute($tournament, $user, $team);
            session()->flash('message', $team ? "Successfully registered {$team->name}!" : 'Successfully joined the tournament! You can form a team in the Team Lobby.');
        } catch (\Exception $e) {
            session()->flash('error', $this->safeError($e, 'Unable to register for this tournament.'));
        }
    }

    public function checkin(CheckinParticipantAction $action)
    {
        if (! Auth::check()) {
            return redirect()->to('/login');
        }

        $tournament = $this->getTournamentQuery()->where('uuid', $this->uuid)->firstOrFail();
        $user = Auth::user();

        try {
            $action->execute($tournament, $user);
            session()->flash('message', 'Successfully checked in!');
        } catch (\Exception $e) {
            session()->flash('error', $this->safeError($e, 'Unable to complete tournament check-in.'));
        }
    }

    public function cancelRegistration(CancelRegistrationAction $action)
    {
        if (! Auth::check()) {
            return redirect()->to('/login');
        }

        $tournament = $this->getTournamentQuery()->where('uuid', $this->uuid)->firstOrFail();
        $user = Auth::user();

        $registration = TournamentRegistration::query()
            ->where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value])
            ->first();

        if (! $registration) {
            session()->flash('error', 'No active registration found.');

            return;
        }

        try {
            $action->execute($registration, $user);
            session()->flash('message', 'Registration cancelled successfully. Any entry fee has been refunded to your wallet.');
        } catch (\Exception $e) {
            session()->flash('error', $this->safeError($e, 'Unable to update the tournament stream.'));
        }
    }

    public function render(StreamEmbedService $streamService)
    {
        $tournament = $this->getTournamentQuery()
            ->where('uuid', $this->uuid)
            ->with([
                'game.translations',
                'platform',
                'template',
                'streamChannels',
            ])
            ->withCount(['registrations' => fn ($query) => $query->whereNotIn('status', [
                RegistrationStatus::CANCELLED->value,
                RegistrationStatus::REFUNDED->value,
            ])])
            ->firstOrFail();

        $user = Auth::user();
        $isRegistered = false;
        $isCheckedIn = false;
        $userRegistration = null;

        if ($user) {
            $userRegistration = TournamentRegistration::query()
                ->where('tournament_id', $tournament->id)
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)->orWhereHas('rosterMembers', fn ($members) => $members->where('user_id', $user->id));
                })
                ->whereNotIn('status', [RegistrationStatus::CANCELLED, RegistrationStatus::REFUNDED])
                ->with(['checkins' => fn ($query) => $query->where('status', CheckinStatus::CHECKED_IN)])
                ->first();

            if ($userRegistration) {
                $isRegistered = true;
                $isCheckedIn = $userRegistration->checkins->isNotEmpty();
            }
        }

        $canViewRestricted = $user?->can('viewRestrictedDetails', $tournament) ?? false;

        $participantsLoaded = $canViewRestricted && isset($this->loadedSections['participants']);
        $bracketLoaded = $canViewRestricted && isset($this->loadedSections['bracket']);
        $activityLoaded = $canViewRestricted && isset($this->loadedSections['activity']);

        if ($participantsLoaded) {
            $tournament->load([
                'registrations' => fn ($query) => $query
                    ->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value])
                    ->with(['user.profile', 'team']),
            ]);
        } else {
            $tournament->setRelation('registrations', collect());
        }

        if ($bracketLoaded) {
            $tournament->load([
                'brackets.rounds.matches.playerARegistration.user',
                'brackets.rounds.matches.playerBRegistration.user',
                'brackets.rounds.matches.winnerRegistration.user',
                'brackets.rounds.matches.round',
            ]);
        } else {
            $tournament->setRelation('brackets', collect());
        }

        $hasLost = false;
        if ($user && $userRegistration) {
            $hasLost = GameMatch::where('tournament_id', $tournament->id)
                ->where(function ($query) use ($userRegistration) {
                    $query->where('player_a_registration_id', $userRegistration->id)
                        ->orWhere('player_b_registration_id', $userRegistration->id);
                })
                ->whereIn('status', [MatchStatus::COMPLETED, MatchStatus::FORFEITED])
                ->whereNotNull('winner_registration_id')
                ->where('winner_registration_id', '!=', $userRegistration->id)
                ->exists();
        }

        // Reuse the already eager-loaded bracket graph for both tabs. The old
        // implementation fetched rounds and matches a second time on every
        // Livewire render, which became increasingly expensive as brackets grew.
        $rounds = $bracketLoaded ? ($tournament->brackets->first()?->rounds
            ->sortBy('round_number')
            ->values() ?? collect()) : collect();
        $allMatches = $rounds->flatMap->matches
            ->sortBy(fn (GameMatch $match): string => sprintf('%010d:%010d', $match->round_id, $match->id))
            ->values();

        $activityLogs = $activityLoaded
            ? Activity::query()
                ->where('subject_type', Tournament::class)
                ->where('subject_id', $tournament->id)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
            : collect();

        // Check if tournament can still be cancelled (registration still open)
        $canCancelRegistration = $isRegistered && in_array($tournament->status, [
            TournamentStatus::REGISTRATION_OPEN,
            TournamentStatus::PUBLISHED,
        ], true);

        return view('livewire.tournament.tournament-detail', [
            'tournament' => $tournament,
            'isRegistered' => $isRegistered,
            'isCheckedIn' => $isCheckedIn,
            'userRegistration' => $userRegistration,
            'rounds' => $rounds,
            'allMatches' => $allMatches,
            'activityLogs' => $activityLogs,
            'hasLost' => $hasLost,
            'streamService' => $streamService,
            'canCancelRegistration' => $canCancelRegistration,
            'canViewRestricted' => $canViewRestricted,
            'participantsLoaded' => $participantsLoaded,
            'bracketLoaded' => $bracketLoaded,
            'activityLoaded' => $activityLoaded,
        ])->layout($this->layout, ['title' => $tournament->name.' | PlayerSaloons', 'dashboard_title' => 'TOURNAMENT DETAILS']);
    }
}
