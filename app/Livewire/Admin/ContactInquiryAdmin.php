<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Community\Models\ContactInquiry;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;

class ContactInquiryAdmin extends AdminComponent
{
    use WithPagination;

    public string $search = '';

    public string $status = 'new';

    public string $category = '';

    public ?int $selectedId = null;

    public string $adminNotes = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingCategory(): void
    {
        $this->resetPage();
    }

    public function selectInquiry(int $id): void
    {
        $inquiry = ContactInquiry::query()->findOrFail($id);

        $this->selectedId = $id;
        $this->adminNotes = (string) $inquiry->admin_notes;

        if ($inquiry->status === 'new') {
            $inquiry->update(['status' => 'in_review']);
        }
    }

    public function saveNotes(): void
    {
        $this->selectedInquiry()?->update(['admin_notes' => $this->adminNotes ?: null]);

        session()->flash('success', 'Inquiry notes saved.');
    }

    public function markResolved(): void
    {
        $inquiry = $this->selectedInquiry();

        if (! $inquiry) {
            session()->flash('error', 'Select an inquiry first.');

            return;
        }

        $inquiry->update([
            'status' => 'resolved',
            'admin_notes' => $this->adminNotes ?: null,
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
        ]);

        $this->status = 'resolved';
        $this->resetPage();

        session()->flash('success', 'Inquiry marked resolved.');
    }

    public function archive(): void
    {
        $inquiry = $this->selectedInquiry();

        if (! $inquiry) {
            session()->flash('error', 'Select an inquiry first.');

            return;
        }

        $inquiry->update([
            'status' => 'archived',
            'admin_notes' => $this->adminNotes ?: null,
        ]);

        $this->status = 'archived';
        $this->resetPage();

        session()->flash('success', 'Inquiry archived.');
    }

    private function selectedInquiry(): ?ContactInquiry
    {
        if (! $this->selectedId) {
            return null;
        }

        return ContactInquiry::query()->find($this->selectedId);
    }

    public function render()
    {
        $query = ContactInquiry::query()
            ->with(['user', 'resolver'])
            ->latest();

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        if ($this->category !== '') {
            $query->where('category', $this->category);
        }

        if ($this->search !== '') {
            $query->where(function ($q): void {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%')
                    ->orWhere('subject', 'like', '%'.$this->search.'%');
            });
        }

        return view('livewire.admin.contact-inquiry-admin', [
            'inquiries' => $query->paginate(12),
            'selectedInquiry' => $this->selectedInquiry(),
            'categories' => [
                'general' => 'General question',
                'account' => 'Account support',
                'tournament' => 'Tournament support',
                'wallet' => 'Wallet or payment',
                'kyc' => 'KYC verification',
                'bug' => 'Bug report',
            ],
        ])->layout('components.layouts.admin', [
            'admin_title' => 'Contact Inquiries',
        ]);
    }
}
