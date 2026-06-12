<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Wallet\Actions\ApproveWithdrawalAction;
use App\Modules\Wallet\Actions\ProcessWithdrawalAction;
use App\Modules\Wallet\Actions\RejectWithdrawalAction;
use App\Modules\Wallet\Actions\ReviewWithdrawalAction;
use App\Modules\Wallet\Models\Withdrawal;
use App\Shared\Enums\WithdrawalStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;

class WithdrawalAdmin extends AdminComponent
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'pending'; // Default to pending reviews

    // Modals
    public bool $showDetailModal = false;

    public bool $showRejectModal = false;

    public bool $showApproveModal = false;

    // Selection
    public ?int $selectedWithdrawalId = null;

    // Forms
    public string $rejectReason = '';

    public string $approveNotes = '';

    protected $paginationTheme = 'tailwind';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function selectWithdrawal(int $id): void
    {
        $this->selectedWithdrawalId = $id;
        $withdrawal = Withdrawal::findOrFail($id);
        $reviewer = Auth::user();

        if ($reviewer && $withdrawal->status === WithdrawalStatus::PENDING) {
            // Verify they aren't reviewing their own request to avoid 4-eyes violation
            if ((int) $withdrawal->user_id !== (int) $reviewer->id) {
                try {
                    app(ReviewWithdrawalAction::class)->execute($withdrawal, $reviewer);
                    session()->flash('info', 'Withdrawal request moved to UNDER REVIEW.');
                } catch (\Exception $e) {
                    session()->flash('error', 'Could not start review: '.$e->getMessage());
                }
            }
        }

        $this->showDetailModal = true;
    }

    public function openApproveModal(): void
    {
        $this->approveNotes = '';
        $this->showApproveModal = true;
    }

    public function approve(ApproveWithdrawalAction $action): void
    {
        if (! $this->selectedWithdrawalId) {
            return;
        }

        $withdrawal = Withdrawal::findOrFail($this->selectedWithdrawalId);
        $reviewer = Auth::user();

        if (! $reviewer) {
            return;
        }

        try {
            $action->execute($withdrawal, $reviewer, $this->approveNotes);
            session()->flash('success', 'Withdrawal approved.');
            $this->showApproveModal = false;
            $this->showDetailModal = false;
        } catch (\Exception $e) {
            session()->flash('error', 'Approval failed: '.$e->getMessage());
        }
    }

    public function openRejectModal(): void
    {
        $this->rejectReason = '';
        $this->showRejectModal = true;
    }

    public function reject(RejectWithdrawalAction $action): void
    {
        $this->validate([
            'rejectReason' => 'required|string|min:5|max:255',
        ]);

        if (! $this->selectedWithdrawalId) {
            return;
        }

        $withdrawal = Withdrawal::findOrFail($this->selectedWithdrawalId);
        $reviewer = Auth::user();

        if (! $reviewer) {
            return;
        }

        try {
            // Make sure we use the correct action inject
            $action = app(RejectWithdrawalAction::class);
            $action->execute($withdrawal, $reviewer, $this->rejectReason);
            session()->flash('success', 'Withdrawal request rejected.');
            $this->showRejectModal = false;
            $this->showDetailModal = false;
        } catch (\Exception $e) {
            session()->flash('error', 'Rejection failed: '.$e->getMessage());
        }
    }

    public function processPayout(ProcessWithdrawalAction $action): void
    {
        if (! $this->selectedWithdrawalId) {
            return;
        }

        $withdrawal = Withdrawal::findOrFail($this->selectedWithdrawalId);

        try {
            $action->execute($withdrawal);
            session()->flash('success', 'Withdrawal payout marked as PROCESSED.');
            $this->showDetailModal = false;
        } catch (\Exception $e) {
            session()->flash('error', 'Payout processing failed: '.$e->getMessage());
        }
    }

    public function render()
    {
        $query = Withdrawal::query()
            ->with(['user', 'reviewer'])
            ->orderBy('created_at', 'desc');

        if ($this->search) {
            $query->whereHas('user', function ($q) {
                $q->where('username', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        $withdrawals = $query->paginate(15);

        $selectedWithdrawal = null;
        $userKyc = null;
        $walletHistory = [];

        if ($this->selectedWithdrawalId) {
            $selectedWithdrawal = Withdrawal::with(['user', 'reviewer', 'wallet'])->find($this->selectedWithdrawalId);
            if ($selectedWithdrawal && $selectedWithdrawal->user) {
                $userKyc = KycSubmission::where('user_id', $selectedWithdrawal->user_id)
                    ->orderBy('created_at', 'desc')
                    ->first();
                $walletHistory = $selectedWithdrawal->wallet
                    ? $selectedWithdrawal->wallet->ledgerEntries()->orderBy('created_at', 'desc')->take(10)->get()
                    : [];
            }
        }

        return view('livewire.admin.withdrawal-admin', [
            'withdrawals' => $withdrawals,
            'selectedWithdrawal' => $selectedWithdrawal,
            'userKyc' => $userKyc,
            'walletHistory' => $walletHistory,
        ])->layout('components.layouts.admin', [
            'admin_title' => 'Finance & Payout Control',
        ]);
    }
}
