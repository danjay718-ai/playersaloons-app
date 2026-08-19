<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\CMS\Models\Game;
use App\Modules\Identity\Actions\ApplyComplianceBlockAction;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Actions\ResolveDisputeAction;
use App\Modules\Match\Actions\ResolveHeadToHeadDisputeAction;
use App\Modules\Match\Events\MatchCompleted;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\HeadToHeadMatch;
use App\Modules\Match\Models\MatchDispute;
use App\Modules\Match\StateMachines\MatchStateMachine;
use App\Shared\Enums\DisputeResolution;
use App\Shared\Enums\DisputeStatus;
use App\Shared\Enums\HeadToHeadDisputeResolution;
use App\Shared\Enums\HeadToHeadMatchStatus;
use App\Shared\Enums\MatchStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

class MatchAdmin extends AdminComponent
{
    use WithPagination;

    // Filters
    public string $search = '';

    public string $statusFilter = '';

    public string $gameFilter = '';

    public bool $disputeFilter = false;

    public int $perPage = 15;

    // Modal state
    public bool $showDetailModal = false;

    public bool $showOverrideModal = false;

    public bool $showDisputeModal = false;

    public bool $showH2HDisputeModal = false;

    // Selection
    public ?int $selectedMatchId = null;

    public ?int $selectedDisputeId = null;

    public ?int $selectedH2HMatchId = null;

    // Forms
    public ?int $winnerRegistrationId = null;

    public string $resolution = '';

    public string $complianceUserId = '';

    public int $complianceBanDays = 7;

    public string $complianceBanReason = '';

    public string $h2hResolution = '';

    protected $paginationTheme = 'tailwind';

    public function mount(): void
    {
        if (request()->query('filter') === 'disputes') {
            $this->disputeFilter = true;
        }
    }

    // ─── Reset page on filter changes ────────────────────────────────────────

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingGameFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDisputeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    // ─── Modal openers ────────────────────────────────────────────────────────

    public function selectMatch(int $id): void
    {
        $this->selectedMatchId = $id;
        $this->winnerRegistrationId = null;
        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->showOverrideModal = false;
        $this->selectedMatchId = null; // free the heavy query on next render
    }

    public function openOverrideModal(int $id): void
    {
        $this->selectedMatchId = $id;
        $match = GameMatch::select(['id', 'winner_registration_id', 'player_a_registration_id'])
            ->findOrFail($id);
        $this->winnerRegistrationId = (int) ($match->winner_registration_id ?? $match->player_a_registration_id);
        $this->showOverrideModal = true;
    }

    public function openDisputeModal(int $disputeId): void
    {
        $this->selectedDisputeId = $disputeId;
        $this->resolution = '';
        $this->resetComplianceBanForm();
        $this->showDisputeModal = true;
    }

    public function closeDisputeModal(): void
    {
        $this->showDisputeModal = false;
        $this->selectedDisputeId = null; // free the query on next render
        $this->resetComplianceBanForm();
    }

    public function openH2HDisputeModal(int $matchId): void
    {
        $this->selectedH2HMatchId = $matchId;
        $this->h2hResolution = '';
        $this->showH2HDisputeModal = true;
    }

    public function closeH2HDisputeModal(): void
    {
        $this->showH2HDisputeModal = false;
        $this->selectedH2HMatchId = null;
    }

    // ─── Actions ─────────────────────────────────────────────────────────────

