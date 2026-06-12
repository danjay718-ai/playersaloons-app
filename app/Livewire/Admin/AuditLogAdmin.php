<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

class AuditLogAdmin extends AdminComponent
{
    use WithPagination;

    public string $actorSearch = '';

    public string $actionFilter = '';

    public string $entityTypeFilter = '';

    public string $startDate = '';

    public string $endDate = '';

    // Modals
    public bool $showDetailModal = false;

    public ?int $selectedLogId = null;

    protected $paginationTheme = 'tailwind';

    public function updatingActorSearch(): void
    {
        $this->resetPage();
    }

    public function updatingActionFilter(): void
    {
        $this->resetPage();
    }

    public function updatingEntityTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStartDate(): void
    {
        $this->resetPage();
    }

    public function updatingEndDate(): void
    {
        $this->resetPage();
    }

    public function selectLog(int $id): void
    {
        $this->selectedLogId = $id;
        $this->showDetailModal = true;
    }

    public function render()
    {
        $query = Activity::query()
            ->with(['causer', 'subject'])
            ->orderBy('created_at', 'desc');

        if ($this->actorSearch) {
            $query->whereHas('causer', function ($q) {
                $q->where('username', 'like', '%'.$this->actorSearch.'%');
            });
        }

        if ($this->actionFilter) {
            $query->where('description', 'like', '%'.$this->actionFilter.'%');
        }

        if ($this->entityTypeFilter) {
            $query->where('subject_type', 'like', '%'.$this->entityTypeFilter.'%');
        }

        if ($this->startDate) {
            $query->whereDate('created_at', '>=', $this->startDate);
        }

        if ($this->endDate) {
            $query->whereDate('created_at', '<=', $this->endDate);
        }

        $logs = $query->paginate(20);

        // Fetch distinct subject types for dropdown helper
        $entityTypes = Activity::query()
            ->select('subject_type')
            ->whereNotNull('subject_type')
            ->distinct()
            ->pluck('subject_type')
            ->map(fn ($type) => basename($type))
            ->unique();

        $selectedLog = $this->selectedLogId ? Activity::with(['causer', 'subject'])->find($this->selectedLogId) : null;

        return view('livewire.admin.audit-log-admin', [
            'logs' => $logs,
            'entityTypes' => $entityTypes,
            'selectedLog' => $selectedLog,
        ])->layout('components.layouts.admin', [
            'admin_title' => 'Security & Action Audit Logs',
        ]);
    }
}
