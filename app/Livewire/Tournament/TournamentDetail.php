<?php

declare(strict_types=1);

namespace App\Livewire\Tournament;

use App\Livewire\Concerns\HandlesUserFacingErrors;
use App\Modules\CMS\Models\Platform;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Operations\Models\Activity;
use App\Modules\Stream\Support\StreamEmbedService;
use App\Modules\Team\Models\Team;
use App\Modules\Tournament\Actions\CancelRegistrationAction;
use App\Modules\Tournament\Actions\FindTournamentTeamAction;
use App\Modules\Tournament\Actions\RegisterForTournamentAction;
use App\Modules\Tournament\Actions\RegisterForV2TournamentAction;
use App\Modules\Tournament\Actions\RequestV2CancellationAction;
use App\Modules\Tournament\Actions\VoteOnV2CancellationAction;
use App\Modules\Tournament\Exceptions\TournamentAlreadyRegisteredException;
use App\Modules\Tournament\Exceptions\TournamentFullException;
use App\Modules\Tournament\Exceptions\TournamentNotOpenForRegistrationException;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentCancellationRequest;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Modules\Tournament\Models\TournamentTeam;
use App\Modules\Tournament\Models\TournamentTeamSearchEntry;
use App\Modules\Tournament\Services\PrizeCalculationService;
use App\Modules\Tournament\Services\V2CancellationPolicy;
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
use LogicException;

class TournamentDetail extends Component
{
    use HandlesUserFacingErrors;

    public string $uuid;

    public string $layout = 'components.layouts.dashboard';

    #[Url]
    public string $activeTab = 'matches';

    #[Url(as: 'match')]
    public string $selectedMatchUuid = '';

    /** Public discovery links must not unexpectedly switch into the player shell. */
    #[Url(as: 'view')]
    public string $viewMode = '';

    /** @var array<string, bool> */
    public array $loadedSections = [];

    public string $gameIdValue = '';

    public ?int $selectedPlatformId = null;

    public string $cancellationError = '';

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;

        if (in_array($this->activeTab, ['fixtures', 'bracket'], true)) {
            $this->activeTab = 'matches';
        }

