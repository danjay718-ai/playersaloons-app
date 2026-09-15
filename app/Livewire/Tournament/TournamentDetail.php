<?php

declare(strict_types=1);

namespace App\Livewire\Tournament;

use App\Livewire\Concerns\HandlesUserFacingErrors;
use App\Modules\CMS\Models\Platform;
use App\Modules\Identity\Models\PlayerExperienceAward;
use App\Modules\Identity\Models\UserGameAccount;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Stream\Support\StreamEmbedService;
use App\Modules\Team\Models\Team;
use App\Modules\Tournament\Actions\CancelRegistrationAction;
use App\Modules\Tournament\Actions\FindTournamentTeamAction;
use App\Modules\Tournament\Actions\RegisterForTournamentAction;
use App\Modules\Tournament\Actions\RegisterForV2TournamentAction;
use App\Modules\Tournament\Actions\RequestV2CancellationAction;
use App\Modules\Tournament\Actions\VoteOnV2CancellationAction;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentCancellationRequest;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Modules\Tournament\Models\TournamentTeam;
use App\Modules\Tournament\Models\TournamentTeamSearchEntry;
use App\Modules\Tournament\Services\PrizeCalculationService;
use App\Modules\Tournament\Services\V2PrizePolicy;
use App\Modules\Wallet\Exceptions\InsufficientBalanceException;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Modules\Operations\Models\Activity;

class TournamentDetail extends Component
{
    use HandlesUserFacingErrors;

    public string $uuid;

    public string $layout = 'components.layouts.dashboard';

    #[Url]
    public string $activeTab = 'overview';

    /** Public discovery links must not unexpectedly switch into the player shell. */
    #[Url(as: 'view')]
    public string $viewMode = '';

    /** @var array<string, bool> */
    public array $loadedSections = [];

    public string $gameIdValue = '';

    public ?int $selectedPlatformId = null;

    public string $readyMode = 'auto';

    public string $cancellationError = '';

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;

