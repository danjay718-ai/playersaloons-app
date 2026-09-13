<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

class AuditLogAdmin extends AdminComponent
{
    use WithPagination;

    public function boot(): void
    {
        parent::boot();
        abort_unless($this->actor()->can('audit_logs.view'), 403);
    }

    public string $actorSearch = '';

    public string $actionFilter = '';

    public string $entityTypeFilter = '';

    public string $startDate = '';

    public string $endDate = '';

    // Modals
    public bool $showDetailModal = false;

    public bool $showClearModal = false;

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

    public function requestClearLogs(): void
    {
        abort_unless($this->actor()->can('audit_logs.manage'), 403);

        $this->showClearModal = true;
    }

    public function clearLogs(): void
    {
        abort_unless($this->actor()->can('audit_logs.manage'), 403);

        $actor = $this->actor();
        $deletedCount = DB::transaction(function () use ($actor): int {
            $count = Activity::query()->count();

            Activity::query()->delete();

            activity()
                ->causedBy($actor)
                ->withProperties([
                    'deleted_count' => $count,
                    'ip' => request()->ip(),
                ])
                ->log('audit_logs_cleared');

            return $count;
        });

        $this->reset('showClearModal', 'showDetailModal', 'selectedLogId');
        $this->resetPage();
        session()->flash('success', number_format($deletedCount).' prior audit log record(s) cleared.');
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