        $user = Auth::user();
        if ($user && $this->viewMode !== 'guest' && $user->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'TOURNAMENT_ORGANIZER'])) {
            $this->layout = 'components.layouts.admin';
        } elseif ($user && $this->viewMode !== 'guest') {
            $this->layout = 'components.layouts.dashboard';
        } else {
            // Guest visitors use app layout
            $this->layout = 'components.layouts.app';
        }

        $tournament = $this->getTournamentQuery()->where('uuid', $uuid)->firstOrFail();
        if ($user?->can('viewRestrictedDetails', $tournament)) {
            $this->loadSection($this->activeTab);
        } elseif (! in_array($this->activeTab, ['overview', 'streams'], true)) {
            $this->activeTab = 'overview';
        }

        if ($user) {
            $this->selectedPlatformId = $tournament->platform_id ?? $tournament->supportedPlatformIds()[0] ?? null;
        }
    }

    public function updatedSelectedPlatformId(): void
    {
        $this->gameIdValue = '';
    }

    public function prepareRegistrationPrompt(): void
    {
        $tournament = $this->getTournamentQuery()->where('uuid', $this->uuid)->firstOrFail();
        $this->resetValidation();
        $this->gameIdValue = '';
        $this->selectedPlatformId = $tournament->platform_id ?? $tournament->supportedPlatformIds()[0] ?? null;
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
            'matches', 'fixtures', 'bracket' => 'bracket',
            'activity' => 'activity',
            default => null,
        };

        if ($section !== null) {
            $this->loadedSections[$section] = true;
        }
    }

    public function openMatch(string $matchUuid): void
    {
        $user = Auth::user();
        abort_if($user === null, 403);

        $tournament = $this->getTournamentQuery()
            ->where('uuid', $this->uuid)
            ->firstOrFail(['id', 'workflow_version']);
        abort_unless((int) $tournament->workflow_version === 2, 404);

        $registration = TournamentRegistration::query()
            ->where('tournament_id', $tournament->id)
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhereHas('rosterMembers', fn ($members) => $members->where('user_id', $user->id));
            })
            ->whereNotIn('status', [RegistrationStatus::CANCELLED, RegistrationStatus::REFUNDED])
            ->firstOrFail(['id']);

        $match = GameMatch::query()
            ->where('tournament_id', $tournament->id)
            ->where('uuid', $matchUuid)
            ->where(function ($query) use ($registration): void {
                $query->where('player_a_registration_id', $registration->id)
                    ->orWhere('player_b_registration_id', $registration->id);
            })
            ->firstOrFail(['id', 'status']);

        $this->selectedMatchUuid = $matchUuid;
        $this->activeTab = 'submit-results';
        $this->dispatch('tournament-content-opened', tournamentUuid: $this->uuid, matchUuid: $matchUuid, tab: 'submit-results',
            focus: $match->status === MatchStatus::DISPUTED ? 'dispute' : null);
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
                    $formed = $findTeam->execute($tournament, $user, $this->gameIdValue, 'auto', $this->selectedPlatformId);
                    session()->flash('message', $formed && (int) $formed->leader_user_id === (int) $user->id
                        ? 'Your tournament team is complete. Click Register Team to finalize the entry.'
                        : ($formed ? 'Your tournament team is complete. The Team Leader will finalize registration.' : 'You are now looking for a team. There is no charge until a full team is formed.'));
                    $this->dispatch('tournament-registration-completed');

                    return;
                }
            }

            if ((int) $tournament->workflow_version === 2) {
                $v2Action->execute($tournament, $user, $squad, $this->gameIdValue, 'auto', $tournamentTeam, $this->selectedPlatformId);
            } else {
                $action->execute($tournament, $user, $squad, $this->gameIdValue, 'auto', $tournamentTeam, $this->selectedPlatformId);
            }
            session()->flash('message', ($tournament->team_size ?? 1) > 1 ? 'Tournament team registered successfully!' : 'Successfully joined the tournament!');
            $this->dispatch('tournament-registration-completed');
        } catch (InsufficientBalanceException $e) {
            session()->flash('error', 'Insufficient balance. Please top up your wallet to pay the entrance fee.');
        } catch (TournamentAlreadyRegisteredException) {
            session()->flash('error', 'You are already registered for this tournament occurrence.');
        } catch (TournamentFullException) {
            session()->flash('error', 'This tournament occurrence is already full.');
        } catch (TournamentNotOpenForRegistrationException) {
            session()->flash('error', 'This tournament occurrence is not open for registration.');
        } catch (LogicException $e) {
            session()->flash('error', $e->getMessage());
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
                    ? __('Registration cancelled. The applicable refund has been credited to your wallet.')
                    : "Cancellation request created. {$request->required_approvals} approval(s) are required before tournament start.");
            } else {
                $action->execute($registration, $user);
                session()->flash('message', 'Registration cancelled successfully. Any entry fee has been refunded to your wallet.');
            }
            $this->dispatch('wallet-balance-updated');
            $this->dispatch('registration-cancellation-completed');
        } catch (LogicException $e) {
            $this->cancellationError = $e->getMessage();
            session()->flash('error', $this->cancellationError);
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
        } catch (LogicException $e) {
            session()->flash('error', $e->getMessage());
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
                    throw new LogicException('Team leadership is locked after registration closes.');
                }

                $team = TournamentTeam::query()
                    ->where('tournament_id', $tournament->id)
                    ->where('leader_user_id', $user->id)
                    ->where('status', 'ready')
                    ->lockForUpdate()
                    ->firstOrFail();
                if (! $team->members()->where('user_id', $userId)->exists()) {
                    throw new LogicException('The new Team Leader must be part of this tournament team.');
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
                'brackets.rounds.matches.playerARegistration.user.profile',
                'brackets.rounds.matches.playerBRegistration.user.profile',
                'brackets.rounds.matches.winnerRegistration.user.profile',
                'brackets.rounds.matches.round',
            ]);
        } else {
            $tournament->setRelation('brackets', collect());
        }

        $hasLost = false;
        $isChampion = false;
        $isSecondPlace = false;
        $secondPlacePrize = null;
        $awaitingNextMatch = false;
        $currentMatch = null;
        $currentMatchHasSubmittedResult = false;
        $displayMatch = null;
        if ($user && $userRegistration) {
            // Always expose the participant's actionable match on the overview.
            // Bracket data remains lazy-loaded, so this focused indexed lookup
            // avoids loading every round just to provide the result submission link.
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
                ->first(['id', 'uuid', 'round_id', 'status', 'active_attempt_number', 'updated_at']);

            if ($currentMatch !== null && (int) $tournament->workflow_version === 2) {
                $currentMatchHasSubmittedResult = $currentMatch->attempts()
                    ->where('attempt_number', $currentMatch->active_attempt_number)
                    ->whereHas('submissions', fn ($query) => $query->where('registration_id', $userRegistration->id))
                    ->exists();
            }

            $participantMatches = GameMatch::query()
                ->where('tournament_id', $tournament->id)
                ->where(function ($query) use ($userRegistration): void {
                    $query->where('player_a_registration_id', $userRegistration->id)
                        ->orWhere('player_b_registration_id', $userRegistration->id);
                });
            if ($this->selectedMatchUuid !== '') {
                $displayMatch = (clone $participantMatches)
                    ->where('uuid', $this->selectedMatchUuid)
                    ->first(['id', 'uuid', 'status', 'updated_at']);
            }
            $displayMatch ??= $currentMatch;
            $displayMatch ??= (clone $participantMatches)
                ->latest('updated_at')
                ->first(['id', 'uuid', 'status', 'updated_at']);

            $hasLost = GameMatch::where('tournament_id', $tournament->id)
                ->where(function ($query) use ($userRegistration) {
                    $query->where('player_a_registration_id', $userRegistration->id)
                        ->orWhere('player_b_registration_id', $userRegistration->id);
                })
                ->whereIn('status', [MatchStatus::COMPLETED, MatchStatus::FORFEITED])
                ->whereNotNull('winner_registration_id')
                ->where('winner_registration_id', '!=', $userRegistration->id)
                ->exists();

            $finalRoundNumber = (int) $tournament->rounds()->max('round_number');
            if ($finalRoundNumber > 0) {
                $lastSettledParticipantMatch = GameMatch::query()
                    ->where('tournament_id', $tournament->id)
                    ->where(function ($query) use ($userRegistration): void {
                        $query->where('player_a_registration_id', $userRegistration->id)
                            ->orWhere('player_b_registration_id', $userRegistration->id);
                    })
                    ->whereIn('status', [MatchStatus::COMPLETED, MatchStatus::FORFEITED])
                    ->whereNotNull('winner_registration_id')
                    ->with('round:id,round_number')
                    ->get(['id', 'round_id', 'player_a_registration_id', 'player_b_registration_id', 'winner_registration_id'])
                    ->sortBy(fn (GameMatch $participantMatch): string => sprintf(
                        '%010d:%010d',
                        (int) ($participantMatch->round?->round_number ?? 0),
                        $participantMatch->id,
                    ))
                    ->last();

                if ($lastSettledParticipantMatch !== null) {
                    $participantRoundNumber = (int) ($lastSettledParticipantMatch->round?->round_number ?? 0);
                    $wonLatestMatch = (int) $lastSettledParticipantMatch->winner_registration_id === (int) $userRegistration->id;

                    if ($participantRoundNumber === $finalRoundNumber) {
                        $isChampion = $wonLatestMatch;
                        if (! $wonLatestMatch) {
                            $configuredSecondPrize = $tournament->finalized_second_prize ?? $tournament->prize_2nd ?? '0.00';
                            if ((float) $configuredSecondPrize > 0) {
                                $isSecondPlace = true;
                                $secondPlacePrize = $configuredSecondPrize;
                            }
                        }
                    } elseif ($wonLatestMatch && $participantRoundNumber < $finalRoundNumber) {
                        $awaitingNextMatch = true;
                    }
                }
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

        $pendingCancellationRequest = $user && (int) $tournament->workflow_version === 2
            ? $tournament->cancellationRequests()
                ->with(['requester:id,username', 'votes:id,request_id,voter_id,approved'])
                ->where('status', 'pending')
                ->first()
            : null;
        $cancellationAmounts = V2CancellationPolicy::amounts($tournament,
            $pendingCancellationRequest && (int) $pendingCancellationRequest->requested_by === (int) $user?->id
                ? $pendingCancellationRequest->requested_at : now());
        $canCancelRegistration = (int) $tournament->workflow_version === 2
            ? $isRegistered && V2CancellationPolicy::isOpen($tournament)
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
            'isChampion' => $isChampion,
            'isSecondPlace' => $isSecondPlace,
            'secondPlacePrize' => $secondPlacePrize,
            'awaitingNextMatch' => $awaitingNextMatch,
            'currentMatch' => $currentMatch,
            'currentMatchHasSubmittedResult' => $currentMatchHasSubmittedResult,
            'displayMatch' => $displayMatch,
            'streamService' => $streamService,
            'canCancelRegistration' => $canCancelRegistration,
            'cancellationAmounts' => $cancellationAmounts,
            'canViewRestricted' => $canViewRestricted,
            'participantsLoaded' => $participantsLoaded,
            'bracketLoaded' => $bracketLoaded,
            'activityLoaded' => $activityLoaded,
            'gameIdSettings' => $gameIdSettings,
            'userTournamentTeam' => $userTournamentTeam,
            'isSearchingForTeam' => $isSearchingForTeam,
            'userSquad' => $userSquad,
            'pendingCancellationRequest' => $pendingCancellationRequest,
        ])->layout($this->layout, ['title' => $tournament->name.' | GamersRival', 'dashboard_title' => 'TOURNAMENT DETAILS']);
    }
}