    public function overrideResult(MatchStateMachine $stateMachine): void
    {
        $this->validate(['winnerRegistrationId' => 'required|integer']);

        $match = GameMatch::findOrFail($this->selectedMatchId);

        $actor = Auth::user();
        if (! $actor || ! $actor->can('submitResult', $match)) {
            abort(403);
        }

        if ($this->winnerRegistrationId !== $match->player_a_registration_id
            && $this->winnerRegistrationId !== $match->player_b_registration_id) {
            session()->flash('error', 'Winner must be one of the match participants.');

            return;
        }

        try {
            DB::transaction(function () use ($match, $stateMachine) {
                if ($match->status === MatchStatus::DISPUTED) {
                    $dispute = MatchDispute::where('match_id', $match->id)
                        ->where('status', DisputeStatus::OPEN)
                        ->first();
                    if ($dispute) {
                        $dispute->status = DisputeStatus::RESOLVED;
                        $dispute->resolution = $this->winnerRegistrationId === $match->player_a_registration_id
                            ? DisputeResolution::PLAYER_A
                            : DisputeResolution::PLAYER_B;
                        $dispute->resolved_by = Auth::id();
                        $dispute->resolved_at = now();
                        $dispute->save();
                    }
                }

                $match->winner_registration_id = $this->winnerRegistrationId;
                $match->save();

                if ($match->status === MatchStatus::PENDING) {
                    $stateMachine->transition($match, MatchStatus::READY);
                }
                if ($match->status === MatchStatus::READY) {
                    $stateMachine->transition($match, MatchStatus::IN_PROGRESS);
                }
                if ($match->status === MatchStatus::IN_PROGRESS) {
                    $stateMachine->transition($match, MatchStatus::WAITING_FOR_CONFIRMATION);
                }
                if ($match->status === MatchStatus::WAITING_FOR_CONFIRMATION
                    || $match->status === MatchStatus::RESULT_SUBMITTED
                    || $match->status === MatchStatus::DISPUTED) {
                    $stateMachine->transition($match, MatchStatus::COMPLETED);
                }

                MatchCompleted::dispatch($match->id, $match->tournament_id, $this->winnerRegistrationId);
            });

            session()->flash('success', 'Match result overridden and advanced successfully.');
            $this->closeDetailModal();
        } catch (\Exception $e) {
            session()->flash('error', $this->safeError($e, 'Unable to override the match result.'));
        }
    }

    public function resolveDispute(ResolveDisputeAction $resolver, ApplyComplianceBlockAction $blocker): void
    {
        $this->validate([
            'resolution' => 'required|string|in:player_a,player_b,rematch',
            'complianceUserId' => 'nullable|integer',
            'complianceBanDays' => 'required_with:complianceUserId|integer|min:1|max:3650',
            'complianceBanReason' => 'required_with:complianceUserId|nullable|string|min:10|max:1000',
        ]);

        if (! $this->selectedDisputeId) {
            return;
        }

        $dispute = MatchDispute::query()
            ->with(['match.playerARegistration.user', 'match.playerBRegistration.user'])
            ->findOrFail($this->selectedDisputeId);
        $resolutionEnum = DisputeResolution::from($this->resolution);
        /** @var User|null $actor */
        $actor = Auth::user();

        if (! $actor) {
            return;
        }

        try {
            DB::transaction(function () use ($resolver, $blocker, $dispute, $actor, $resolutionEnum): void {
                $resolver->execute($dispute, $actor, $resolutionEnum);

                if ($this->complianceUserId !== '') {
                    $participantUsers = collect([
                        $dispute->match->playerARegistration?->user,
                        $dispute->match->playerBRegistration?->user,
                    ])->filter();
                    /** @var User|null $target */
                    $target = $participantUsers->firstWhere('id', (int) $this->complianceUserId);

                    if (! $target) {
                        throw new \LogicException('The compliance block target must be a participant in this match.');
                    }

                    $blocker->execute(
                        $target,
                        $actor,
                        'fraud',
                        sprintf(
                            'False match proof (match %s, dispute #%d): %s',
                            $dispute->match->uuid,
                            $dispute->id,
                            trim($this->complianceBanReason)
                        ),
                        now()->addDays($this->complianceBanDays)
                    );
                }
            });
            session()->flash('success', $this->complianceUserId !== ''
                ? 'Dispute resolved and timed compliance block applied.'
                : 'Dispute resolved successfully.');
            $this->closeDisputeModal();
            $this->closeDetailModal();
        } catch (\Exception $e) {
            session()->flash('error', $this->safeError($e, 'Unable to resolve the match dispute.'));
        }
    }

    private function resetComplianceBanForm(): void
    {
        $this->complianceUserId = '';
        $this->complianceBanDays = 7;
        $this->complianceBanReason = '';
    }

