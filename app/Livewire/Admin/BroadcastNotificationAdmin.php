<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Community\Models\BroadcastMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\WithPagination;

class BroadcastNotificationAdmin extends AdminComponent
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    // Form fields
    public string $title   = '';
    public string $message = '';
    public string $startsAt = '';
    public string $endsAt   = '';

    // Modal state
    public bool $showFormModal    = false;
    public bool $showConfirmModal = false;
    public ?int $editingId        = null;
    public ?int $confirmTargetId  = null;
    public string $confirmAction  = ''; // 'delete' | 'expire'

    // Search
    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    // ── CRUD ────────────────────────────────────────────────────────────────

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId   = null;
        $this->showFormModal = true;
    }

    public function openEdit(int $id): void
    {
        $broadcast = BroadcastMessage::findOrFail($id);

        $this->editingId  = $id;
        $this->title      = $broadcast->title;
        $this->message    = $broadcast->message;
        $this->startsAt   = $broadcast->starts_at ? \Illuminate\Support\Carbon::parse($broadcast->starts_at)->format('Y-m-d\TH:i') : '';
        $this->endsAt     = $broadcast->ends_at ? \Illuminate\Support\Carbon::parse($broadcast->ends_at)->format('Y-m-d\TH:i') : '';
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'title'    => 'required|string|max:255',
            'message'  => 'required|string|max:2000',
            'startsAt' => 'nullable|date',
            'endsAt'   => 'nullable|date|after_or_equal:startsAt',
        ]);

        $data = [
            'title'     => $this->title,
            'message'   => $this->message,
            'starts_at' => $this->startsAt ?: null,
            'ends_at'   => $this->endsAt   ?: null,
        ];

        if ($this->editingId) {
            BroadcastMessage::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Broadcast updated.');
        } else {
            BroadcastMessage::create(array_merge($data, ['uuid' => Str::uuid()->toString()]));
            session()->flash('success', 'Broadcast created.');
        }

        $this->showFormModal = false;
        $this->resetForm();
    }

    public function confirmExpire(int $id): void
    {
        $this->confirmTargetId = $id;
        $this->confirmAction   = 'expire';
        $this->showConfirmModal = true;
    }

    public function confirmDelete(int $id): void
    {
        $this->guardSuperAdmin();

        $this->confirmTargetId = $id;
        $this->confirmAction   = 'delete';
        $this->showConfirmModal = true;
    }

    public function executeConfirm(): void
    {
        if (! $this->confirmTargetId) {
            return;
        }

        $broadcast = BroadcastMessage::findOrFail($this->confirmTargetId);

        if ($this->confirmAction === 'expire') {
            $broadcast->update(['ends_at' => now()]);
            session()->flash('success', 'Broadcast expired.');
        } elseif ($this->confirmAction === 'delete') {
            $this->guardSuperAdmin();
            $broadcast->delete();
            session()->flash('success', 'Broadcast deleted.');
        }

        $this->showConfirmModal = false;
        $this->confirmTargetId  = null;
        $this->confirmAction    = '';
    }

    public function cancelConfirm(): void
    {
        $this->showConfirmModal = false;
        $this->confirmTargetId  = null;
        $this->confirmAction    = '';
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    public function isSuperAdmin(): bool
    {
        return (bool) Auth::user()?->hasRole('SUPER_ADMIN');
    }

    private function resetForm(): void
    {
        $this->title    = '';
        $this->message  = '';
        $this->startsAt = '';
        $this->endsAt   = '';
    }

    /**
     * Abort 403 if actor is not SUPER_ADMIN.
     */
    private function guardSuperAdmin(): void
    {
        if (! $this->isSuperAdmin()) {
            abort(403, 'Only Super Admins can perform this action.');
        }
    }

    // ── Render ───────────────────────────────────────────────────────────────

    public function render()
    {
        $query = BroadcastMessage::query()->orderBy('created_at', 'desc');

        if ($this->search) {
            $query->where(function ($q): void {
                $q->where('title', 'like', '%'.$this->search.'%')
                  ->orWhere('message', 'like', '%'.$this->search.'%');
            });
        }

        return view('livewire.admin.broadcast-notification-admin', [
            'broadcasts' => $query->paginate(15),
        ])->layout('components.layouts.admin', [
            'admin_title' => 'Broadcast Notifications',
        ]);
    }
}
