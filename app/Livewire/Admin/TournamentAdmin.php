<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\Platform;
use App\Modules\Tournament\Actions\CancelTournamentAction;
use App\Modules\Tournament\Actions\CloseCheckinAction;
use App\Modules\Tournament\Actions\CloseRegistrationAction;
use App\Modules\Tournament\Actions\CompleteTournamentAction;
use App\Modules\Tournament\Actions\GenerateBracketAction;
use App\Modules\Tournament\Actions\OpenCheckinAction;
use App\Modules\Tournament\Actions\OpenRegistrationAction;
use App\Modules\Tournament\Actions\ProcessRefundAction;
use App\Modules\Tournament\Actions\PublishTournamentAction;
use App\Modules\Tournament\Actions\StartTournamentAction;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentTemplate;
use App\Modules\Tournament\StateMachines\TournamentStateMachine;
use App\Shared\Enums\CompetitionType;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class TournamentAdmin extends AdminComponent
{
    use WithFileUploads, WithPagination;

    public function boot(): void
    {
        parent::boot();
        abort_unless($this->actor()->can('tournaments.view'), 403);
    }

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $gameFilter = '';

    #[Url]
    public string $platformFilter = '';

    #[Url]
    public string $activeTab = 'all';

    /** Status group tab: 'active' (default, excludes cancelled/completed/refunded) | 'completed' | 'cancelled' | 'all' */
    #[Url]
    public string $statusTab = 'active';

    #[Url]
    public string $startDateFilter = '';

    #[Url]
    public string $endDateFilter = '';

    #[Url]
    public int $perPage = 10;

    // Modal control
    public bool $showDetailModal = false;

    public bool $showCancelModal = false;

    public bool $showDeleteModal = false;

    // Selected ID
    public ?int $selectedTournamentId = null;

    // Cancel form
    public string $cancelReason = '';

    public string $cancelNotes = '';

    protected $paginationTheme = 'tailwind';

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

    public function updatingPlatformFilter(): void
    {
        $this->resetPage();
    }

    public function updatingActiveTab(): void
    {
        $this->resetPage();
    }

    public function updatingStatusTab(): void
    {
        $this->resetPage();
        $this->statusFilter = ''; // clear per-status dropdown when switching group tabs
    }

    public function updatingStartDateFilter(): void
    {
        $this->resetPage();
    }

    public function updatingEndDateFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function selectTournament(int $id): void
    {
        $this->selectedTournamentId = $id;
        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedTournamentId = null;
    }

    public function closeCancelModal(): void
    {
        $this->showCancelModal = false;
        // Don't nullify selectedTournamentId if detail modal is still open
        if (! $this->showDetailModal) {
            $this->selectedTournamentId = null;
        }
    }

    public function setRecurringScheduleState(int $templateId, bool $active): void
    {
        $template = TournamentTemplate::query()->findOrFail($templateId);
        $actor = Auth::user();
        if ($actor === null || (! $actor->hasPermissionTo('tournaments.manage') && (int) $template->created_by !== (int) $actor->id)) {
            abort(403);
        }
        $template->update(['is_recurring' => $active]);

        activity()
            ->causedBy(Auth::user())
            ->performedOn($template)
            ->withProperties(['is_recurring' => $active])
            ->log($active ? 'recurring_schedule_resumed' : 'recurring_schedule_paused');

        session()->flash('success', $active ? 'Recurring schedule resumed.' : 'Recurring schedule paused.');
    }

    /** @var string[] */
    private const ALLOWED_TRANSITIONS = [
        'publish',
        'open_registration',
        'close_registration',
        'open_checkin',
        'close_checkin',
        'generate_bracket',
        'start',
        'complete',
        'process_refund',
        'reopen_checkin',
        'reopen_registration',
    ];

    // Lifecycle transitions
    public function applyTransitionById(int $tournamentId, string $transitionName): void
    {
        $this->selectedTournamentId = $tournamentId;
        $this->applyTransition($transitionName);
    }

    public function applyTransition(string $transitionName): void
    {
        if (! $this->selectedTournamentId) {
            return;
        }

        if (! in_array($transitionName, self::ALLOWED_TRANSITIONS, strict: true)) {
            session()->flash('error', 'Invalid transition.');

            return;
        }

        $tournament = Tournament::findOrFail($this->selectedTournamentId);

        $actor = Auth::user();
        if (! $actor || ! $actor->can('manage', $tournament)) {
            abort(403);
        }

        $stateMachine = app(TournamentStateMachine::class);

        if ((int) $tournament->workflow_version === 2) {
            session()->flash('error', 'Tournament V2 lifecycle transitions are automatic. Use match/dispute operations for active play.');

            return;
        }

        try {
            match ($transitionName) {
                'publish' => app(PublishTournamentAction::class)->execute($tournament),
                'open_registration' => app(OpenRegistrationAction::class)->execute($tournament),
                'close_registration' => app(CloseRegistrationAction::class)->execute($tournament),
                'open_checkin' => app(OpenCheckinAction::class)->execute($tournament),
                'close_checkin' => app(CloseCheckinAction::class)->execute($tournament),
                'generate_bracket' => app(GenerateBracketAction::class)->execute($tournament),
                'start' => app(StartTournamentAction::class)->execute($tournament),
                'complete' => app(CompleteTournamentAction::class)->execute($tournament),
                'process_refund' => app(ProcessRefundAction::class)->execute($tournament),
                'reopen_checkin' => $stateMachine->transition($tournament, TournamentStatus::CHECKIN_OPEN, ['triggered_by' => 'admin_manual', 'user_id' => Auth::id()]),
                'reopen_registration' => $stateMachine->transition($tournament, TournamentStatus::REGISTRATION_OPEN, ['triggered_by' => 'admin_manual', 'user_id' => Auth::id()]),
            };
            session()->flash('success', 'State transition executed successfully.');
        } catch (\Exception $e) {
            session()->flash('error', $this->safeError($e, 'Unable to apply the tournament transition.'));
        }
    }

    public function openCancelModal(int $id): void
    {
        $this->selectedTournamentId = $id;
        $this->cancelReason = '';
        $this->cancelNotes = '';
        $this->showCancelModal = true;
    }

    public function openDeleteModal(int $id): void
    {
        $this->selectedTournamentId = $id;
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->selectedTournamentId = null;
    }

    public function cancelTournament(CancelTournamentAction $cancelAction): void
    {
        $this->validate([
            'cancelReason' => 'required|string|min:5|max:255',
            'cancelNotes' => 'nullable|string',
        ]);

        if (! $this->selectedTournamentId) {
            return;
        }

        $tournament = Tournament::findOrFail($this->selectedTournamentId);
        $actor = Auth::user();

        if (! $actor) {
            return;
        }

        if (! $actor->can('cancel', $tournament)) {
            abort(403);
        }

        try {
            $cancelAction->execute($tournament, $actor, $this->cancelReason, $this->cancelNotes);
            session()->flash('success', 'Tournament cancelled and refunds processed successfully.');
            $this->closeCancelModal();
            $this->closeDetailModal();
        } catch (\Exception $e) {
            session()->flash('error', $this->safeError($e, 'Unable to cancel the tournament.'));
        }
    }

    public function deleteTournament(): void
    {
        if (! $this->selectedTournamentId) {
            return;
        }

        $tournament = Tournament::withCount('registrations')->findOrFail($this->selectedTournamentId);

        if ($tournament->status !== TournamentStatus::DRAFT) {
            session()->flash('error', 'Only draft tournaments can be deleted.');

            return;
        }

        if ($tournament->registrations_count > 0) {
            session()->flash('error', 'Cannot delete a tournament that has registrations.');

            return;
        }

        app(\App\Modules\Operations\Services\AdminDeletionService::class)->delete('tournaments', [$tournament->id], $this->actor());
        session()->flash('success', 'Tournament deleted successfully.');
        $this->closeDeleteModal();
    }

    public function render()
    {
        $query = Tournament::query()
            ->with(['game.translations', 'creator', 'platform', 'template', 'cancellation'])
            ->withCount('registrations')
            ->orderBy('created_at', 'desc');

        // Status group tab — default 'active' excludes completed/cancelled/refunded
        $activeStatuses = [
            TournamentStatus::DRAFT->value,
            TournamentStatus::PUBLISHED->value,
            TournamentStatus::REGISTRATION_OPEN->value,
            TournamentStatus::REGISTRATION_CLOSED->value,
            TournamentStatus::CHECKIN_OPEN->value,
            TournamentStatus::CHECKIN_CLOSED->value,
            TournamentStatus::BRACKET_GENERATED->value,
            TournamentStatus::ONGOING->value,
        ];

        match ($this->statusTab) {
            'active' => $query->whereIn('status', $activeStatuses),
            'completed' => $query->whereIn('status', [TournamentStatus::COMPLETED->value]),
            'cancelled' => $query->whereIn('status', [TournamentStatus::CANCELLED->value, TournamentStatus::REFUNDED->value]),
            default => null, // 'all' — no status restriction
        };

        $this->applyFilters($query, includeStatus: true);

        $v2Templates = null;
        if (config('features.tournament_v2.enabled')) {
            $v2Occurrences = Tournament::query()
                ->where('workflow_version', 2)
                ->where('competition_type', CompetitionType::TOURNAMENT);
            match ($this->statusTab) {
                'active' => $v2Occurrences->whereIn('status', $activeStatuses),
                'completed' => $v2Occurrences->where('status', TournamentStatus::COMPLETED->value),
                'cancelled' => $v2Occurrences->whereIn('status', [TournamentStatus::CANCELLED->value, TournamentStatus::REFUNDED->value]),
                default => null,
            };
            $this->applyFilters($v2Occurrences, includeStatus: true);

            // V2 occurrences are immutable operational records. Grouping is
            // presentation-only so the admin index is not one row per slot.
            $v2Templates = TournamentTemplate::query()
                ->with(['game.translations', 'scheduleSlots:id,tournament_template_id,schedule_start_at,schedule_end_at,day_of_week,day_of_month'])
                ->withCount(['scheduleSlots as slots_count'])
                ->where('workflow_version', 2)
                ->where('competition_type', CompetitionType::TOURNAMENT)
                ->whereHas('scheduleSlots.occurrences', fn ($occurrences) => $this->applyV2OccurrenceSubquery($occurrences, $v2Occurrences))
                ->orderByDesc('updated_at')
                ->paginate($this->perPage, ['*'], 'v2Page');

            // Retain historical V1 records in their original row-based UI.
            $query->where('workflow_version', 1);
        }

        // Counts per status group for tab badges
        $baseCount = Tournament::query();
        if (config('features.tournament_v2.enabled')) {
            $baseCount->where('workflow_version', 2);
        }
        $this->applyFilters($baseCount, includeStatus: true);
        $countActive = (clone $baseCount)->whereIn('status', $activeStatuses)->count();
        $countCompleted = (clone $baseCount)->where('status', TournamentStatus::COMPLETED->value)->count();
        $countCancelled = (clone $baseCount)->whereIn('status', [TournamentStatus::CANCELLED->value, TournamentStatus::REFUNDED->value])->count();
        $countAll = (clone $baseCount)->count();

        $tournaments = $query->paginate($this->perPage);
        $games = Game::with('translations')->get();
        $platforms = Platform::where('is_active', true)->get();

        $selectedTournament = ($this->showDetailModal || $this->showCancelModal) && $this->selectedTournamentId
            ? Tournament::with(['game.translations', 'registrations.user', 'cancellation.cancelledBy', 'rounds.matches', 'platform'])->find($this->selectedTournamentId)
            : null;

        return view('livewire.admin.tournament-admin', [
            'tournaments' => $tournaments,
            'v2Templates' => $v2Templates,
            'games' => $games,
            'platforms' => $platforms,
            'selectedTournament' => $selectedTournament,
            'countActive' => $countActive,
            'countCompleted' => $countCompleted,
            'countCancelled' => $countCancelled,
            'countAll' => $countAll,
        ])->layout('components.layouts.admin', [
            'admin_title' => 'Tournament Management',
        ]);
    }

    private function applyV2OccurrenceSubquery($occurrences, $source): void
    {
        $occurrences->whereIn('id', (clone $source)->select('id'));
    }

    private function applyFilters($query, bool $includeStatus): void
    {
        if ($this->search) {
            $query->where('name', 'like', '%'.$this->search.'%');
        }

        if ($includeStatus && $this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->gameFilter) {
            $query->where('game_id', $this->gameFilter);
        }

        if ($this->platformFilter) {
            $query->forPlatform((int) $this->platformFilter);
        }

        if ($this->activeTab !== 'all') {
            $query->where('frequency', $this->activeTab);
        }

        if ($this->startDateFilter) {
            $query->whereDate('start_at', '>=', $this->startDateFilter);
        }

        if ($this->endDateFilter) {
            $query->whereDate('start_at', '<=', $this->endDateFilter);
        }
    }
}
