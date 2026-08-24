<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Community\Models\ContactInquiry;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;

class ContactInquiryAdmin extends AdminComponent
{
    use WithPagination;

    public function boot(): void
    {
        parent::boot();
        abort_unless($this->actor()->can('contact_inquiries.manage'), 403);
    }

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
        $statusCounts = ContactInquiry::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

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
            'statusCounts' => $statusCounts,
            'categories' => [
                'general' => 'General question',
                'account' => 'Account support',
                'tournament' => 'Tournament support',
                'wallet' => 'Wallet or payment',
                'kyc' => 'KYC verification',
                'bug' => 'Bug report',
            ],
            'categoryStyles' => [
                'general' => 'border-slate-600 bg-slate-800/60 text-slate-300',
                'account' => 'border-indigo-500/30 bg-indigo-500/10 text-indigo-300',
                'tournament' => 'border-violet-500/30 bg-violet-500/10 text-violet-300',
                'wallet' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
                'kyc' => 'border-amber-500/30 bg-amber-500/10 text-amber-300',
                'bug' => 'border-red-500/30 bg-red-500/10 text-red-300',
            ],
            'statusStyles' => [
                'new' => 'border-sky-500/30 bg-sky-500/10 text-sky-300',
                'in_review' => 'border-amber-500/30 bg-amber-500/10 text-amber-300',
                'resolved' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
                'archived' => 'border-slate-600 bg-slate-800/60 text-slate-400',
            ],
        ])->layout('components.layouts.admin', [
            'admin_title' => 'Contact Inquiries',
        ]);
    }
}
