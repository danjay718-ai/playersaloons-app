<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Identity\Actions\ApproveKycAction;
use App\Modules\Identity\Actions\RejectKycAction;
use App\Modules\Identity\Actions\ReviewKycAction;
use App\Modules\Identity\Models\KycSubmission;
use App\Shared\Enums\KycStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

class KycAdmin extends AdminComponent
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'submitted'; // Default to submitted (pending review)

    // Modals
    public bool $showDetailModal = false;
    public bool $showRejectModal = false;

    // Selection
    public ?int $selectedSubmissionId = null;

    // Form
    public string $rejectReason = '';

    protected $paginationTheme = 'tailwind';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function selectSubmission(int $id): void
    {
        $this->selectedSubmissionId = $id;
        $submission = KycSubmission::findOrFail($id);
        
        // If it's in SUBMITTED state, automatically transition it to UNDER_REVIEW
        if ($submission->status === KycStatus::SUBMITTED) {
            try {
                app(ReviewKycAction::class)->execute($submission);
                session()->flash('info', 'Submission is now marked as UNDER REVIEW.');
            } catch (\Exception $e) {
                session()->flash('error', 'Could not transition state: ' . $e->getMessage());
            }
        }

        $this->showDetailModal = true;
    }

    public function approve(ApproveKycAction $action): void
    {
        if (!$this->selectedSubmissionId) return;

        $submission = KycSubmission::findOrFail($this->selectedSubmissionId);
        $reviewer = Auth::user();

        if (!$reviewer) return;

        try {
            $action->execute($submission, $reviewer);
            session()->flash('success', 'KYC submission approved successfully.');
            $this->showDetailModal = false;
        } catch (\Exception $e) {
            session()->flash('error', 'Approval failed: ' . $e->getMessage());
        }
    }

    public function openRejectModal(): void
    {
        $this->rejectReason = '';
        $this->showRejectModal = true;
    }

    public function reject(RejectKycAction $action): void
    {
        $this->validate([
            'rejectReason' => 'required|string|min:5|max:255',
        ]);

        if (!$this->selectedSubmissionId) return;

        $submission = KycSubmission::findOrFail($this->selectedSubmissionId);
        $reviewer = Auth::user();

        if (!$reviewer) return;

        try {
            $action->execute($submission, $reviewer, $this->rejectReason);
            session()->flash('success', 'KYC submission rejected.');
            $this->showRejectModal = false;
            $this->showDetailModal = false;
        } catch (\Exception $e) {
            session()->flash('error', 'Rejection failed: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $query = KycSubmission::query()
            ->with(['user', 'reviewer'])
            ->orderBy('created_at', 'desc');

        if ($this->search) {
            $query->whereHas('user', function ($q) {
                $q->where('username', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        $submissions = $query->paginate(15);

        $selectedSubmission = $this->selectedSubmissionId
            ? KycSubmission::with(['user', 'reviewer'])->find($this->selectedSubmissionId)
            : null;

        // Fetch KYC decision audit trail
        $auditTrail = Activity::where('log_name', 'default')
            ->whereIn('description', ['kyc_approved', 'kyc_rejected'])
            ->with(['causer', 'subject'])
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        return view('livewire.admin.kyc-admin', [
            'submissions' => $submissions,
            'selectedSubmission' => $selectedSubmission,
            'auditTrail' => $auditTrail,
        ])->layout('components.layouts.admin', [
            'admin_title' => 'KYC Compliance Queue',
        ]);
    }
}
