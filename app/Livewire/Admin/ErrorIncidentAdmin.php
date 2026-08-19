<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Operations\Models\ErrorIncident;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

class ErrorIncidentAdmin extends AdminComponent
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $levelFilter = '';

    #[Url]
    public string $sourceFilter = '';

    #[Url]
    public string $stateFilter = 'open';

    #[Url]
    public string $startDateFilter = '';

    #[Url]
    public string $endDateFilter = '';

    #[Url]
    public int $perPage = 10;

    public ?int $selectedIncidentId = null;

    public bool $showDetailModal = false;

    public string $resolutionNotes = '';

    protected $paginationTheme = 'tailwind';

    public function boot(): void
    {
        parent::boot();

        abort_unless(Auth::user()?->can('error_incidents.view'), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, [
            'search',
            'levelFilter',
            'sourceFilter',
            'stateFilter',
            'startDateFilter',
            'endDateFilter',
            'perPage',
        ], true)) {
            $this->resetPage();
        }
    }

    public function selectIncident(int $id): void
    {
        $this->selectedIncidentId = $id;
        $this->resolutionNotes = '';
        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->reset('selectedIncidentId', 'resolutionNotes', 'showDetailModal');
    }

    public function resolveIncident(): void
    {
        abort_unless(Auth::user()?->can('error_incidents.manage'), 403);

        $this->validate([
            'selectedIncidentId' => ['required', 'integer', 'exists:error_incidents,id'],
            'resolutionNotes' => ['nullable', 'string', 'max:2000'],
        ]);

        ErrorIncident::query()->findOrFail($this->selectedIncidentId)->update([
            'resolved_at' => now(),
            'resolved_by' => Auth::id(),
            'resolution_notes' => trim($this->resolutionNotes) ?: null,
        ]);

        session()->flash('success', 'Error incident marked as resolved.');
        $this->closeDetailModal();
    }

    public function reopenIncident(): void
    {
        abort_unless(Auth::user()?->can('error_incidents.manage'), 403);

        ErrorIncident::query()->findOrFail($this->selectedIncidentId)->update([
            'resolved_at' => null,
            'resolved_by' => null,
            'resolution_notes' => null,
        ]);

        session()->flash('success', 'Error incident reopened.');
        $this->closeDetailModal();
    }

    public function render()
    {
        $query = ErrorIncident::query()
            ->with(['user:id,username,email', 'resolver:id,username'])
            ->latest('last_seen_at');

        if ($this->search !== '') {
            $search = '%'.$this->search.'%';
            $query->where(function ($builder) use ($search): void {
                $builder->where('reference_id', 'like', $search)
                    ->orWhere('exception_class', 'like', $search)
                    ->orWhere('message', 'like', $search)
                    ->orWhere('route', 'like', $search);
            });
        }

        if ($this->levelFilter !== '') {
            $query->where('level', $this->levelFilter);
        }

        if ($this->sourceFilter !== '') {
            $query->where('source', $this->sourceFilter);
        }

        if ($this->stateFilter === 'open') {
            $query->whereNull('resolved_at');
        } elseif ($this->stateFilter === 'resolved') {
            $query->whereNotNull('resolved_at');
        }

        if ($this->startDateFilter !== '') {
            $query->whereDate('last_seen_at', '>=', $this->startDateFilter);
        }

        if ($this->endDateFilter !== '') {
            $query->whereDate('last_seen_at', '<=', $this->endDateFilter);
        }

        $incidents = $query->paginate($this->perPage);
        $selectedIncident = $this->showDetailModal && $this->selectedIncidentId
            ? ErrorIncident::query()->with(['user:id,username,email', 'resolver:id,username'])->find($this->selectedIncidentId)
            : null;

        return view('livewire.admin.error-incident-admin', [
            'incidents' => $incidents,
            'selectedIncident' => $selectedIncident,
            'openCount' => ErrorIncident::query()->whereNull('resolved_at')->count(),
            'todayCount' => ErrorIncident::query()->whereDate('last_seen_at', today())->sum('occurrences'),
        ])->layout('components.layouts.admin', ['admin_title' => 'Error Logs']);
    }
}