    public function resolveH2HDispute(ResolveHeadToHeadDisputeAction $resolver): void
    {
        $this->validate(['h2hResolution' => 'required|string|in:player_a,player_b,refund']);

        if (! $this->selectedH2HMatchId) {
            return;
        }

        $match = HeadToHeadMatch::query()->findOrFail($this->selectedH2HMatchId);
        $actor = Auth::user();

        if (! $actor) {
            return;
        }

        try {
            $resolver->execute($match, $actor, HeadToHeadDisputeResolution::from($this->h2hResolution));
            session()->flash('success', 'Head-to-head dispute resolved successfully.');
            $this->closeH2HDisputeModal();
        } catch (\Exception $e) {
            session()->flash('error', $this->safeError($e, 'Unable to resolve the head-to-head dispute.'));
        }
    }

    // ─── Computed properties (only run when modal is open) ───────────────────

    /**
     * Load the selected match with full relations — only when detail modal is open.
     * Cached per request via #[Computed].
     */
    #[Computed]
    public function selectedMatch(): ?GameMatch
    {
        if (! $this->showDetailModal || ! $this->selectedMatchId) {
            return null;
        }

        return GameMatch::with([
            'tournament.game.translations',
            'playerARegistration.user',
            'playerBRegistration.user',
            'winnerRegistration.user',
            'round',
            'disputes.openedBy',
            'disputes.evidence.uploadedBy',
        ])->find($this->selectedMatchId);
    }

    /**
     * Load the selected dispute for the resolve modal — only when that modal is open.
     * Cached per request via #[Computed].
     */
    #[Computed]
    public function selectedDispute(): ?MatchDispute
    {
        if (! $this->showDisputeModal || ! $this->selectedDisputeId) {
            return null;
        }

        return MatchDispute::with([
            'match.playerARegistration.user',
            'match.playerBRegistration.user',
            'openedBy',
            'evidence.uploadedBy',
        ])->find($this->selectedDisputeId);
    }

    #[Computed]
    public function selectedH2HMatch(): ?HeadToHeadMatch
    {
        if (! $this->showH2HDisputeModal || ! $this->selectedH2HMatchId) {
            return null;
        }

        return HeadToHeadMatch::with([
            'creator',
            'opponent',
            'winner',
            'resultSubmitter',
            'disputer',
            'disputeResolver',
            'game.translations',
            'platform',
        ])->find($this->selectedH2HMatchId);
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render()
    {
        // Only eager-load 'disputes' on the list when the dispute filter or dispute actions need it
        $with = ['tournament.game.translations', 'playerARegistration.user', 'playerBRegistration.user', 'winnerRegistration.user'];
        if ($this->disputeFilter || ! $this->statusFilter) {
            // include disputes for the quick-action gavel button in rows
            $with[] = 'disputes';
        }

        $query = GameMatch::query()
            ->with($with)
            ->orderBy('updated_at', 'desc');

        if ($this->search) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('tournament', fn ($tq) => $tq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('playerARegistration.user', fn ($uq) => $uq->where('username', 'like', "%{$search}%"))
                    ->orWhereHas('playerBRegistration.user', fn ($uq) => $uq->where('username', 'like', "%{$search}%"));
            });
        }

        if ($this->gameFilter) {
            $query->whereHas('tournament', fn ($q) => $q->where('game_id', $this->gameFilter));
        }

        if ($this->disputeFilter) {
            $query->where('status', MatchStatus::DISPUTED->value);
        } elseif ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        $matches = $query->paginate($this->perPage);
        $games = Game::with('translations')->orderBy('slug')->get();
        $h2hDisputes = HeadToHeadMatch::query()
            ->with(['creator', 'opponent', 'winner', 'resultSubmitter', 'disputer', 'game.translations', 'platform'])
            ->where('status', HeadToHeadMatchStatus::DISPUTED)
            ->latest('updated_at')
            ->take(10)
            ->get();

        return view('livewire.admin.match-admin', [
            'matches' => $matches,
            'games' => $games,
            'h2hDisputes' => $h2hDisputes,
        ])->layout('components.layouts.admin', [
            'admin_title' => 'Match & Dispute Control',
        ]);
    }
}