        $user = Auth::user();
        if ($user && $this->viewMode !== 'guest' && $user->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'TOURNAMENT_ORGANIZER'])) {
            $this->layout = 'components.layouts.admin';
        } elseif ($user && $this->viewMode !== 'guest') {
            $this->layout = 'components.layouts.dashboard';
        } else {
            // Guest visitors use app layout
            $this->layout = 'components.layouts.app';
        }

        if ($user) {
            $tournament = $this->getTournamentQuery()->where('uuid', $uuid)->first(['game_id', 'platform_id']);
            if ($tournament?->platform_id !== null) {
                $this->selectedPlatformId = (int) $tournament->platform_id;
                $this->gameIdValue = (string) (UserGameAccount::query()
                    ->where('user_id', $user->getKey())
                    ->where('game_id', $tournament->game_id)
                    ->where('platform_id', $tournament->platform_id)
                    ->value('game_id_value') ?? '');
            }
        }
    }

    public function updatedSelectedPlatformId(): void
    {
        $tournament = $this->getTournamentQuery()->where('uuid', $this->uuid)->firstOrFail();
        $this->gameIdValue = (string) (UserGameAccount::query()
            ->where('user_id', Auth::id())
            ->where('game_id', $tournament->game_id)
            ->where('platform_id', $this->selectedPlatformId)
            ->value('game_id_value') ?? '');
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

    public function register(RegisterForTournamentAction $action, RegisterForV2TournamentAction $v2Action, FindTournamentTeamAction $findTeam)
    {
        if (! Auth::check()) {
            return redirect()->to('/login');
        }

        if (! Auth::user()->hasRole('PLAYER')) {
            session()->flash('error', 'Only players can join tournaments.');

            return;
        }

        $tournament = $this->getTournamentQuery()->where('uuid', $this->uuid)->firstOrFail();
        abort_if((int) $tournament->workflow_version === 2 && ! config('features.tournament_v2.enabled'), 404);
        $user = Auth::user();

        $this->validate([
            'selectedPlatformId' => ['nullable', 'integer', Rule::in($tournament->supportedPlatformIds())],
            'gameIdValue' => 'required|string|max:191',
            'readyMode' => 'required|in:auto,confirm_each_match',
        ]);

        try {
            $squad = null;
            $tournamentTeam = null;
            if (($tournament->team_size ?? 1) > 1) {
                $tournamentTeam = TournamentTeam::query()
                    ->where('tournament_id', $tournament->id)
                    ->where('leader_user_id', $user->id)
                    ->where('status', 'ready')
                    ->first();
                $squad = Team::query()->where('captain_user_id', $user->id)->where('status', 'active')->first();

                if ($tournamentTeam === null && $squad === null) {
                    $formed = $findTeam->execute($tournament, $user, $this->gameIdValue, $this->readyMode, $this->selectedPlatformId);
                    session()->flash('message', $formed && (int) $formed->leader_user_id === (int) $user->id
                        ? 'Your tournament team is complete. Click Register Team to finalize the entry.'
                        : ($formed ? 'Your tournament team is complete. The Team Leader will finalize registration.' : 'You are now looking for a team. There is no charge until a full team is formed.'));

                    return;
                }
            }

            if ((int) $tournament->workflow_version === 2) {
                $v2Action->execute($tournament, $user, $squad, $this->gameIdValue, $this->readyMode, $tournamentTeam, $this->selectedPlatformId);
            } else {
                $action->execute($tournament, $user, $squad, $this->gameIdValue, $this->readyMode, $tournamentTeam, $this->selectedPlatformId);
            }
            session()->flash('message', ($tournament->team_size ?? 1) > 1 ? 'Tournament team registered successfully!' : 'Successfully joined the tournament!');
        } catch (InsufficientBalanceException $e) {
            session()->flash('error', 'Insufficient balance. Please top up your wallet to pay the entrance fee.');
        } catch (\Exception $e) {
            session()->flash('error', $this->safeError($e, 'Unable to register for this tournament.'));
        }
    }

    public function cancelRegistration(CancelRegistrationAction $action, RequestV2CancellationAction $v2Action)
    {
        $this->cancellationError = '';

        if (! Auth::check()) {
            return redirect()->to('/login');
        }

        $tournament = $this->getTournamentQuery()->where('uuid', $this->uuid)->firstOrFail();
        abort_if((int) $tournament->workflow_version === 2 && ! config('features.tournament_v2.enabled'), 404);
        $user = Auth::user();

        $registration = TournamentRegistration::query()
            ->where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value])
            ->first();

        if (! $registration) {
            $this->cancellationError = 'No active registration found.';
            session()->flash('error', 'No active registration found.');

            return;
        }

        try {
            if ((int) $tournament->workflow_version === 2) {
                $request = $v2Action->execute($registration, $user);
                session()->flash('message', $request->status === 'approved'
                    ? 'Registration cancelled and refunded.'
                    : "Cancellation request created. {$request->required_approvals} approval(s) are required before tournament start.");
            } else {
                $action->execute($registration, $user);
                session()->flash('message', 'Registration cancelled successfully. Any entry fee has been refunded to your wallet.');
            }
            $this->dispatch('registration-cancellation-completed');
        } catch (\Exception $e) {
            $this->cancellationError = $this->safeError($e, 'Unable to cancel the tournament registration.');
            session()->flash('error', $this->cancellationError);
        }
    }

    public function voteOnCancellation(int $requestId, bool $approved, VoteOnV2CancellationAction $action): void
    {
        if (! Auth::check()) {
            return;
        }

        try {
            $request = TournamentCancellationRequest::query()
                ->where('tournament_id', Tournament::query()->where('uuid', $this->uuid)->value('id'))
                ->findOrFail($requestId);
            $action->execute($request, Auth::user(), $approved);
            session()->flash('message', 'Your cancellation vote has been recorded and cannot be changed.');
        } catch (\Exception $e) {
            session()->flash('error', $this->safeError($e, 'Unable to record the cancellation vote.'));
        }
    }

    public function transferTournamentTeamLeadership(int $userId): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        try {
            DB::transaction(function () use ($user, $userId): void {
                $tournament = $this->getTournamentQuery()->where('uuid', $this->uuid)->lockForUpdate()->firstOrFail();
                if ($tournament->status !== TournamentStatus::REGISTRATION_OPEN) {
                    throw new \LogicException('Team leadership is locked after registration closes.');
                }

                $team = TournamentTeam::query()
                    ->where('tournament_id', $tournament->id)
                    ->where('leader_user_id', $user->id)
                    ->where('status', 'ready')
                    ->lockForUpdate()
                    ->firstOrFail();
                if (! $team->members()->where('user_id', $userId)->exists()) {
                    throw new \LogicException('The new Team Leader must be part of this tournament team.');
                }

                $team->members()->where('user_id', $user->id)->update(['role' => 'member', 'updated_at' => now()]);
                $team->members()->where('user_id', $userId)->update(['role' => 'leader', 'updated_at' => now()]);
                $team->update(['leader_user_id' => $userId]);
            });

            session()->flash('message', 'Tournament Team leadership transferred.');
        } catch (\Exception $e) {
            session()->flash('error', $this->safeError($e, 'Unable to transfer Tournament Team leadership.'));
        }
    }

    public function render(
        StreamEmbedService $streamService,
        PrizeCalculationService $prizeCalculationService,
        V2PrizePolicy $v2PrizePolicy,
    ) {
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
        abort_if((int) $tournament->workflow_version === 2 && ! config('features.tournament_v2.enabled'), 404);

        $user = Auth::user();
        $prizeCalculation = $prizeCalculationService->calculate($tournament);
        $isV2Registration = (int) $tournament->workflow_version === 2
            && $tournament->status === TournamentStatus::REGISTRATION_OPEN
            && ($tournament->start_at?->isFuture() ?? false)
            && $tournament->financial_finalized_at === null;
        // Before a V2 tournament starts, show organizers' full-capacity
        // projection rather than a misleading $0/TBD live pool. The payout
        // is recalculated from actual confirmed entries when it starts.
        $displayPrizeCalculation = $isV2Registration
            ? $v2PrizePolicy->calculate($tournament, (int) $tournament->max_participants)
            : null;
        $isRegistered = false;
        $userRegistration = null;

        if ($user) {
            $userRegistration = TournamentRegistration::query()
                ->where('tournament_id', $tournament->id)
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)->orWhereHas('rosterMembers', fn ($members) => $members->where('user_id', $user->id));
                })
                ->whereNotIn('status', [RegistrationStatus::CANCELLED, RegistrationStatus::REFUNDED])
                ->first();

            if ($userRegistration) {
                $isRegistered = true;
            }
        }

        // This is a player-facing occurrence notice. Admins and visitors must
        // never receive it, and a player must actually hold an active entry.
        $shouldShowUnderfilledNotice = $isRegistered
            && $user?->hasRole('PLAYER')
            && (int) $tournament->workflow_version === 2
            && $tournament->status === TournamentStatus::ONGOING
            && $prizeCalculation['confirmed_count'] >= 2
            && $prizeCalculation['confirmed_count'] < (int) $tournament->max_participants;

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
        $defeatXp = 0;
        $currentMatch = null;
        if ($user && $userRegistration) {
            // Always expose the participant's actionable match on the overview.
            // Bracket data remains lazy-loaded, so this focused indexed lookup
            // avoids loading every round just to provide the Match Room link.
            $currentMatch = GameMatch::query()
                ->where('tournament_id', $tournament->id)
                ->where(function ($query) use ($userRegistration): void {
                    $query->where('player_a_registration_id', $userRegistration->id)
                        ->orWhere('player_b_registration_id', $userRegistration->id);
                })
                ->whereIn('status', [
                    MatchStatus::READY,
                    MatchStatus::IN_PROGRESS,
                    MatchStatus::RESULT_SUBMITTED,
                    MatchStatus::WAITING_FOR_CONFIRMATION,
                    MatchStatus::DISPUTED,
                ])
                ->with('round:id,round_number')
                ->latest('updated_at')
                ->first(['id', 'uuid', 'round_id', 'status', 'updated_at']);

            $hasLost = GameMatch::where('tournament_id', $tournament->id)
                ->where(function ($query) use ($userRegistration) {
                    $query->where('player_a_registration_id', $userRegistration->id)
                        ->orWhere('player_b_registration_id', $userRegistration->id);
                })
                ->whereIn('status', [MatchStatus::COMPLETED, MatchStatus::FORFEITED])
                ->whereNotNull('winner_registration_id')
                ->where('winner_registration_id', '!=', $userRegistration->id)
                ->exists();

            if ($hasLost) {
                $defeatXp = (int) PlayerExperienceAward::query()
                    ->where('user_id', $user->id)
                    ->where('source_type', 'tournament')
                    ->where('source_id', $tournament->id)
                    ->sum('amount');
            }
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
        $canCancelRegistration = (int) $tournament->workflow_version === 2
            ? $isRegistered && $tournament->start_at?->copy()->subMinutes(30)->isFuture()
                && ! $tournament->cancellationRequests()->where('status', 'pending')->exists()
            : $isRegistered && $userRegistration?->locked_at === null
                && $tournament->extra_registration_started_at === null && in_array($tournament->status, [
                    TournamentStatus::REGISTRATION_OPEN,
                    TournamentStatus::PUBLISHED,
                ], true);

        $gameIdSettings = (array) (($tournament->game->game_id_settings ?? [])[(string) ($this->selectedPlatformId ?? $tournament->platform_id)]
            ?? ($tournament->game->game_id_settings['default'] ?? []));
        $userTournamentTeam = $user ? TournamentTeam::query()
            ->where('tournament_id', $tournament->id)
            ->whereHas('members', fn ($members) => $members->where('user_id', $user->id))
            ->with(['members.user:id,username'])
            ->first() : null;
        $isSearchingForTeam = $user ? TournamentTeamSearchEntry::query()
            ->where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->where('status', 'searching')
            ->exists() : false;
        $userSquad = $user ? Team::query()->where('captain_user_id', $user->id)->where('status', 'active')->first(['id', 'name']) : null;
        $pendingCancellationRequest = $user && (int) $tournament->workflow_version === 2
            ? $tournament->cancellationRequests()
                ->with(['requester:id,username', 'votes:id,request_id,voter_id,approved'])
                ->where('status', 'pending')
                ->first()
            : null;

        return view('livewire.tournament.tournament-detail', [
            'tournament' => $tournament,
            'competitionPlatforms' => Platform::query()->whereIn('id', $tournament->supportedPlatformIds())->orderBy('name')->get(),
            'prizeCalculation' => $prizeCalculation,
            'displayPrizeCalculation' => $displayPrizeCalculation,
            'isV2Registration' => $isV2Registration,
            'shouldShowUnderfilledNotice' => $shouldShowUnderfilledNotice,
            'isRegistered' => $isRegistered,
            'userRegistration' => $userRegistration,
            'rounds' => $rounds,
            'allMatches' => $allMatches,
            'activityLogs' => $activityLogs,
            'hasLost' => $hasLost,
            'defeatXp' => $defeatXp,
            'currentMatch' => $currentMatch,
            'streamService' => $streamService,
            'canCancelRegistration' => $canCancelRegistration,
            'canViewRestricted' => $canViewRestricted,
            'participantsLoaded' => $participantsLoaded,
            'bracketLoaded' => $bracketLoaded,
            'activityLoaded' => $activityLoaded,
            'gameIdSettings' => $gameIdSettings,
            'userTournamentTeam' => $userTournamentTeam,
            'isSearchingForTeam' => $isSearchingForTeam,
            'userSquad' => $userSquad,
            'pendingCancellationRequest' => $pendingCancellationRequest,
        ])->layout($this->layout, ['title' => $tournament->name.' | PlayerSaloons', 'dashboard_title' => 'TOURNAMENT DETAILS']);
    }
}
