<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Operations\Services\AdminDeletionService;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;

class RecoverableDelete extends AdminComponent
{
    use WithPagination;

    #[Locked]
    public string $resource;

    #[Locked]
    public ?int $parentId = null;

    #[Locked]
    public ?int $recordId = null;

    #[Locked]
    public bool $menuItem = false;

    #[Locked]
    public array $reviewedIds = [];

    public bool $showModal = false;

    public string $search = '';

    public array $selectedIds = [];

    #[Locked]
    public bool $confirming = false;

    public function mount(string $resource, ?int $parentId = null, ?int $recordId = null, bool $menuItem = false): void
    {
        app(AdminDeletionService::class)->definition($resource);
        $this->resource = $resource;
        $this->parentId = $parentId;
        $this->recordId = $recordId;
        $this->menuItem = $menuItem;
    }

    private function authorizeDeletion(): void
    {
        abort_unless($this->actor()->can(app(AdminDeletionService::class)->definition($this->resource)[1]), 403);
    }

    public function open(): void
    {
        $this->authorizeDeletion();
        $this->reset(['selectedIds', 'reviewedIds', 'search', 'confirming']);
        $this->resetValidation();
        $this->resetPage('deletePage');
        $this->showModal = true;
        if ($this->recordId !== null) {
            $this->selectedIds = [$this->recordId];
            $this->preview();
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage('deletePage');
    }

    public function updatedSelectedIds(): void
    {
        $this->confirming = false;
    }

    public function preview(): void
    {
        $this->authorizeDeletion();
        $this->validate(['selectedIds' => 'required|array|min:1|max:100', 'selectedIds.*' => 'required|integer|min:1|distinct']);
        $this->confirming = true;
        $this->reviewedIds = $this->selectedIds;
    }

    public function cancel(): void
    {
        $this->reset(['showModal', 'selectedIds', 'reviewedIds', 'search', 'confirming']);
        $this->resetValidation();
        $this->resetPage('deletePage');
    }

    public function deleteSelected(): void
    {
        $this->authorizeDeletion();
        abort_unless($this->confirming, 422);
        abort_unless($this->selectedIds === $this->reviewedIds, 422);
        $count = app(AdminDeletionService::class)->delete($this->resource, $this->reviewedIds, $this->actor(), $this->parentId);
        $this->showModal = false;
        $this->reset('selectedIds', 'confirming');
        session()->flash('success', $count.' record(s) deleted. Connected records were preserved.');
        $this->dispatch('admin-records-deleted');
    }

    public function render()
    {
        $service = app(AdminDeletionService::class);
        $definition = $service->definition($this->resource);
        $allowed = $this->actor()->can($definition[1]);
        $records = null;
        $selected = collect();
        $blockedReasons = [];
        if ($allowed && $this->showModal) {
            $query = $service->query($this->resource, $this->parentId);
            if ($this->search !== '') {
                $query->where($definition[2], 'like', '%'.$this->search.'%');
            }
            $records = $query->orderByDesc('id')->paginate(15, ['*'], 'deletePage');
            if ($this->confirming) {
                $selected = $service->query($this->resource, $this->parentId)->whereKey(array_slice($this->selectedIds, 0, 100))->get();
                foreach ($selected as $record) {
                    if ($reason = $service->blockedReason($record, $this->actor())) {
                        $blockedReasons[$record->id] = $reason;
                    }
                }
            }
        }

        return view('livewire.admin.recoverable-delete', compact('allowed', 'definition', 'records', 'selected', 'service', 'blockedReasons'));
    }
}
